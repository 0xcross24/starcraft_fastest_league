<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Create specific users with custom data
    
        User::create([
            'player_name' => 'tb1s',
            'email' => 'jane@example.com',
            'password' => Hash::make('password456'),
        ]);
    
        User::create([
            'player_name' => 'bigwan3000',
            'email' => 'uniq1@example.com',
            'password' => Hash::make('password789'),
        ]);

        User::create([
            'player_name' => 'BidulgiAvenger',
            'email' => 'uniq2@example.com',
            'password' => Hash::make('password789'),
        ]);

        User::create([
            'player_name' => 'AnthZ',
            'email' => 'uniq3@example.com',
            'password' => Hash::make('password789'),
        ]);
    
        // Add more specific users as needed
    }
}
