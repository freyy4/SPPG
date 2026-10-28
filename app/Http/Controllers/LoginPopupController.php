<?php

namespace App\Http\Controllers;

use App\Models\LoginPopupSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LoginPopupController extends Controller
{
    public function update(Request $request, User $user)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        if (!Schema::hasColumn('login_popup_settings', 'user_id')) {
            $this->queueFeedback($request, 'error', 'Tambahkan kolom user_id pada tabel login_popup_settings terlebih dahulu.');
            return back()->withErrors(['popup' => 'Tambahkan kolom user_id pada tabel login_popup_settings terlebih dahulu.'], 'loginPopup');
        }

        try {
            $validated = $request->validateWithBag('loginPopup', [
                // Explicit enable state identifies the v2.3 contract. Legacy clients
                // retain their existing length and automatic-enable behavior.
                'title' => ['nullable', 'string', $request->has('enabled') ? 'max:100' : 'max:150'],
                'message' => ['nullable', 'string', $request->has('enabled') ? 'max:500' : 'max:5000'],
                'enabled' => ['sometimes', 'boolean'],
                '_version' => ['sometimes', 'string'],
            ]);
        } catch (ValidationException $exception) {
            if (!$request->expectsJson()) $this->queueFeedback($request, 'error', $exception->validator->errors()->first());
            throw $exception;
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $user, $validated) {
            // Serialize writes for this account, including first-time settings.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $setting = LoginPopupSetting::firstOrNew(['user_id' => $user->id]);
            if (isset($validated['_version']) && !hash_equals(\App\Support\PopupVersion::token($setting), $validated['_version'])) {
                return response()->json([
                    'message' => 'Konfigurasi telah berubah. Draf Anda tetap tersimpan; muat versi server untuk meninjau perubahan.',
                    'current' => ['title' => $setting->title ?? '', 'message' => $setting->message ?? '', 'enabled' => (bool) $setting->enabled, 'version' => \App\Support\PopupVersion::token($setting)],
                ], 409);
            }
            $setting->enabled = (bool) ($validated['enabled'] ?? true);
            $setting->title = $validated['title'] ?? null;
            $setting->message = $validated['message'] ?? null;
            if ($setting->enabled && !$setting->title && !$setting->message) {
                $message = 'Isi judul atau pesan sebelum menyimpan popup.';
                if ($request->expectsJson()) return response()->json(['message' => $message, 'errors' => ['title' => [$message]]], 422);
                $this->queueFeedback($request, 'error', $message);
                return back()->withErrors(['title' => $message], 'loginPopup')->withInput();
            }
            $setting->save();
            $message = 'Popup untuk '.$user->name.' berhasil disimpan dan '.($setting->enabled ? 'diaktifkan.' : 'dinonaktifkan.');
            if ($request->expectsJson()) return response()->json(['success' => true, 'message' => $message]);
            $this->queueFeedback($request, 'success', $message);
            return back();
        });
    }

    public function toggle(Request $request, User $user)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        if (!Schema::hasColumn('login_popup_settings', 'user_id')) {
            $this->queueFeedback($request, 'error', 'Tambahkan kolom user_id pada tabel login_popup_settings terlebih dahulu.');
            return back()->withErrors(['popup' => 'Tambahkan kolom user_id pada tabel login_popup_settings terlebih dahulu.'], 'loginPopup');
        }

        try {
            $validated = $request->validateWithBag('loginPopup', ['enabled' => ['required', 'boolean']]);
        } catch (ValidationException $exception) {
            if (!$request->expectsJson()) $this->queueFeedback($request, 'error', $exception->validator->errors()->first());
            throw $exception;
        }
        $setting = LoginPopupSetting::firstOrNew(['user_id' => $user->id]);

        if ($validated['enabled'] && !$setting->title && !$setting->message) {
            $message = 'Isi judul atau pesan melalui Setting Popup sebelum mengaktifkannya.';
            if ($request->expectsJson()) return response()->json(['message' => $message, 'errors' => ['enabled' => [$message]]], 422);
            $this->queueFeedback($request, 'error', $message);
            return back()->withErrors(['enabled' => $message], 'loginPopup');
        }

        $setting->enabled = (bool) $validated['enabled'];
        $setting->save();

        if ($request->expectsJson()) return response()->json(['success' => true, 'enabled' => $setting->enabled]);
        $this->queueFeedback($request, 'success', 'Popup untuk '.$user->name.' berhasil '.($setting->enabled ? 'diaktifkan.' : 'dinonaktifkan.'));
        return back();
    }

    private function queueFeedback(Request $request, string $type, string $message): void
    {
        $request->session()->put('popup_feedback', compact('type', 'message'));
    }

}
