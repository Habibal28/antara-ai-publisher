<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;
use Throwable;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:set-webhook {url? : URL publik endpoint webhook}';

    protected $description = 'Daftarkan endpoint callback approval pada Telegram';

    public function handle(TelegramService $telegram): int
    {
        $url = $this->argument('url') ?: rtrim((string) config('app.url'), '/').'/telegram/webhook';
        if (! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            $this->error('Webhook harus menggunakan URL HTTPS yang dapat diakses Telegram.');

            return self::FAILURE;
        }

        try {
            $telegram->setWebhook($url);
            $this->info('Webhook Telegram berhasil didaftarkan.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
