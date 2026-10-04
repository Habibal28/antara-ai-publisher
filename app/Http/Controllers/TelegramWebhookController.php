<?php

namespace App\Http\Controllers;

use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramService $telegram): JsonResponse
    {
        $secret = config('services.telegram.webhook_secret');
        if (! is_string($secret) || $secret === ''
            || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            return response()->json(['ok' => false], 403);
        }

        $telegram->processUpdate($request->all());

        return response()->json(['ok' => true]);
    }
}
