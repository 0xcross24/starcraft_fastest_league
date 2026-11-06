<footer class="w-full py-6 bg-gray-100 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 text-center">
    <div class="flex flex-col md:flex-row items-center justify-center gap-4 text-sm text-gray-600 dark:text-gray-300">
        <span>Connect with us:</span>
        <a href="https://discord.com/invite/kZZXemJQzj" target="_blank" rel="noopener" class="hover:text-blue-500 font-semibold">Discord</a>
        <a href="https://www.youtube.com/@cross_tv1" target="_blank" rel="noopener" class="hover:text-blue-500 font-semibold">YouTube</a>
    </div>
    <nav aria-label="Site information" class="mt-4 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-gray-600 dark:text-gray-300">
        <a href="{{ route('about') }}" class="hover:text-blue-500">About</a>
        <a href="{{ route('rules') }}" class="hover:text-blue-500">Rules</a>
        <a href="{{ route('contact') }}" class="hover:text-blue-500">Contact</a>
        <a href="{{ route('privacy') }}" class="hover:text-blue-500">Privacy Policy</a>
    </nav>
    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">&copy; {{ now()->year }} StarCraft Fastest League. StarCraft is a trademark of Blizzard Entertainment; SFL is a fan community and is not affiliated with Blizzard.</p>
</footer>
