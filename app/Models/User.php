<?php

namespace App\Models;

use App\Models\Stats;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'player_name',
        'email',
        'password',
        // 'role' is deliberately not mass assignable; only the owner sets it in the admin portal.
    ];

    /** Roles, lowest to highest. An owner can do everything an admin can, plus change roles. */
    public const ROLES = ['user', 'admin', 'owner'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        // The registered name is always the account's first player name.
        static::created(function (User $user) {
            $user->playerNames()->create(['name' => $user->player_name]);
        });
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'owner'], true);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /** The names this account plays under now (at most PlayerName::MAX_PER_USER). */
    public function playerNames()
    {
        return $this->hasMany(PlayerName::class)->whereNull('retired_at')->orderBy('id');
    }

    /** Including retired names, which keep their games on this account. */
    public function allPlayerNames()
    {
        return $this->hasMany(PlayerName::class)->orderBy('id');
    }

    /**
     * Make sure the account has a stats row for every format in the season.
     */
    public function ensureStats(Season $season): void
    {
        foreach (PlayerName::FORMATS as $format) {
            Stats::firstOrCreate(
                ['user_id' => $this->id, 'season_id' => $season->id, 'format' => $format],
                ['wins' => 0, 'losses' => 0, 'elo' => 1000],
            );
        }
    }

    public function primaryPlayerName(): ?PlayerName
    {
        return $this->playerNames()->where('name', $this->player_name)->first()
            ?? $this->playerNames()->first();
    }

    public function stats()
    {
        return $this->hasMany(Stats::class);
    }

    public function replay()
    {
        return $this->hasMany(Replay::class);
    }
}
