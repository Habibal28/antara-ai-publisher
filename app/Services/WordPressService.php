<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WordPressService
{
    public function publish(Article $article): int
    {
        $url = rtrim((string) config('services.wordpress.url'), '/');
        $username = (string) config('services.wordpress.username');
        $password = (string) config('services.wordpress.application_password');

        if ($url === '' || $username === '' || $password === '') {
            throw new RuntimeException('Konfigurasi WordPress belum lengkap.');
        }

        $response = Http::acceptJson()
            ->withBasicAuth($username, $password)
            ->timeout(30)
            ->post($url.'/wp-json/wp/v2/posts', [
                'title' => $article->rewritten_title,
                'content' => $article->rewritten_content,
                'status' => 'publish',
            ]);

        if (! $response->successful() || ! is_numeric($response->json('id'))) {
            $message = $response->json('message') ?? 'respons tidak valid.';
            throw new RuntimeException('WordPress API gagal: '.$message);
        }

        return (int) $response->json('id');
    }
}
