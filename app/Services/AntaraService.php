<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AntaraService
{
    public function getArticle(string $url): array
    {
        $response = Http::timeout(30)
            ->get($url);

        if ($response->failed()) {
            throw new \Exception(
                "Gagal mengambil artikel. HTTP {$response->status()}"
            );
        }

        return [
            'url' => $url,
            'html' => $response->body(),
        ];
    }
}