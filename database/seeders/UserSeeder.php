<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    
    protected $users = [
        ['player_name' => '[SFL]Cross-',      'email' => 'Cross@email.com'],
        ['player_name' => '[SFL]Bigwan',      'email' => 'Bigwan@example.com'],
        ['player_name' => '[SFL]Dizzy',       'email' => 'Dizzy@example.com'],
        ['player_name' => '[SFL]gbomb',       'email' => 'gbomb@example.com'],
        ['player_name' => '[SFL]nightstand',  'email' => 'nightstand@example.com'],
        ['player_name' => 'Bidulgi',          'email' => 'Bidulgi@example.com'],
        ['player_name' => 'BL1ZZ',            'email' => 'BL1ZZ@example.com'],
        ['player_name' => '[SFL]Shrink',      'email' => 'Shrink@example.com'],
        ['player_name' => 'Vince88',          'email' => 'Vince88@example.com'],
        ['player_name' => 'flowcc',           'email' => 'flowcc@example.com'],
        ['player_name' => '[SFL]tb`Hero-',    'email' => 'tb1s@example.com'],
        ['player_name' => '[SFL]Banging',     'email' => 'Banging@example.com'],
        ['player_name' => '[SFL]Health',      'email' => 'Health@example.com'],
        ['player_name' => '[SFL]fil0',        'email' => 'fil0@example.com'],
        // Add more specific users as needed
    ];

    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Create specific users with custom data
        foreach ( $this->users as $user ) {
            User::create([
              'player_name'       => $user['player_name'],
              'email'             => $user['email'],
              'email_verified_at' => now(),
              'password'          => Hash::make('password456'),
              'remember_token'    => \Illuminate\Support\Str::random(10),
              'created_at'        => now(),
              'updated_at'        => now(),
            ]);
        }     
    }
}
