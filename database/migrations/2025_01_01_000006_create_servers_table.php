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
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('pelican_server_id')->nullable()->unique();
            $table->string('name');
            $table->enum('status', ['active', 'suspended', 'terminated', 'running', 'stopped', 'offline'])->default('active');
            $table->foreignId('egg_id')->constrained('eggs')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('server_plans')->nullOnDelete();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('payment_intent_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
