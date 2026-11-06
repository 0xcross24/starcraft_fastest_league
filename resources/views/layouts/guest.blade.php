<!DOCTYPE html>
<html class="dark" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('layouts.partials.head')
</head>


<body class="font-sans antialiased min-h-screen flex flex-col">
    <div class="flex-1 flex flex-col bg-gray-100 dark:bg-gray-900">
        @include('layouts.navigation')
        <div class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 w-full flex flex-col">
            {{ $slot }}
        </div>
    </div>
    <x-footer />
    <script src="https://cdn.jsdelivr.net/npm/flowbite@2.5.2/dist/flowbite.min.js"></script>
    <x-pending-download />
</body>

</html>
