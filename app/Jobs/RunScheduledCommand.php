<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunScheduledCommand implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public string $commandName)
    {
        $this->onQueue('scheduled');
    }

    public function uniqueId(): string
    {
        return $this->commandName;
    }

    public function handle(): void
    {
        $exitCode = Artisan::call($this->commandName);

        if ($exitCode !== 0) {
            Log::error('Scheduled workflow command returned a failure status.', [
                'command' => $this->commandName,
                'exit_code' => $exitCode,
            ]);
        }
    }
}
