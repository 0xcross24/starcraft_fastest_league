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
                        <a href="{{ route('builds.index', ['race' => 'Pub']) }}" class="px-3 py-1 rounded {{ (isset($race) && $race === 'Pub') ? 'bg-blue-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100' }}">Pub</a>
                    </div>
                    @if($builds->isEmpty())
                    <p>No build orders found.</p>
                    @else
                    <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-2 py-2 w-10"><span class="sr-only">Transitions</span></th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wider font-nav">Title</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wider font-nav">Subtitle</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wider font-nav">Race</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wider font-nav">Matchup</th>
                                </tr>
                            </thead>
                            {{-- One tbody per opener so Alpine can toggle a group's rows together. --}}
                            @foreach($builds as $build)
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700" x-data="{ open: false }">
                                @php $showUrl = route('builds.show', ['id' => $build->id]); @endphp
                                <tr class="hover:bg-gray-800 hover:text-white dark:hover:bg-gray-900 dark:hover:text-white transition-colors cursor-pointer" onclick="window.location='{{ $showUrl }}'">
                                    <td class="px-2 py-3 align-middle">
                                        @if($build->transitions->isNotEmpty())
                                        {{-- @click.stop, or expanding would follow the row's link instead.
                                             Two icons rather than a rotate: the rotate utility is not in the
                                             compiled stylesheet, which is committed and not rebuilt on deploy. --}}
                                        <button type="button" x-on:click.stop="open = !open"
                                                :aria-expanded="open ? 'true' : 'false'"
                                                aria-label="Show transitions from {{ $build->title }}"
                                                class="w-8 h-8 inline-flex items-center justify-center rounded border border-gray-300 dark:border-gray-600">
                                            <svg x-show="!open" class="w-4 h-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="9 18 15 12 9 6"></polyline>
                                            </svg>
                                            <svg x-show="open" style="display: none" class="w-4 h-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="6 9 12 15 18 9"></polyline>
                                            </svg>
                                        </button>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-semibold font-nav">
                                        <span class="inline-flex items-center gap-2">
                                            {{ $build->title }}
                                            @if($build->transitions->isNotEmpty())
                                            <span class="text-xs font-semibold px-3 py-1 rounded-full bg-blue-600 text-white">{{ $build->transitions->count() }}</span>
                                            @endif
                                        </span>
                                    </td>
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
                                @foreach($build->transitions as $transition)
                                @php $transitionUrl = route('builds.show', ['id' => $transition->id]); @endphp
                                {{-- Inline display:none hides the row before Alpine boots; x-show clears it. --}}
                                <tr x-show="open" style="display: none"
                                    class="bg-gray-50 dark:bg-gray-900 hover:bg-gray-800 hover:text-white dark:hover:bg-gray-900 dark:hover:text-white transition-colors cursor-pointer"
                                    onclick="window.location='{{ $transitionUrl }}'">
                                    <td class="px-2 py-2"></td>
                                    <td class="px-4 py-2 font-nav">
                                        <span class="border-l-2 border-gray-300 dark:border-gray-600">
                                            <span class="ml-4 inline-flex items-center gap-2">
                                                <svg class="w-3 h-3 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="15 10 20 15 15 20"></polyline>
                                                    <path d="M4 4v7a4 4 0 0 0 4 4h12"></path>
                                                </svg>
                                                <span class="text-gray-900 dark:text-gray-100">{{ $transition->title }}</span>
                                                @if($transition->phase)
                                                <span class="text-xs font-semibold px-3 py-1 rounded-full bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200">{{ $transition->phase }}</span>
                                                @endif
                                            </span>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 font-nav">{{ Str::limit($transition->description, 60) }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 font-nav">{{ $transition->race }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 font-nav">
                                        @if(is_array($transition->matchup))
                                        {{ implode(' / ', $transition->matchup) }}
                                        @else
                                        {{ $transition->matchup }}
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            @endforeach
                        </table>
                    </div>
                    <div class="mt-6">{{ $builds->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
