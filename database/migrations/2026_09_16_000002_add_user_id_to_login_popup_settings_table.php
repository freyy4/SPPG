<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('login_popup_settings', 'user_id')) {
            Schema::table('login_popup_settings', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->unique()->constrained('users')->cascadeOnDelete();
            });
        }

        $legacy = DB::table('login_popup_settings')->whereNull('user_id')->first();
        if ($legacy) {
            foreach (DB::table('users')->whereNull('deleted_at')->pluck('id') as $userId) {
                DB::table('login_popup_settings')->insert([
                    'user_id' => $userId,
                    'enabled' => $legacy->enabled,
                    'title' => $legacy->title,
                    'message' => $legacy->message,
                    'image_path' => $legacy->image_path,
                    'created_at' => $legacy->created_at,
                    'updated_at' => $legacy->updated_at,
                ]);
            }
            DB::table('login_popup_settings')->where('id', $legacy->id)->delete();
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('login_popup_settings', 'user_id')) {
            Schema::table('login_popup_settings', function (Blueprint $table) {
                $table->dropUnique(['user_id']);
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};
