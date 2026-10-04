<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\AIService;
use Illuminate\Console\Command;
use Throwable;

class RewriteArticle extends Command
{
    protected $signature = 'ai:rewrite {article? : ID artikel yang akan ditulis ulang}';

    protected $description = 'Tulis ulang artikel berdasarkan fakta tervalidasi menggunakan Gemini';

    public function handle(AIService $aiService): int
    {
        if (! config('services.gemini.api_key')) {
            $this->error('GEMINI_API_KEY belum diatur di file .env.');

            return self::FAILURE;
        }

        $articleId = $this->argument('article');
        $articles = $articleId
            ? Article::query()->whereKey($articleId)->get()
            : Article::query()
                ->whereIn('status', ['processing', 'failed'])
                ->whereNotNull('facts')
                ->whereNull('rewritten_title')
                ->whereNull('rewritten_content')
                ->orderBy('id')
                ->limit(1)
                ->get();

        if ($articles->isEmpty()) {
            $this->info('Tidak ada artikel yang menunggu rewrite.');

            return self::SUCCESS;
        }

        $succeeded = 0;
        $failed = 0;

        foreach ($articles as $article) {
            try {
                $rewrite = $aiService->rewriteArticleWithModel($article);

                $article->update([
                    'rewritten_title' => $rewrite['title'],
                    'rewritten_content' => $rewrite['content'],
                    'seo_meta_description' => $rewrite['meta_description'],
                    'seo_focus_keyword' => $rewrite['focus_keyword'],
                    'seo_tags' => $rewrite['tags'],
                    'seo_category' => $rewrite['category'],
                    'ai_model' => $rewrite['model'],
                    'ai_processed_at' => now(),
                    'status' => 'drafted',
                    'error_message' => null,
                ]);

                $succeeded++;
                $this->line("Artikel {$article->id}: rewrite berhasil ({$rewrite['model']}).");
            } catch (Throwable $exception) {
                $article->update([
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                ]);

                $failed++;
                $this->error("Artikel {$article->id}: {$exception->getMessage()}");
            }
        }

        $this->info("Berhasil: {$succeeded}; gagal: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
