<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();

            // =========================
            // SOURCE ARTICLE
            // =========================
            $table->string('source', 50);
            $table->string('source_id')->nullable();
            $table->text('source_url');
            $table->string('source_url_hash', 64)->nullable();
            $table->string('source_title');
            $table->longText('source_content');
            $table->timestamp('source_published_at')->nullable();

            // =========================
            // DEDUPLICATION
            // =========================
            $table->string('content_hash', 64)->nullable();

            // =========================
            // AI PROCESSING
            // =========================
            $table->json('facts')->nullable();
            $table->string('rewritten_title')->nullable();
            $table->longText('rewritten_content')->nullable();
            $table->string('ai_model')->nullable();
            $table->timestamp('ai_processed_at')->nullable();

            // =========================
            // WORKFLOW
            // =========================
            $table->string('status', 30)->default('pending');

            // =========================
            // TELEGRAM
            // =========================
            $table->string('telegram_message_id')->nullable();

            // =========================
            // WORDPRESS
            // =========================
            $table->unsignedBigInteger('wordpress_post_id')->nullable();

            // =========================
            // ERROR HANDLING
            // =========================
            $table->text('error_message')->nullable();

            // =========================
            // APPROVAL & PUBLISHING
            // =========================
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            // =========================
            // UNIQUE CONSTRAINTS
            // =========================

            // Mencegah artikel dari source yang sama
            // dengan source ID yang sama masuk dua kali.
            $table->unique(['source', 'source_id']);

            // URL asli disimpan sebagai TEXT,
            // sehingga yang di-index adalah hash-nya.
            $table->unique(['source', 'source_url_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};