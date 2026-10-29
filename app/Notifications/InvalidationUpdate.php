<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emailed to the players in a game when someone asks to invalidate it, when
 * the discussion gets a new message, and when it is decided.
 */
class InvalidationUpdate extends Notification
{
    public function __construct(
        public string $replayId,
        public string $event,
        public ?string $authorName,
        public ?string $body,
        public ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $game = substr($this->replayId, 0, 8);
        $who = $this->authorName ?? 'Someone';

        [$subject, $line] = match ($this->event) {
            'opened' => ["Invalidation requested for game $game", "$who asked the admins to invalidate game $game ({$this->reason}). If you agree, say so; if you don't, dispute it."],
            'agree' => ["$who agreed to invalidate game $game", "$who agreed that game $game should be invalidated."],
            'dispute' => ["$who disputed invalidating game $game", "$who disputed the request to invalidate game $game. An admin will decide."],
            'admin_reply' => ["Admin reply on game $game", "$who (admin) replied about game $game."],
            'approved' => ["Game $game was invalidated", "Game $game was invalidated. The ELO and wins/losses it gave every player were reversed."],
            'rejected' => ["Invalidation of game $game was rejected", "The request to invalidate game $game was rejected. The game still counts."],
            default => ["New comment on game $game", "$who commented on the request to invalidate game $game."],
        };

        $mail = (new MailMessage)->subject("SFL: $subject")->line($line);
        if ($this->body) {
            $mail->line('"' . $this->body . '"');
        }

        return $mail->action('Open the game', route('games.show', $this->replayId));
    }
}
