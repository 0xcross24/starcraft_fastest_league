<x-app-layout>
    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                @include('admin.partials.nav')

                <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-400 hover:underline">&larr; All users</a>
                <h2 class="text-xl font-semibold mt-2">{{ $user->player_name }}</h2>
                <p class="text-sm text-gray-400">
                    Account #{{ $user->id }} · joined {{ $user->created_at?->format('Y-m-d') }} · {{ $user->replay_count }} replay {{ Str::plural('row', $user->replay_count) }}
                </p>

                {{-- Account --}}
                @if($user->isOwner() && !auth()->user()->isOwner())
                <p class="mt-6 text-sm text-gray-400">This is an owner account. Only an owner can change it.</p>
                @else
                <form method="post" action="{{ route('admin.users.update', $user) }}" class="mt-6 space-y-4">
                    @csrf
                    @method('patch')
                    <div>
                        <x-input-label for="email" value="Email (used to log in)" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required />
                        <x-input-error class="mt-2" :messages="$errors->get('email')" />
                    </div>
                    <div>
                        <x-input-label for="role" value="Role" />
                        @if(auth()->user()->isOwner() && !$user->is(auth()->user()))
                        <select id="role" name="role" class="mt-1 block w-full rounded-md border-gray-700 bg-gray-900 text-gray-200">
                            @foreach($roles as $role)
                            <option value="{{ $role }}" @selected(old('role', $user->role) === $role)>{{ $role }}</option>
                            @endforeach
                        </select>
                        @else
                        <input type="hidden" name="role" value="{{ $user->role }}">
                        <p id="role" class="mt-1 text-gray-200">{{ $user->role }}</p>
                        @endif
                        <p class="mt-1 text-xs text-gray-400">
                            Admins can manage builds and use this portal. Owners can also change roles and owner accounts.
                            @if($user->is(auth()->user()))
                            You cannot change your own role.
                            @elseif(!auth()->user()->isOwner())
                            Only an owner can change roles.
                            @endif
                        </p>
                        <x-input-error class="mt-2" :messages="$errors->get('role')" />
                    </div>
                    <x-primary-button>Save</x-primary-button>
                </form>
                @endif
            </div>

            @if(!$user->isOwner() || auth()->user()->isOwner())

            {{-- Usernames --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                <h3 class="text-lg font-semibold">Usernames</h3>
                <p class="text-sm text-gray-400">Up to {{ \App\Models\PlayerName::MAX_PER_USER }}. All share the account's one rank. The main name is shown on the rankings; to rename, add the new name and make it main.</p>
                @php $retired = $user->allPlayerNames->filter->isRetired(); @endphp
                @if($retired->isNotEmpty())
                <p class="mt-1 text-xs text-gray-400">Removed (games kept, can't be registered by others): {{ $retired->pluck('name')->implode(', ') }}</p>
                @endif
                <ul class="mt-3 divide-y divide-gray-700">
                    @foreach($user->playerNames as $name)
                    <li class="flex items-center justify-between py-2">
                        <a href="{{ route('player', ['user' => $name->name]) }}" class="text-blue-400 hover:underline">{{ $name->name }}</a>
                        @if($blocker = $name->removalBlocker())
                        <span class="text-xs text-gray-400">Main name</span>
                        @else
                        <div class="flex items-center gap-4">
                            <form method="post" action="{{ route('admin.users.names.main', [$user, $name]) }}">
                                @csrf
                                @method('patch')
                                <button class="text-xs text-blue-400 hover:underline">Make main</button>
                            </form>
                            <form method="post" action="{{ route('admin.users.names.destroy', [$user, $name]) }}"
                                onsubmit="return confirm(@js('Remove the username '.$name->name.'? Points and games stay on the account.'))">
                                @csrf
                                @method('delete')
                                <button class="text-xs text-red-400 hover:underline">Remove</button>
                            </form>
                        </div>
                        @endif
                    </li>
                    @endforeach
                </ul>
                @if($user->playerNames->count() < \App\Models\PlayerName::MAX_PER_USER)
                <form method="post" action="{{ route('admin.users.names.store', $user) }}" class="mt-3 flex items-start gap-3">
                    @csrf
                    <div class="flex-1">
                        <x-text-input name="name" type="text" class="block w-full" :value="old('name')" placeholder="In-game name" required maxlength="50" />
                        <x-input-error class="mt-2" :messages="$errors->playerName->get('name')" />
                    </div>
                    <x-primary-button>Add</x-primary-button>
                </form>
                @endif
            </div>

            {{-- Actions --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100 space-y-4">
                <h3 class="text-lg font-semibold">Actions</h3>
                <form method="post" action="{{ route('admin.users.password-reset', $user) }}">
                    @csrf
                    <x-secondary-button type="submit">Send password reset email</x-secondary-button>
                    <p class="mt-1 text-xs text-gray-400">Emails a reset link to {{ $user->email }}.</p>
                </form>
                @if($user->is(auth()->user()))
                <p class="text-xs text-gray-400">You cannot delete your own account here.</p>
                @elseif($user->replay_count > 0)
                <p class="text-xs text-gray-400">Delete account: not possible, this account has replays. Invalidate bad games in Replays instead.</p>
                @else
                <form method="post" action="{{ route('admin.users.destroy', $user) }}"
                    onsubmit="return confirm(@js('Permanently delete '.$user->player_name.' and all their usernames?'))">
                    @csrf
                    @method('delete')
                    <x-danger-button>Delete account</x-danger-button>
                    <p class="mt-1 text-xs text-gray-400">Removes the account and its usernames. Cannot be undone.</p>
                </form>
                @endif
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
