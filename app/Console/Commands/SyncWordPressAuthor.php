<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\WordPressService;
use Illuminate\Console\Command;
use Throwable;

class SyncWordPressAuthor extends Command
{
    protected $signature = 'wordpress:sync-author {article : ID artikel Laravel}';

    protected $description = 'Isi field Penulis Berita pada post WordPress yang sudah terhubung';

    public function handle(WordPressService $wordpress): int
    {
        $article = Article::query()->find($this->argument('article'));

        if (! $article) {
            $this->error('Artikel tidak ditemukan.');

            return self::FAILURE;
        }

        try {
            $wordpress->syncPostAuthor($article);
            $article->update(['error_message' => null]);
            $this->info("Penulis Berita '{$article->source_author}' berhasil disimpan pada post WordPress {$article->wordpress_post_id}.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $article->update(['error_message' => $exception->getMessage()]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
