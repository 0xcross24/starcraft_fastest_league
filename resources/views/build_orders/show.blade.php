<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-8 text-gray-900 dark:text-gray-100 relative">
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
                    <div class="grid grid-cols-1 md:grid-cols-1 gap-6 mb-8">
                        <div>
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
                            @if($buildOrder->youtube_url)
                            <div class="mb-2"><span class="font-semibold">YouTube Video:</span></div>
                            <div class="w-full p-0 mb-2">
                                <iframe id="video" class="w-full h-[600px]" src="https://www.youtube.com/embed/{{ $buildOrder->youtube_url }}" frameborder="0" allowfullscreen></iframe>
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
                        <span class="rounded whitespace-pre-line">{{ $buildOrder->steps }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
