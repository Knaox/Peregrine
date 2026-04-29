<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plugin-side mirror of Pelican's `subusers` table. Populated by the
 * SyncPelicanSubuser listener which reacts to App\Events\Bridge\SubuserSynced
 * fired by core when Pelican forwards a subuser-related webhook.
 *
 * Lives entirely under the invitations plugin — core never writes here.
 * Plugin-prefixed (`invitations_*`) to stay clear of any future core
 * `pelican_subusers` table without collision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations_pelican_subusers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pelican_subuser_id')->unique();
            $table->unsignedBigInteger('pelican_server_id')->index();
            $table->unsignedBigInteger('pelican_user_id')->index();
            $table->json('permissions')->nullable();
            $table->timestamp('pelican_created_at')->nullable();
            $table->timestamp('pelican_updated_at')->nullable();
            $table->timestamps();

            $table->index(['pelican_server_id', 'pelican_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations_pelican_subusers');
    }
};
