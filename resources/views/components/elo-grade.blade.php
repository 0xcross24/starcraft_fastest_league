@props(['elo' => 0])

@php
    $grade = app(\App\Services\EloService::class)->getEloGrade($elo);

    /*
     * The badge is an image (public/images/badges, made by
     * docs/discord-badges/make_badges.py --crop) so it looks the same on
     * every device: as text it depended on each phone's fonts.
     */
    $file = 'sfl_'.$grade[0].(str_ends_with($grade, '+') ? '_plus' : (str_ends_with($grade, '-') ? '_minus' : '')).'.png';
@endphp

<img src="{{ asset('images/badges/'.$file) }}" alt="{{ $grade }}" title="{{ $grade }}" width="39" height="24"
     {{ $attributes->merge(['class' => 'inline-block align-middle h-6 w-auto']) }}>
