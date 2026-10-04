<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('articles')
            ->select(['id', 'source_content'])
            ->whereNotNull('content_hash')
            ->orderBy('id')
            ->chunkById(500, function ($articles): void {
                foreach ($articles as $article) {
                    $sourceContent = (string) $article->source_content;
                    $normalized = preg_replace('/\s+/u', ' ', $sourceContent);
                    $content = trim($normalized === null ? $sourceContent : $normalized);
                    DB::table('articles')->where('id', $article->id)->update([
                        'content_hash' => hash('sha256', $content),
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('articles')
            ->select(['id', 'source_content'])
            ->whereNotNull('content_hash')
            ->orderBy('id')
            ->chunkById(500, function ($articles): void {
                foreach ($articles as $article) {
                    DB::table('articles')->where('id', $article->id)->update([
                        'content_hash' => hash('sha256', (string) $article->source_content),
                    ]);
                }
            });
    }
};
