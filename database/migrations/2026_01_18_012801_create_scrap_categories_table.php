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
        Schema::create('scrap_categories', function (Blueprint $table) {
            $table->id();
            $table->string('url')->unique();
            $table->boolean('is_scraped')->default(false);
            $table->string('type')->default('ania-gotuje');
            $table->timestamps();
            $table->index(['is_scraped', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scrap_categories');
    }
};
