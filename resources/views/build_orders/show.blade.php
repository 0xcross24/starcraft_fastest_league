<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-8 text-gray-900 dark:text-gray-100 relative">
                    @if(session('error'))
                    <div class="mb-4 px-4 py-3 rounded bg-red-100 text-red-600 dark:bg-gray-700">{{ session('error') }}</div>
                    @endif
                    {{-- Shown on every build, not only transitions: an opener
                         otherwise has no way back to the listing. --}}
                    <nav aria-label="Breadcrumb" class="mb-4 flex flex-wrap items-center gap-2 text-sm font-nav">
                        <a href="{{ route('builds.index') }}" class="text-blue-600 dark:text-gray-300">Build Orders</a>
                        @foreach($trail as $ancestor)
                        <span class="text-gray-400 dark:text-gray-600">&rsaquo;</span>
                        <a href="{{ route('builds.show', ['id' => $ancestor->id]) }}" class="text-blue-600 dark:text-gray-300">{{ $ancestor->title }}</a>
                        @endforeach
                        <span class="text-gray-400 dark:text-gray-600">&rsaquo;</span>
                        <span class="text-gray-700 dark:text-gray-200">{{ $buildOrder->title }}</span>
                    </nav>
                    <div class="flex items-center justify-between mb-6">
                        <h1 class="text-3xl font-bold font-logo m-0">{{ $buildOrder->title }}</h1>
                        @auth
                        @if(auth()->user() && auth()->user()->role === 'admin')
                        <div class="flex gap-2">
                            <a href="{{ route('builds.edit', ['id' => $buildOrder->id]) }}" class="px-2 py-1 text-sm bg-yellow-500 text-white hover:bg-yellow-600">Edit</a>
                            <form action="{{ route('builds.destroy', ['id' => $buildOrder->id]) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this build order?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-2 py-1 text-sm bg-red-600 text-white hover:bg-red-700">Delete</button>
                            </form>
                        </div>
                        @endif
                        @endauth
                    </div>
                    @if($buildOrder->parent)
                    <a href="{{ route('builds.show', ['id' => $buildOrder->parent->id]) }}"
                       class="mb-6 flex items-center gap-3 rounded border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-4 py-3 ">
                        <svg class="w-4 h-4 text-blue-500 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                        <span class="text-sm text-gray-500 dark:text-gray-400 font-nav">Continues from</span>
                        <span class="font-semibold font-nav">{{ $buildOrder->parent->title }}</span>
                    </a>
                    @endif
                    <div class="grid grid-cols-1 md:grid-cols-1 gap-6 mb-8">
                        <div>
                            @if($buildOrder->phase)
                            <div class="mb-2"><span class="font-semibold">Phase:</span> {{ $buildOrder->phase }}</div>
                            @endif
                            <div class="mb-2"><span class="font-semibold">Race:</span> {{ $buildOrder->race }}</div>
                            <div class="mb-2"><span class="font-semibold">Matchup:</span>
                                @if(is_array($buildOrder->matchup))
                                {{ implode(' / ', $buildOrder->matchup) }}
                                @else
                                {{ $buildOrder->matchup }}
                                @endif
                            </div>
                        </div>
                        <div>
                            @if($buildOrder->youtube_embed_id)
                            <div class="mb-2"><span class="font-semibold">YouTube Video:</span></div>
                            <div class="w-full p-0 mb-2">
                                <iframe id="video" class="w-full h-[600px]" src="https://www.youtube.com/embed/{{ $buildOrder->youtube_embed_id }}" frameborder="0" allowfullscreen></iframe>
                            </div>
                            @endif
                        </div>
                    </div>
                    <div class="mb-6">
                        <div class="font-semibold mb-1 font-nav">Description:</div>
                        <div class="rounded whitespace-pre-line">{{ $buildOrder->description }}</div>
                    </div>
                    <div class="mb-6">
                        <div class="font-semibold mb-1">Steps:</div>
                        {{-- Safe to print unescaped: steps_html escapes every line before adding markup. --}}
                        {{-- Styling the generated tags here, not in the model: Tailwind scans
                             Blade files only, so classes named in app/ get dropped from the build. --}}
                        <div class="rounded space-y-3 [&_p]:whitespace-pre-line [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-1">{!! $buildOrder->steps_html !!}</div>
                    </div>
                    @if($buildOrder->transitions->isNotEmpty())
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <div class="flex items-center gap-2 mb-4">
                            <svg class="w-4 h-4 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 3v12"></path>
                                <circle cx="18" cy="6" r="3"></circle>
                                <circle cx="6" cy="18" r="3"></circle>
                                <path d="M18 9a9 9 0 0 1-9 9"></path>
                            </svg>
                            <span class="text-lg font-bold font-logo">Continues into</span>
                        </div>
                        <div class="flex flex-col gap-3">
                            @foreach($buildOrder->transitions as $transition)
                            <a href="{{ route('builds.show', ['id' => $transition->id]) }}"
                               class="flex items-center gap-4 rounded border border-l-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 px-4 py-3 ">
                                <span class="flex flex-col gap-1 flex-1 min-w-0">
                                    <span class="flex items-center gap-2">
                                        <span class="font-semibold font-nav">{{ $transition->title }}</span>
                                        @if($transition->phase)
                                        <span class="text-xs font-semibold px-3 py-1 rounded-full bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200">{{ $transition->phase }}</span>
                                        @endif
                                    </span>
                                    @if($transition->description)
                                    <span class="text-sm text-gray-600 dark:text-gray-300 font-nav">{{ Str::limit($transition->description, 80) }}</span>
                                    @endif
                                </span>
                                <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="9 18 15 12 9 6"></polyline>
                                </svg>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
