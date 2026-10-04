<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Throwable;

class SendTelegramDraft extends Command
{
    protected $signature = 'telegram:send-draft {article? : ID artikel draft yang akan dikirim}';

    protected $description = 'Kirim satu draft artikel ke Telegram untuk approval';

    public function handle(TelegramService $telegram): int
    {
        $articleId = $this->argument('article');
        $article = $articleId
            ? Article::query()->whereKey($articleId)->where('status', 'drafted')->first()
            : Article::query()->where('status', 'drafted')->orderBy('id')->first();

        if (! $article) {
            $this->info('Tidak ada draft yang menunggu pengiriman.');

            return self::SUCCESS;
        }

        try {
            $messageId = $telegram->sendDraft($article);
            $article->update([
                'telegram_message_id' => $messageId,
                'status' => 'waiting_approval',
                'error_message' => null,
            ]);
            $this->info("Artikel {$article->id} dikirim ke Telegram dan menunggu approval.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $article->update(['error_message' => $exception->getMessage()]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
