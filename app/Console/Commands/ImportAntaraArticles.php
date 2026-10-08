<?php

namespace App\Console\Commands;

use App\Models\Article;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class ImportAntaraArticles extends Command
{
    protected $signature = 'antara:import {date? : Tanggal berita dalam format YYYY-MM-DD} {--region= : Filter berita dengan wilayah jateng atau jogja}';

    protected $description = 'Ambil artikel ANTARA dan simpan yang belum pernah diproses ke database';

    public function handle(): int
    {
        $date = $this->argument('date') ?: now('Asia/Jakarta')->toDateString();
        $region = $this->option('region');

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $date)->toDateString();
        } catch (Throwable) {
            $this->error('Tanggal tidak valid. Gunakan format YYYY-MM-DD.');

            return self::FAILURE;
        }

        $regionConfig = null;
        if ($region !== null) {
            $regionConfig = config("services.antara_regions.{$region}");
            if (! is_array($regionConfig) || ! is_array($regionConfig['areas'] ?? null)) {
                $this->error('Wilayah tidak dikenal. Gunakan jateng atau jogja.');

                return self::FAILURE;
            }
        }

        $login = new Process(['node', 'scripts/antara-login.js'], base_path());
        $login->setTimeout(120);
        $login->run();

        if (! $login->isSuccessful()) {
            $this->error(trim($login->getErrorOutput()) ?: 'Gagal memperbarui sesi ANTARA.');

            return self::FAILURE;
        }

        $arguments = ['node', 'scripts/antara-import.js', $date];
        if ($regionConfig !== null) {
            $arguments[] = $region;
            $arguments[] = json_encode($regionConfig['areas'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        $process = new Process($arguments, base_path());
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error(trim($process->getErrorOutput()) ?: 'Scraper ANTARA gagal dijalankan.');

            return self::FAILURE;
        }

        try {
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            $this->error('Output scraper tidak valid: '.$exception->getMessage());

            return self::FAILURE;
        }

        $summary = null;
        if ($regionConfig !== null && is_array($result) && isset($result['articles']) && is_array($result['articles'])) {
            $articles = $result['articles'];
            $summary = $result;
        } else {
            $articles = $result;
        }

        if (! is_array($articles) || ($regionConfig !== null && $summary === null)) {
            $this->error('Output scraper bukan daftar artikel.');

            return self::FAILURE;
        }

        foreach ($articles as $index => $article) {
            if (! $this->isValidArticle($article)) {
                $this->error('Data artikel hasil scraping tidak valid pada item '.($index + 1).'.');

                return self::FAILURE;
            }
        }

        foreach ($articles as &$article) {
            $sourceId = (string) $article['source_id'];
            $sourceUrl = (string) $article['source_url'];
            $urlHash = hash('sha256', $sourceUrl);
            $contentHash = hash('sha256', $this->normalizeContent((string) $article['source_content']));
            $existingArticle = Article::query()
                ->where('source', 'antara')
                ->where(function ($query) use ($sourceId, $urlHash, $contentHash): void {
                    $query->where('source_id', $sourceId)
                        ->orWhere('source_url_hash', $urlHash)
                        ->orWhere('content_hash', $contentHash);
                })
                ->first();

            $hasStoredImage = $existingArticle?->image_path
                && Storage::disk('local')->exists($existingArticle->image_path);
            $article['image_path'] = $hasStoredImage
                ? $existingArticle->image_path
                : ($article['image_url']
                    ? $this->downloadImage($article['image_url'], $sourceId)
                    : null);
        }
        unset($article);

        $inserted = 0;
        $skipped = 0;

        DB::transaction(function () use ($articles, &$inserted, &$skipped): void {
            foreach ($articles as $article) {
                $sourceId = (string) ($article['source_id'] ?? '');
                $sourceUrl = (string) ($article['source_url'] ?? '');
                $content = (string) ($article['source_content'] ?? '');
                $urlHash = hash('sha256', $sourceUrl);
                $contentHash = hash('sha256', $this->normalizeContent($content));

                $existingArticle = Article::query()
                    ->where('source', 'antara')
                    ->where(function ($query) use ($sourceId, $urlHash, $contentHash): void {
                        $query->where('source_id', $sourceId)
                            ->orWhere('source_url_hash', $urlHash)
                            ->orWhere('content_hash', $contentHash);
                    })
                    ->first();

                $publishedAt = $this->publishedAt(
                    (string) ($article['source_published_at'] ?? ''),
                );

                if ($existingArticle) {
                    $missingFields = [];

                    if ($existingArticle->source_published_at === null && $publishedAt !== null) {
                        $missingFields['source_published_at'] = $publishedAt;
                    }

                    if ((! $existingArticle->image_path
                        || ! Storage::disk('local')->exists($existingArticle->image_path))
                        && ! empty($article['image_path'])) {
                        $missingFields['image_url'] = $article['image_url'];
                        $missingFields['image_path'] = $article['image_path'];
                    }

                    if ($missingFields !== []) {
                        $existingArticle->update($missingFields);
                    }

                    $skipped++;

                    continue;
                }

                Article::create([
                    'source' => 'antara',
                    'source_id' => $sourceId,
                    'source_url' => $sourceUrl,
                    'source_url_hash' => $urlHash,
                    'source_title' => (string) $article['source_title'],
                    'source_content' => $content,
                    'source_published_at' => $publishedAt,
                    'content_hash' => $contentHash,
                    'image_url' => $article['image_url'] ?? null,
                    'image_path' => $article['image_path'] ?? null,
                ]);

                $inserted++;
            }
        });

        $this->info('Artikel baru disimpan: '.$inserted);
        $this->line('Duplikat dilewati: '.$skipped);

        if ($summary !== null) {
            $this->line('Halaman daftar diperiksa: '.(int) ($summary['pages_scanned'] ?? 0));
            $this->line('Berita wilayah ditemukan: '.count($articles).' dari 10 target');
            if (count($articles) < 10) {
                $this->warn('Semua halaman daftar yang tersedia sudah diperiksa; sumber tidak menyediakan 10 berita cocok untuk wilayah ini pada tanggal ini.');
            }
        }

        return self::SUCCESS;
    }

    private function publishedAt(string $dateText): ?CarbonImmutable
    {
        if (preg_match('/\b(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})(?:\s+(\d{1,2}):(\d{2}))?\b/u', $dateText, $matches) !== 1) {
            return null;
        }

        $months = [
            'januari' => 1,
            'jan' => 1,
            'februari' => 2,
            'feb' => 2,
            'maret' => 3,
            'mar' => 3,
            'april' => 4,
            'apr' => 4,
            'mei' => 5,
            'may' => 5,
            'juni' => 6,
            'jun' => 6,
            'juli' => 7,
            'jul' => 7,
            'agustus' => 8,
            'agu' => 8,
            'agt' => 8,
            'aug' => 8,
            'september' => 9,
            'sep' => 9,
            'oktober' => 10,
            'okt' => 10,
            'oct' => 10,
            'november' => 11,
            'nov' => 11,
            'desember' => 12,
            'des' => 12,
            'dec' => 12,
        ];
        $month = $months[mb_strtolower($matches[2])] ?? null;

        if ($month === null) {
            return null;
        }

        return CarbonImmutable::createFromDate((int) $matches[3], $month, (int) $matches[1])
            ->setTime((int) ($matches[4] ?? 0), (int) ($matches[5] ?? 0));
    }

    private function isValidArticle(mixed $article): bool
    {
        if (! is_array($article)) {
            return false;
        }

        $sourceId = $article['source_id'] ?? null;
        $sourceUrl = $article['source_url'] ?? null;
        $title = $article['source_title'] ?? null;
        $content = $article['source_content'] ?? null;
        $query = [];

        if (is_string($sourceUrl)) {
            parse_str((string) parse_url($sourceUrl, PHP_URL_QUERY), $query);
        }

        if (! is_string($sourceId) || ! ctype_digit($sourceId)
            || ! is_string($sourceUrl) || filter_var($sourceUrl, FILTER_VALIDATE_URL) === false
            || parse_url($sourceUrl, PHP_URL_SCHEME) !== 'https'
            || parse_url($sourceUrl, PHP_URL_HOST) !== 'branda.antaranews.com'
            || ! str_ends_with((string) parse_url($sourceUrl, PHP_URL_PATH), '/content.php')
            || ! is_string($title) || trim($title) === ''
            || ! is_string($content) || trim($content) === ''
            || preg_match('//u', $content) !== 1
            || (isset($article['source_published_at']) && $article['source_published_at'] !== null
                && ! is_string($article['source_published_at']))
            || (isset($article['image_url']) && $article['image_url'] !== null
                && (! is_string($article['image_url'])
                    || filter_var($article['image_url'], FILTER_VALIDATE_URL) === false))) {
            return false;
        }

        return (string) ($query['id'] ?? '') === $sourceId;
    }

    private function normalizeContent(string $content): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $content));
    }

    private function downloadImage(string $url, string $sourceId): ?string
    {
        $response = Http::timeout(45)->get($url);

        if (! $response->successful() || ! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
            $this->warn("Gambar artikel sumber {$sourceId} tidak dapat diunduh; artikel tetap diimpor tanpa file gambar.");

            return null;
        }

        $mime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => null,
        };

        if ($extension === null) {
            $this->warn("Format gambar artikel sumber {$sourceId} tidak didukung.");

            return null;
        }

        $path = "antara-images/{$sourceId}.{$extension}";
        if (! Storage::disk('local')->put($path, $response->body())) {
            throw new RuntimeException("Gagal menyimpan file gambar artikel sumber {$sourceId} ke storage lokal.");
        }

        return $path;
    }
}
