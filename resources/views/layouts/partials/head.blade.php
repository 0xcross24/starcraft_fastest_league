@php
    /*
     * Shared <head> for the app, guest and auth layouts. Pages pass title,
     * description and noindex through the layout component; everything has
     * a fallback so a page that sets nothing still gets sane tags.
     */
    $siteName = config('app.name', 'SFL');
    $fullTitle = $title ? $title.' | '.$siteName : $siteName.' - StarCraft Fastest League';
    $metaDescription = $description
        ?: 'StarCraft: Brood War Fastest team ladder for 2v2 and 3v3: season rankings, uploaded replays and build orders.';

    // Login, account, admin and per-game pages carry no written content, and
    // any query string (season, format, race, date, page filters) is a
    // templated variant of a page that is indexed without it.
    $noindex = $noindex
        || request()->getQueryString() !== null
        || request()->routeIs(
            'games.show', 'login', 'register', 'password.*', 'verification.*',
            'profile.*', 'upload.*', 'admin.*', 'players.search', 'builds.create', 'builds.edit',
        );
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $metaDescription }}">
@if($noindex)
<meta name="robots" content="noindex, follow">
@else
<link rel="canonical" href="{{ url()->current() }}">
@endif
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $fullTitle }}">
<meta property="og:description" content="{{ $metaDescription }}">
<meta property="og:url" content="{{ url()->current() }}">

<!-- Adsense -->
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1266476407086877"
    crossorigin="anonymous"></script>

@if($analyticsId = config('services.google_analytics.id'))
<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $analyticsId }}"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', @json($analyticsId));
</script>
@endif

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/flowbite@2.5.2/dist/flowbite.min.css" rel="stylesheet" />

<!-- Scripts -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
