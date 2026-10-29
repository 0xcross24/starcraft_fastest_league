<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                @include('admin.partials.nav')

                <form method="get" class="flex flex-wrap gap-2 mb-4">
                    <x-text-input name="q" type="search" :value="$search" placeholder="Search username or email" class="flex-1 min-w-0" />
                    <x-primary-button>Search</x-primary-button>
                    @if($search !== '')
                    <a href="{{ route('admin.users.index') }}" class="self-center text-sm text-gray-400 hover:underline">Clear</a>
                    @endif
                </form>

                <p class="text-sm text-gray-400 mb-2">{{ $users->total() }} {{ Str::plural('account', $users->total()) }}</p>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead class="bg-gray-700 text-gray-200">
                            <tr>
                                <th class="px-3 py-2 text-left">Usernames</th>
                                <th class="px-3 py-2 text-left hidden sm:table-cell">Email</th>
                                <th class="px-3 py-2 text-left">Role</th>
                                <th class="px-3 py-2 text-right hidden sm:table-cell">Replays</th>
                                <th class="px-3 py-2 text-left hidden md:table-cell">Joined</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @forelse($users as $user)
                            <tr class="hover:bg-gray-700/50">
                                <td class="px-3 py-2">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="font-semibold text-blue-400 hover:underline">{{ $user->player_name }}</a>
                                    @foreach($user->playerNames->where('name', '!=', $user->player_name) as $name)
                                    <span class="text-gray-400">· {{ $name->name }}</span>
                                    @endforeach
                                    <div class="text-xs text-gray-400 sm:hidden">{{ $user->email }}</div>
                                </td>
                                <td class="px-3 py-2 hidden sm:table-cell">{{ $user->email }}</td>
                                <td class="px-3 py-2">
                                    @if($user->isOwner())
                                    <span class="rounded bg-yellow-900 px-2 py-0.5 text-xs text-yellow-200">owner</span>
                                    @elseif($user->isAdmin())
                                    <span class="rounded bg-blue-900 px-2 py-0.5 text-xs text-blue-200">admin</span>
                                    @else
                                    <span class="text-gray-400">user</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right hidden sm:table-cell">{{ $user->replay_count }}</td>
                                <td class="px-3 py-2 hidden md:table-cell text-gray-400">{{ $user->created_at?->format('Y-m-d') }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="px-3 py-6 text-center text-gray-400">No accounts match.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $users->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
