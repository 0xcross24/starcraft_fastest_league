<?php

namespace App\Http\Controllers;

use App\Services\DiscordRankings;
use Illuminate\Http\Request;

/**
 * Interactions Endpoint URL of the SFL Rankings Discord app: the 2v2 / 3v3
 * buttons on the live rankings message. Every request must carry Discord's
 * Ed25519 signature (checked with DISCORD_RANKINGS_PUBLIC_KEY).
 */
class DiscordInteractionController extends Controller
{
    private const PING = 1;
    private const COMPONENT = 3;
    private const PONG = 1;
    private const MESSAGE = 4;
    private const UPDATE_MESSAGE = 7;

    public function __invoke(Request $request, DiscordRankings $rankings)
    {
        if (!$this->signed($request)) {
            return response('invalid request signature', 401);
        }

        $interaction = $request->json()->all();
        $type = $interaction['type'] ?? null;

        if ($type === self::PING) {
            return response()->json(['type' => self::PONG]);
        }

        if ($type === self::COMPONENT
            && preg_match('/^rankings:(2v2|3v3)$/', $interaction['data']['custom_id'] ?? '', $m)) {
            return response()->json([
                'type' => self::UPDATE_MESSAGE,
                'data' => $rankings->show($m[1], $interaction['message']['id'] ?? null),
            ]);
        }

        return response()->json(['type' => self::MESSAGE, 'data' => ['content' => 'Unknown action.', 'flags' => 64]]);
    }

    private function signed(Request $request): bool
    {
        $key = (string) config('services.discord.rankings_public_key');
        $signature = (string) $request->header('X-Signature-Ed25519');
        $timestamp = (string) $request->header('X-Signature-Timestamp');

        if (strlen($key) !== 64 || !ctype_xdigit($key) || strlen($signature) !== 128 || !ctype_xdigit($signature) || $timestamp === '') {
            return false;
        }

        return sodium_crypto_sign_verify_detached(hex2bin($signature), $timestamp . $request->getContent(), hex2bin($key));
    }
}
