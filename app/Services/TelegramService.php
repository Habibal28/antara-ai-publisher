<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TelegramService
{
    public function setWebhook(string $url): void
    {
        $secret = config('services.telegram.webhook_secret');
        if (! is_string($secret) || strlen($secret) < 1 || strlen($secret) > 256
            || ! preg_match('/^[A-Za-z0-9_-]+$/', $secret)) {
            throw new RuntimeException('TELEGRAM_WEBHOOK_SECRET harus 1-256 karakter alfanumerik, _ atau -.');
        }

        $this->request('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['callback_query'],
        ]);
    }

    public function sendDraft(Article $article): string
    {
        if (blank($article->rewritten_title) || blank($article->rewritten_content)) {
            throw new RuntimeException('Draft artikel belum lengkap.');
        }

        $seoSummary = "\n\nSEO\nFocus keyword: ".($article->seo_focus_keyword ?: 'belum ada')
            ."\nKategori: ".($article->seo_category ?: 'belum ada')
            ."\nTags: ".(is_array($article->seo_tags) ? implode(', ', $article->seo_tags) : 'belum ada');
        $articleText = $article->rewritten_title."\n\n".$article->rewritten_content;
        $text = $articleText.$seoSummary;
        if (mb_strlen($text) > 3900) {
            $maxArticleLength = 3900 - mb_strlen($seoSummary) - 3;
            $text = mb_substr($articleText, 0, $maxArticleLength).'...'.$seoSummary;
        }

        $keyboard = [
            'inline_keyboard' => [[
                ['text' => 'Approve', 'callback_data' => 'approve:'.$article->id],
                ['text' => 'Reject', 'callback_data' => 'reject:'.$article->id],
            ]],
        ];

        if (filled($article->image_path) && Storage::disk('local')->exists($article->image_path)) {
            $caption = mb_substr($article->rewritten_title."\n\n".$seoSummary, 0, 1024);
            $this->sendPhotoFile($article->image_path, $caption);
        } elseif (filled($article->image_url)) {
            $caption = mb_substr($article->rewritten_title."\n\n".$seoSummary, 0, 1024);
            $this->sendPhoto($article->image_url, $caption);
        }

        $response = $this->request('sendMessage', [
            'chat_id' => $this->chatId(),
            'text' => $text,
            'reply_markup' => $keyboard,
        ]);

        $messageId = data_get($response, 'result.message_id');
        if (! is_int($messageId) && ! is_string($messageId)) {
            throw new RuntimeException('Telegram tidak mengembalikan message_id.');
        }

        return (string) $messageId;
    }

    public function processUpdate(array $update): void
    {
        $callback = $update['callback_query'] ?? null;
        if (! is_array($callback)) {
            return;
        }

        $callbackId = $callback['id'] ?? null;
        $data = $callback['data'] ?? '';
        $chatId = data_get($callback, 'message.chat.id');
        if ((string) $chatId !== $this->chatId()) {
            if (is_string($callbackId)) {
                $this->answerCallback($callbackId, 'Chat tidak diizinkan.');
            }

            return;
        }

        if (! is_string($data) || ! preg_match('/^(approve|reject):(\d+)$/', $data, $matches)) {
            return;
        }

        $article = Article::query()->find((int) $matches[2]);
        if (! $article || $article->status !== 'waiting_approval') {
            if (is_string($callbackId)) {
                $this->answerCallback($callbackId, 'Artikel sudah diproses atau tidak ditemukan.');
            }

            return;
        }

        $approved = $matches[1] === 'approve';
        $updated = Article::query()
            ->whereKey($article->id)
            ->where('status', 'waiting_approval')
            ->update([
                'status' => $approved ? 'approved' : 'rejected',
                'approved_at' => $approved ? now() : null,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            if (is_string($callbackId)) {
                $this->answerCallback($callbackId, 'Artikel sudah diproses.');
            }

            return;
        }

        if (is_string($callbackId)) {
            $this->answerCallback($callbackId, $approved ? 'Artikel disetujui.' : 'Artikel ditolak.');
        }

        $messageId = data_get($callback, 'message.message_id');
        if ($messageId !== null) {
            try {
                $this->request('editMessageReplyMarkup', [
                    'chat_id' => $this->chatId(),
                    'message_id' => $messageId,
                    'reply_markup' => ['inline_keyboard' => []],
                ]);
            } catch (RuntimeException $exception) {
                Log::warning('Gagal menghapus tombol approval Telegram.', ['error' => $exception->getMessage()]);
            }
        }
    }

    private function answerCallback(string $callbackId, string $text): void
    {
        $this->request('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => $text,
        ]);
    }

    private function sendPhoto(string $url, string $caption): void
    {
        try {
            $image = Http::timeout(45)->get($url);
        } catch (ConnectionException) {
            throw new RuntimeException('Tidak dapat mengunduh gambar sumber untuk Telegram.');
        }

        if (! $image->successful() || ! str_starts_with((string) $image->header('Content-Type'), 'image/')) {
            throw new RuntimeException('Gambar sumber gagal diunduh atau responsnya bukan gambar.');
        }

        $token = config('services.telegram.bot_token');
        if (! is_string($token) || trim($token) === '') {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN belum diatur di file .env.');
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $filename = basename($path) ?: 'antara-image.jpg';
        try {
            $response = Http::acceptJson()->timeout(45)
                ->attach('photo', $image->body(), $filename)
                ->post("https://api.telegram.org/bot{$token}/sendPhoto", [
                    'chat_id' => $this->chatId(),
                    'caption' => $caption,
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Tidak dapat terhubung ke Telegram API. Periksa koneksi server.');
        }

        if ($response->failed() || $response->json('ok') !== true) {
            throw new RuntimeException('Telegram gagal mengunggah gambar: '.($response->json('description') ?? 'respons tidak valid.'));
        }
    }

    private function sendPhotoFile(string $path, string $caption): void
    {
        $token = config('services.telegram.bot_token');
        if (! is_string($token) || trim($token) === '') {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN belum diatur di file .env.');
        }

        $disk = Storage::disk('local');
        $filename = basename($path) ?: 'antara-image.jpg';
        $mimeType = $disk->mimeType($path) ?: 'image/jpeg';

        try {
            $response = Http::acceptJson()->timeout(45)
                ->attach('photo', $disk->get($path), $filename, ['Content-Type' => $mimeType])
                ->post("https://api.telegram.org/bot{$token}/sendPhoto", [
                    'chat_id' => $this->chatId(),
                    'caption' => $caption,
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Tidak dapat terhubung ke Telegram API. Periksa koneksi server.');
        }

        if ($response->failed() || $response->json('ok') !== true) {
            throw new RuntimeException('Telegram gagal mengunggah gambar: '.($response->json('description') ?? 'respons tidak valid.'));
        }
    }

    private function request(string $method, array $payload): array
    {
        $token = config('services.telegram.bot_token');
        if (! is_string($token) || trim($token) === '') {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN belum diatur di file .env.');
        }

        try {
            $response = Http::acceptJson()->timeout(20)->post("https://api.telegram.org/bot{$token}/{$method}", $payload);
        } catch (ConnectionException) {
            throw new RuntimeException('Tidak dapat terhubung ke Telegram API. Periksa koneksi server.');
        }
        if ($response->failed() || $response->json('ok') !== true) {
            throw new RuntimeException('Telegram API gagal: '.($response->json('description') ?? 'respons tidak valid.'));
        }

        return $response->json();
    }

    private function chatId(): string
    {
        $chatId = config('services.telegram.chat_id');
        if (! is_string($chatId) || trim($chatId) === '') {
            throw new RuntimeException('TELEGRAM_CHAT_ID belum diatur di file .env.');
        }

        return $chatId;
    }
}
