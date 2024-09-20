<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <script src="https://cdn.tailwindcss.com"></script>
  
  <title>{{ $title }}</title>
</head>
<body>


  <nav class="bg-white border-gray-200 dark:bg-gray-900">
    <div class="max-w-screen-xl flex flex-wrap items-center justify-between mx-auto p-4">
    <a href="/" class="flex items-center space-x-3 rtl:space-x-reverse">
        <img src={{ asset('images/fsl-logo.png')}} class="h-8" alt="Fastest Starcraft League Logo" />
    </a>
    <div class="items-center justify-between hidden w-full md:flex md:w-auto md:order-1" id="navbar-user">
      <ul class="flex flex-col font-medium p-4 md:p-0 mt-4 border border-gray-100 rounded-lg bg-gray-50 md:space-x-8 rtl:space-x-reverse md:flex-row md:mt-0 md:border-0 md:bg-white dark:bg-gray-800 md:dark:bg-gray-900 dark:border-gray-700">
        <li>
          <x-nav-link href="/" :active="request()->is('/')">Home</x-nav-link>
        </li>
        <li>
          <x-nav-link href="/rules" :active="request()->is('rules')">Rules</x-nav-link>
        </li>
        <li>
          <x-nav-link href="/rankings" :active="request()->is('rankings')">Rankings</x-nav-link>
        </li>
        <li>
          <x-nav-link href="/replays" :active="request()->is('replays')">Replays</x-nav-link>
        </li>
        <li>
          <x-nav-link href="/seasons" :active="request()->is('seasons')">Seasons</x-nav-link>
        </li>
        <li>
          <x-nav-link href="/streams" :active="request()->is('streams')">Streams</x-nav-link>
        </li>
      </ul>
    </div>
    </div>
  </nav>
  
  {{ $slot }}

</body>
</html>