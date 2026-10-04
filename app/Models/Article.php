<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = [
        'source',
        'source_id',
        'source_url',
        'source_url_hash',
        'source_title',
        'source_content',
        'source_published_at',
        'image_url',
        'image_path',
        'content_hash',
        'facts',
        'rewritten_title',
        'rewritten_content',
        'seo_focus_keyword',
        'seo_tags',
        'seo_category',
        'ai_model',
        'ai_processed_at',
        'status',
        'telegram_message_id',
        'wordpress_post_id',
        'wordpress_category_id',
        'wordpress_tag_ids',
        'wordpress_author_id',
        'wordpress_media_id',
        'error_message',
        'approved_at',
        'published_at',
    ];

    protected $casts = [
        'facts' => 'array',
        'seo_tags' => 'array',
        'wordpress_tag_ids' => 'array',
        'source_published_at' => 'datetime',
        'ai_processed_at' => 'datetime',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
    ];
}
