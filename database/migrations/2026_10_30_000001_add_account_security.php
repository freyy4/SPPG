<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('pin_hash')->nullable();
            $table->text('totp_secret')->nullable();
            $table->timestamp('totp_enabled_at')->nullable();
            $table->unsignedBigInteger('totp_last_step')->nullable();
            $table->text('totp_pending_secret')->nullable();
            $table->timestamp('totp_pending_expires_at')->nullable();
            $table->text('recovery_code_hashes')->nullable();
        });
        Schema::create('security_audit_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('event', 64);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_audit_events');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['phone', 'avatar_path', 'pin_hash', 'totp_secret', 'totp_enabled_at', 'totp_last_step', 'totp_pending_secret', 'totp_pending_expires_at', 'recovery_code_hashes']));
    }
};
