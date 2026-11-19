@props(['position'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center justify-center h-6 min-w-[1.75rem] px-1.5 pt-[3px] rounded bg-gray-700 text-gray-100 font-mono font-bold leading-none']) }}>{{ $position }}</span>
