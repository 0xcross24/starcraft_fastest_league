<?php

namespace App\Http\Controllers;

use App\Models\BuildOrder;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Only pages with written content. Game pages, player profiles and
     * filtered lists are templated data and stay out (they are noindexed
     * too), so crawlers spend their time on the pages worth reading.
     */
    public function index(): Response
    {
        $urls = collect([
            route('homepage'),
            route('about'),
            route('rules'),
            route('builds.index'),
            route('rankings'),
            route('contact'),
            route('privacy'),
        ])->map(fn($loc) => ['loc' => $loc, 'lastmod' => null]);

        $builds = BuildOrder::query()->orderBy('id')->get(['id', 'updated_at'])
            ->map(fn($build) => [
                'loc' => route('builds.show', ['id' => $build->id]),
                'lastmod' => $build->updated_at?->toDateString(),
            ]);

        return response()
            ->view('sitemap', ['urls' => $urls->concat($builds)])
            ->header('Content-Type', 'application/xml');
    }
}
