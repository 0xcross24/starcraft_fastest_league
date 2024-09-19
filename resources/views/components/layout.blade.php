<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-100">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="{{ asset('css/stylesheet.css') }}">
  
  <title>{{ $title }}</title>
</head>
<body>
  <div class="bg-white">
    <header class="absolute inset-x-0 top-0 z-50">
      <nav class="bg-navbar flex items-center justify-between p-6 lg:px-8" aria-label="Global">
        <div class="flex lg:flex-1">
          <a href="/" class="-m-1.5 p-1.5">
            <img class="w-auto" src="{{ asset('images/fsl-logo.png')}}" alt="">
          </a>
        </div>
        <div class="flex lg:hidden">
          <button type="button" class="-m-2.5 inline-flex items-center justify-center rounded-md p-2.5 text-gray-700">
            <svg class="h-8 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
          </button>
        </div>
        <div class="hidden lg:flex lg:gap-x-12 items-center lg:justify-end">
          <a href="/rules" class="text-lg font-semibold leading-6 nav-links">Rules</a>
          <a href="/forum" class="text-lg font-semibold leading-6 nav-links">Forum</a>
          <a href="/replays" class="text-lg font-semibold leading-6 nav-links">Replays</a>
          <a href="/seasons" class="text-lg font-semibold leading-6 nav-links">Seasons</a>
          <a href="/login" class="text-lg font-semibold leading-6 p-2 login-link text-gray-100">Log in <span aria-hidden="true">&rarr;</span></a>
        </div>
      </nav>
      <!-- Mobile menu, show/hide based on menu open state. -->
      <div class="lg:hidden" role="dialog" aria-modal="true">
        <!-- Background backdrop, show/hide based on slide-over state. -->
        <div class="fixed inset-0 z-50"></div>
        <div class="fixed inset-y-0 right-0 z-50 w-full overflow-y-auto bg-white px-6 py-6 sm:max-w-sm sm:ring-1 sm:ring-gray-900/10">
          <div class="flex items-center justify-between">
            <a href="/" class="-m-1.5 p-1.5">
              <img class="h-8 w-auto" src="src={{ asset('images/fsl-logo.png')}}" alt="">
            </a>
            <button type="button" class="-m-2.5 rounded-md p-2.5 text-gray-700">
              <span class="sr-only">Close menu</span>
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="mt-6 flow-root">
            <div class="-my-6 divide-y divide-gray-500/10">
              <div class="space-y-2 py-6">
                <a href="/rules" class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 nav-links hover:bg-gray-50">Rules</a>
                <a href="/forum" class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 nav-links hover:bg-gray-50">Forum</a>
                <a href="/replays" class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 nav-links hover:bg-gray-50">Replays</a>
                <a href="/seasons" class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 nav-links hover:bg-gray-50">Seasons</a>
              </div>
              <div class="py-6">
                <a href="#" class="-mx-3 block rounded-lg px-3 py-2.5 text-base font-semibold leading-7 login-link text-gray-100 hover:bg-gray-50">Log in</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </header>
  </div>
</body>
</html>