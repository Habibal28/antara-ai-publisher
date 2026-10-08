<?php

namespace App\Jobs;

use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportRegionalAntaraArticles implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public string $region)
    {
        $this->onQueue('scheduled');
    }

    public function uniqueId(): string
    {
        return 'telegram-antara-region-'.$this->region;
    }

    public function handle(TelegramService $telegram): void
    {
        $label = (string) config("services.antara_regions.{$this->region}.label", $this->region);

        try {
            $exitCode = Artisan::call('antara:import', [
                '--region' => $this->region,
            ]);
            $output = trim(Artisan::output());

            if ($exitCode !== 0) {
                throw new \RuntimeException($output ?: 'Import ANTARA gagal.');
            }

            $telegram->sendMessage((string) config('services.telegram.chat_id'), "Pengambilan berita {$label} selesai.\n{$output}");
        } catch (Throwable $exception) {
            Log::error('Telegram regional ANTARA import failed.', [
                'region' => $this->region,
                'error' => $exception->getMessage(),
            ]);

            $telegram->sendMessage((string) config('services.telegram.chat_id'), "Pengambilan berita {$label} gagal: ".$exception->getMessage());
            throw $exception;
        }
    }
}
