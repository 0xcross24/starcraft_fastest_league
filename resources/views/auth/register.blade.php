<x-auth-layout>
    <form method="POST" action="{{ route('register') }}" class="group">
        @csrf

        <!-- Name -->
        <div x-data="{ showTip: false }">
            <div class="flex items-center gap-2 relative group"
                @mouseenter="showTip = true" @mouseleave="showTip = false">
                <x-input-label for="player_name" :value="__('Player Name')" class="cursor-pointer" />
                <span class="relative">
                    <svg class="w-4 h-4 text-blue-500 cursor-pointer" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16v-4m0-4h.01" />
                    </svg>
                    <span
                        class="absolute left-6 top-1/2 -translate-y-1/2 w-56 bg-gray-800 text-white text-xs rounded px-3 py-2 shadow-lg opacity-0 transition-opacity z-10 pointer-events-none"
                        :class="{ 'opacity-100': showTip }"
                        x-show="showTip || $refs.playerNameInput === document.activeElement"
                        style="display: none;">
                        Player Name must match your in-game Starcraft name
                    </span>
                </span>
            </div>
            <x-text-input id="player_name" x-ref="playerNameInput"
                @focus="showTip = true" @blur="showTip = false"
                class="block mt-1 w-full"
                type="text" name="player_name" :value="old('player_name')" required autofocus autocomplete="player_name" />
            <x-input-error :messages="$errors->get('player_name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                type="password"
                name="password"
                required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                type="password"
                name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-auth-layout>
