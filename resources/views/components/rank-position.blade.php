@props(['position'])

<span {{ $attributes->merge(['class' => 'font-bold']) }}>{{ $position }}</span>
