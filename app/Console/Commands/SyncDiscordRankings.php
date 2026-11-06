<?php

namespace App\Console\Commands;

use App\Services\DiscordRankings;
use Illuminate\Console\Command;

class SyncDiscordRankings extends Command
{
    protected $signature = 'rankings:discord
                            {--force : Edit the message even if the standings did not change}
                            {--preview : Print the message without sending anything}';
    protected $description = 'Post or update the live rankings message in Discord.';

    public function handle(DiscordRankings $rankings): int
    {
        if ($this->option('preview')) {
            $mode = DiscordRankings::mode() ?? 'webhook';
            $payload = $rankings->payload($rankings->standings(), $mode, DiscordRankings::FORMATS[0], now()->getTimestamp());
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->info("Preview only ({$mode}): nothing was sent.");

            return self::SUCCESS;
        }

        $status = $rankings->sync((bool) $this->option('force'));
        $this->line("Discord rankings: {$status}");

        return $status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
