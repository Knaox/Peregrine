<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Align 2FA columns with Filament 5's HasAppAuthentication /
     * HasAppAuthenticationRecovery contracts so the Filament admin panel's
     * built-in 2FA actions (SetUpAppAuthenticationAction, Disable…, Regenerate…)
     * share the same storage as our SPA TwoFactorService. No double schema,
     * no double implementation.
     *
     * `two_factor_confirmed_at` stays untouched — Filament infers "enabled"
     * from filled($secret) but we want an audit timestamp for notifications.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('two_factor_secret', 'app_authentication_secret');
            $table->renameColumn('two_factor_recovery_codes', 'app_authentication_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('app_authentication_secret', 'two_factor_secret');
            $table->renameColumn('app_authentication_recovery_codes', 'two_factor_recovery_codes');
        });
    }
};
