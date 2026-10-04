<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('seo_focus_keyword')->nullable();
            $table->json('seo_tags')->nullable();
            $table->string('seo_category')->nullable();
            $table->unsignedBigInteger('wordpress_category_id')->nullable();
            $table->json('wordpress_tag_ids')->nullable();
            $table->unsignedBigInteger('wordpress_author_id')->nullable();
            $table->unsignedBigInteger('wordpress_media_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn([
                'seo_focus_keyword',
                'seo_tags',
                'seo_category',
                'wordpress_category_id',
                'wordpress_tag_ids',
                'wordpress_author_id',
                'wordpress_media_id',
            ]);
        });
    }
};
