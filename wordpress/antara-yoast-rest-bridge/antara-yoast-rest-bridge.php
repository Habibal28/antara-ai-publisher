<?php
/**
 * Plugin Name: ANTARA AI Publisher - Yoast REST Bridge
 * Description: Exposes Yoast focus keyphrase on WordPress post REST endpoints.
 * Version: 1.1.0
 */

add_action('init', static function (): void {
    register_post_meta('post', '_yoast_wpseo_focuskw', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback' => static function (bool $allowed, string $metaKey, int $postId): bool {
            return current_user_can('edit_post', $postId)
                && current_user_can('wpseo_edit_advanced_metadata');
        },
    ]);

    register_post_meta('post', 'writer-value', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback' => static function (bool $allowed, string $metaKey, int $postId): bool {
            return current_user_can('edit_post', $postId);
        },
    ]);
}, 20);
