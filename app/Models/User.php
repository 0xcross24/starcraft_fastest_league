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
        'role',
    ];

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

    public function playerNames()
    {
        return $this->hasMany(PlayerName::class)->orderBy('id');
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
