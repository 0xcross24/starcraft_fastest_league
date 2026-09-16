<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Meilisearch\Client as Meilisearch;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $host = config('scout.meilisearch.host', env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'));
        $key  = config('scout.meilisearch.key', env('MEILISEARCH_KEY'));

        // If Meilisearch isn’t configured, don’t attempt anything
        if (!$host) {
            return;
        }

        $client = new Meilisearch($host, $key);
        $uid = 'users';                // must match your Scout index (searchableAs)
        $expectedPrimaryKey = 'id';    // your model’s unique key

        try {
            $index = $client->getIndex($uid);
        } catch (\Throwable $e) {
            // Index doesn’t exist: create with primary key
            $client->createIndex($uid, ['primaryKey' => $expectedPrimaryKey]);
            return;
        }

        // If primary key not set yet (and index is empty), set it
        try {
            if (!$index->getPrimaryKey()) {
                // This only succeeds if there are no documents
                $client->index($uid)->update(['primaryKey' => $expectedPrimaryKey]);
            }
        } catch (\Throwable $e) {
            // Silently ignore if it fails (e.g., docs exist); you can log if desired
            // \Log::warning('Meilisearch PK update skipped: '.$e->getMessage());
        }
    }
}
