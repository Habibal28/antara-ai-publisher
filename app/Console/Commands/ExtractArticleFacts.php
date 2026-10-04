<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\AIService;
use Illuminate\Console\Command;
use Throwable;

class ExtractArticleFacts extends Command
{
    protected $signature = 'ai:extract-facts {article? : ID artikel yang akan diproses}';

    protected $description = 'Ekstrak fakta artikel ANTARA menggunakan Gemini';

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
                ->whereIn('status', ['pending', 'scraped', 'failed'])
                ->whereNull('facts')
                ->orderBy('id')
                ->limit(1)
                ->get();

        if ($articles->isEmpty()) {
            $this->info('Tidak ada artikel yang menunggu ekstraksi fakta.');

            return self::SUCCESS;
        }

        $succeeded = 0;
        $failed = 0;

        foreach ($articles as $article) {
            try {
                $result = $aiService->extractFactsWithModel($article);

                $article->update([
                    'facts' => $result['facts'],
                    'ai_model' => $result['model'],
                    'ai_processed_at' => now(),
                    'status' => 'processing',
                    'error_message' => null,
                ]);

                $succeeded++;
                $this->line("Artikel {$article->id}: fakta berhasil diekstrak ({$result['model']}).");
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
