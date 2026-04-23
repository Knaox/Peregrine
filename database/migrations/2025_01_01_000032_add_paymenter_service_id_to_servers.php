<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores Paymenter's service ID on each Server row when running in Bridge
 * Paymenter mode. The Pelican-Paymenter extension persists this as the
 * Pelican server's `external_id` field; we mirror it locally for audit
 * and for support flows ("which Paymenter service does Server #42 belong
 * to?"). Never used as a functional key — the canonical identifier is
 * still `pelican_server_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('paymenter_service_id')->nullable()->after('payment_intent_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropIndex(['paymenter_service_id']);
            $table->dropColumn('paymenter_service_id');
        });
    }
};
