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
            $table->text('description')->nullable()->after('category');
            $table->json('keywords')->nullable()->after('diet');
            $table->string('cuisine')->nullable()->after('category');
            $table->decimal('rating_value', 2, 1)->nullable()->after('images');
            $table->unsignedInteger('rating_count')->nullable()->after('rating_value');
            $table->string('author')->nullable()->after('url');
            $table->timestamp('published_at')->nullable()->after('author');
            $table->timestamp('modified_at')->nullable()->after('published_at');
            $table->unsignedInteger('comment_count')->nullable()->after('rating_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scrap_recipes', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'keywords',
                'cuisine',
                'rating_value',
                'rating_count',
                'author',
                'published_at',
                'modified_at',
                'comment_count',
            ]);
        });
    }
};
