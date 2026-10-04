<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class WordPressService
{
    public function syncPostAuthor(Article $article): void
    {
        $baseUrl = rtrim((string) config('services.wordpress.url'), '/');

        if ($baseUrl === '' || blank(config('services.wordpress.username')) || blank(config('services.wordpress.application_password'))) {
            throw new RuntimeException('Konfigurasi WordPress belum lengkap.');
        }

        $writerName = trim((string) config('services.wordpress.author_name'));
        if (! $article->wordpress_post_id || $writerName === '') {
            throw new RuntimeException('Artikel harus memiliki wordpress_post_id dan WORDPRESS_AUTHOR_NAME harus diatur.');
        }

        $metaDescription = $this->metaDescription($article);

        $response = $this->client()->post($baseUrl.'/wp-json/wp/v2/posts/'.$article->wordpress_post_id, [
            'meta' => [
                'writer-value' => $writerName,
                '_yoast_wpseo_metadesc' => $metaDescription,
            ],
        ]);

        if (! $response->successful()
            || (string) $response->json('meta.writer-value') !== $writerName
            || (string) $response->json('meta._yoast_wpseo_metadesc') !== $metaDescription) {
            throw new RuntimeException('WordPress tidak menyimpan meta description atau Penulis Berita. Pastikan bridge REST terbaru sudah aktif.');
        }
    }

    public function publish(Article $article): int
    {
        $baseUrl = rtrim((string) config('services.wordpress.url'), '/');

        if ($baseUrl === '' || blank(config('services.wordpress.username')) || blank(config('services.wordpress.application_password'))) {
            throw new RuntimeException('Konfigurasi WordPress belum lengkap.');
        }

        if (blank($article->seo_focus_keyword) || blank($article->seo_category) || empty($article->seo_tags)) {
            throw new RuntimeException('Metadata SEO belum lengkap. Jalankan ulang ai:rewrite untuk artikel ini.');
        }

        $metaDescription = $this->metaDescription($article);

        $writerName = trim((string) config('services.wordpress.author_name'));
        if ($writerName === '') {
            throw new RuntimeException('WORDPRESS_AUTHOR_NAME belum diatur.');
        }

        $categoryId = $article->wordpress_category_id ?: $this->findOrCreateTerm($baseUrl, 'categories', $article->seo_category);
        $article->wordpress_category_id = $categoryId;
        $article->save();

        $tagIds = $article->wordpress_tag_ids ?: $this->resolveTags($baseUrl, $article->seo_tags);
        $article->wordpress_tag_ids = $tagIds;
        $article->save();

        $authorId = $article->wordpress_author_id ?: $this->findAuthor($baseUrl, (string) config('services.wordpress.author_name'));
        $article->wordpress_author_id = $authorId;
        $article->save();

        $mediaId = $article->wordpress_media_id ?: $this->uploadFeaturedImage($baseUrl, $article);
        $article->wordpress_media_id = $mediaId;
        $article->save();

        $postData = [
            'title' => $article->rewritten_title,
            'content' => $article->rewritten_content,
            'status' => 'draft',
            'author' => $authorId,
            'categories' => [$categoryId],
            'tags' => $tagIds,
            'featured_media' => $mediaId,
            'meta' => [
                '_yoast_wpseo_focuskw' => $article->seo_focus_keyword,
                '_yoast_wpseo_metadesc' => $metaDescription,
                'writer-value' => $writerName,
            ],
        ];

        $postUrl = $baseUrl.'/wp-json/wp/v2/posts'.($article->wordpress_post_id ? '/'.$article->wordpress_post_id : '');
        $postResponse = $this->client()->post($postUrl, $postData);

        if (! $postResponse->successful() || ! is_numeric($postResponse->json('id'))) {
            throw new RuntimeException('WordPress API gagal membuat/memperbarui draft: '.($postResponse->json('message') ?? 'respons tidak valid.'));
        }

        $postId = (int) $postResponse->json('id');
        $article->wordpress_post_id = $postId;
        $article->save();

        if ((string) $postResponse->json('meta._yoast_wpseo_focuskw') !== $article->seo_focus_keyword) {
            throw new RuntimeException('Yoast tidak menyimpan focus keyphrase. Pasang plugin bridge WordPress yang disertakan, lalu coba publikasi lagi.');
        }

        if ((string) $postResponse->json('meta._yoast_wpseo_metadesc') !== $metaDescription) {
            throw new RuntimeException('Yoast tidak menyimpan meta description. Pastikan bridge REST terbaru sudah aktif.');
        }

        if ((string) $postResponse->json('meta.writer-value') !== $writerName) {
            throw new RuntimeException('WordPress tidak menyimpan Penulis Berita. Pastikan bridge REST terbaru sudah aktif.');
        }

        $publishResponse = $this->client()->post($baseUrl.'/wp-json/wp/v2/posts/'.$postId, ['status' => 'publish']);
        if (! $publishResponse->successful() || $publishResponse->json('status') !== 'publish') {
            throw new RuntimeException('Draft tersimpan di WordPress, tetapi gagal diterbitkan: '.($publishResponse->json('message') ?? 'respons tidak valid.'));
        }

        return $postId;
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->withBasicAuth((string) config('services.wordpress.username'), (string) config('services.wordpress.application_password'))
            ->timeout(45);
    }

    private function metaDescription(Article $article): string
    {
        $description = trim((string) $article->seo_meta_description);

        if ($description === '') {
            $content = html_entity_decode(strip_tags((string) $article->rewritten_content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $content = trim((string) preg_replace('/\s+/u', ' ', $content));

            if ($content === '') {
                throw new RuntimeException('Meta description kosong dan isi rewrite tidak tersedia untuk membuat ringkasan.');
            }

            if (mb_strlen($content) > 155) {
                $excerpt = mb_substr($content, 0, 152);
                $lastSpace = mb_strrpos($excerpt, ' ');
                $description = rtrim(mb_substr($excerpt, 0, $lastSpace === false ? null : $lastSpace), ' ,.;:') . '…';
            } else {
                $description = $content;
            }

            $article->seo_meta_description = $description;
            $article->save();
        }

        return $description;
    }

    private function findOrCreateTerm(string $baseUrl, string $endpoint, string $name): int
    {
        $response = $this->client()->get($baseUrl.'/wp-json/wp/v2/'.$endpoint, ['search' => $name, 'per_page' => 100]);
        if (! $response->successful()) {
            throw new RuntimeException("WordPress gagal membaca {$endpoint}: ".($response->json('message') ?? 'respons tidak valid.'));
        }

        foreach ($response->json() as $term) {
            if (mb_strtolower((string) ($term['name'] ?? '')) === mb_strtolower($name) && is_numeric($term['id'] ?? null)) {
                return (int) $term['id'];
            }
        }

        $created = $this->client()->post($baseUrl.'/wp-json/wp/v2/'.$endpoint, ['name' => $name]);
        if (! $created->successful() || ! is_numeric($created->json('id'))) {
            throw new RuntimeException("WordPress gagal membuat {$endpoint} '{$name}': ".($created->json('message') ?? 'respons tidak valid.'));
        }

        return (int) $created->json('id');
    }

    /** @param array<int, string> $tags
     *  @return array<int, int>
     */
    private function resolveTags(string $baseUrl, array $tags): array
    {
        $ids = [];
        foreach ($tags as $tag) {
            $ids[] = $this->findOrCreateTerm($baseUrl, 'tags', $tag);
        }

        return array_values(array_unique($ids));
    }

    private function findAuthor(string $baseUrl, string $name): int
    {
        $configuredId = config('services.wordpress.author_id');
        if (is_numeric($configuredId) && (int) $configuredId > 0) {
            return (int) $configuredId;
        }

        if ($name === '') {
            throw new RuntimeException('WORDPRESS_AUTHOR_NAME belum diatur.');
        }

        $response = $this->client()->get($baseUrl.'/wp-json/wp/v2/users', ['search' => $name, 'per_page' => 100]);
        if (! $response->successful()) {
            throw new RuntimeException('WordPress gagal mencari author. Pastikan akun API boleh menetapkan author atau isi WORDPRESS_AUTHOR_ID.');
        }

        foreach ($response->json() as $user) {
            if (mb_strtolower((string) ($user['name'] ?? '')) === mb_strtolower($name) && is_numeric($user['id'] ?? null)) {
                return (int) $user['id'];
            }
        }

        throw new RuntimeException("Author WordPress '{$name}' tidak ditemukan. Pastikan akun tersebut ada atau atur WORDPRESS_AUTHOR_ID.");
    }

    private function uploadFeaturedImage(string $baseUrl, Article $article): int
    {
        if (blank($article->image_path) || ! Storage::disk('local')->exists($article->image_path)) {
            throw new RuntimeException('File gambar lokal artikel tidak ditemukan. Jalankan ulang import ANTARA untuk mengunduh gambar.');
        }

        $imageBody = Storage::disk('local')->get($article->image_path);
        $mimeType = Storage::disk('local')->mimeType($article->image_path) ?: 'image/jpeg';
        $extension = pathinfo($article->image_path, PATHINFO_EXTENSION) ?: 'jpg';
        $filename = 'antara-'.$article->id.'.'.$extension;
        $uploaded = $this->client()
            ->withHeaders([
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Content-Type' => $mimeType,
            ])
            ->withBody($imageBody, $mimeType)
            ->post($baseUrl.'/wp-json/wp/v2/media');

        if (! $uploaded->successful() || ! is_numeric($uploaded->json('id'))) {
            throw new RuntimeException('WordPress gagal mengunggah featured image: '.($uploaded->json('message') ?? 'respons tidak valid.'));
        }

        return (int) $uploaded->json('id');
    }
}
