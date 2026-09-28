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
        'content_hash',
        'facts',
        'rewritten_title',
        'rewritten_content',
        'ai_model',
        'ai_processed_at',
        'status',
        'telegram_message_id',
        'wordpress_post_id',
        'error_message',
        'approved_at',
        'published_at',
    ];

    protected $casts = [
        'facts' => 'array',
        'source_published_at' => 'datetime',
        'ai_processed_at' => 'datetime',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
    ];
}