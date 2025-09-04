<x-app-layout>
    <div class="py-12">
        <div class="max-w-screen-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-bold font-logo">Build Orders</h1>
                        @auth
                        @if(auth()->user() && auth()->user()->role === 'admin')
                        <a href="{{ route('builds.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">New</a>
                        @endif
                        @endauth
                    </div>
                    @if(session('success'))
                    <div class="mb-4 text-green-600">{{ session('success') }}</div>
                    @endif
                    <div class="flex gap-2 mb-4">
                        <a href="{{ route('builds.index') }}" class="px-3 py-1 rounded {{ empty($race) ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100' }}">All</a>
                        <a href="{{ route('builds.index', ['race' => 'Protoss']) }}" class="px-3 py-1 rounded {{ (isset($race) && $race === 'Protoss') ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100' }}">Protoss</a>
                        <a href="{{ route('builds.index', ['race' => 'Terran']) }}" class="px-3 py-1 rounded {{ (isset($race) && $race === 'Terran') ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100' }}">Terran</a>
                        <a href="{{ route('builds.index', ['race' => 'Zerg']) }}" class="px-3 py-1 rounded {{ (isset($race) && $race === 'Zerg') ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100' }}">Zerg</a>
                    </div>
                    @if($builds->isEmpty())
                    <p>No build orders found.</p>
                    @else
                    <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider font-nav">Title</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider font-nav">Subtitle</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider font-nav">Race</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider font-nav">Matchup</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($builds as $build)
                                @php $showUrl = route('builds.show', ['id' => $build->id]); @endphp
                                <tr class="hover:bg-gray-800 hover:text-white dark:hover:bg-gray-900 dark:hover:text-white transition-colors cursor-pointer" onclick="window.location='{{ $showUrl }}'">
                                    <td class="px-4 py-3 font-semibold text-blue-700 dark:text-blue-300 underline font-nav">{{ $build->title }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200 font-nav">{{ Str::limit($build->description, 60) }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200 font-nav">{{ $build->race }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200 font-nav">
                                        @if(is_array($build->matchup))
                                        {{ implode(' / ', $build->matchup) }}
                                        @else
                                        {{ $build->matchup }}
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-6">{{ $builds->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
