<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('locale', ['en', 'fr'])->default('en')->after('name');
            $table->boolean('is_admin')->default(false)->after('locale');
            $table->unsignedBigInteger('pelican_user_id')->nullable()->unique()->after('remember_token');
            $table->string('stripe_customer_id')->nullable()->after('pelican_user_id');
            $table->string('oauth_provider')->nullable()->after('stripe_customer_id');
            $table->string('oauth_id')->nullable()->after('oauth_provider');

            $table->string('password')->nullable()->change();

            $table->index('email');
            $table->index('pelican_user_id');
            $table->index(['oauth_provider', 'oauth_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['oauth_provider', 'oauth_id']);
            $table->dropIndex(['pelican_user_id']);
            $table->dropIndex(['email']);

            $table->string('password')->nullable(false)->change();

            $table->dropColumn([
                'locale',
                'is_admin',
                'pelican_user_id',
                'stripe_customer_id',
                'oauth_provider',
                'oauth_id',
            ]);
        });
    }
};
