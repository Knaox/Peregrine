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
        Schema::create('server_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('stripe_price_id')->unique();
            $table->foreignId('egg_id')->constrained('eggs')->cascadeOnDelete();
            $table->foreignId('nest_id')->constrained('nests')->cascadeOnDelete();
            $table->unsignedInteger('ram');
            $table->unsignedInteger('cpu');
            $table->unsignedInteger('disk');
            $table->foreignId('node_id')->constrained('nodes')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_plans');
    }
};
