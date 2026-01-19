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
        Schema::create('scrap_recipes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url')->unique();
            $table->string('category')->nullable();
            $table->string('prep_time')->nullable();
            $table->string('cook_time')->nullable();
            $table->string('total_time')->nullable();
            $table->string('servings')->nullable();
            $table->json('nutrition')->nullable();
            $table->json('ingredients');
            $table->json('steps');
            $table->json('images')->nullable();
            $table->string('diet')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scrap_recipes');
    }
};
