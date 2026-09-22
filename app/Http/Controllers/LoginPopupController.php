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
                'title' => ['nullable', 'string', 'max:150'],
                'message' => ['nullable', 'string', 'max:5000'],
            ]);
        } catch (ValidationException $exception) {
            $this->queueFeedback($request, 'error', $exception->validator->errors()->first());
            throw $exception;
        }

        $setting = LoginPopupSetting::firstOrNew(['user_id' => $user->id]);
        $setting->enabled = true;
        $setting->title = $validated['title'] ?? null;
        $setting->message = $validated['message'] ?? null;

        if (!$setting->title && !$setting->message) {
            $message = 'Isi judul atau pesan sebelum menyimpan popup.';
            $this->queueFeedback($request, 'error', $message);
            return back()->withErrors(['title' => $message], 'loginPopup')->withInput();
        }

        $setting->save();

        $this->queueFeedback($request, 'success', 'Popup untuk '.$user->name.' berhasil disimpan dan diaktifkan.');
        return back();
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
            $this->queueFeedback($request, 'error', $exception->validator->errors()->first());
            throw $exception;
        }
        $setting = LoginPopupSetting::firstOrNew(['user_id' => $user->id]);

        if ($validated['enabled'] && !$setting->title && !$setting->message) {
            $message = 'Isi judul atau pesan melalui Setting Popup sebelum mengaktifkannya.';
            $this->queueFeedback($request, 'error', $message);
            return back()->withErrors(['enabled' => $message], 'loginPopup');
        }

        $setting->enabled = (bool) $validated['enabled'];
        $setting->save();

        $this->queueFeedback($request, 'success', 'Popup untuk '.$user->name.' berhasil '.($setting->enabled ? 'diaktifkan.' : 'dinonaktifkan.'));
        return back();
    }

    private function queueFeedback(Request $request, string $type, string $message): void
    {
        $request->session()->put('popup_feedback', compact('type', 'message'));
    }

}
