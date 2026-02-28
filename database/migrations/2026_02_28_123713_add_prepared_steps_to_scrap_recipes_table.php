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
        Schema::table('scrap_recipes', function (Blueprint $table) {
            $table->json('prepared_steps')->nullable()->after('prepared_ingredients');
        });
    }

    public function down(): void
    {
        Schema::table('scrap_recipes', function (Blueprint $table) {
            $table->dropColumn('prepared_steps');
        });
    }
};
