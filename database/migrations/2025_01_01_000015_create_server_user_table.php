<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->enum('role', ['owner', 'subuser'])->default('owner');
            $table->json('permissions')->nullable(); // null = owner = all permissions
            $table->timestamps();

            $table->unique(['user_id', 'server_id']);
        });

        // Seed existing server owners into the pivot table
        $servers = \Illuminate\Support\Facades\DB::table('servers')->whereNotNull('user_id')->get();

        foreach ($servers as $server) {
            \Illuminate\Support\Facades\DB::table('server_user')->insert([
                'user_id' => $server->user_id,
                'server_id' => $server->id,
                'role' => 'owner',
                'permissions' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('server_user');
    }
};
