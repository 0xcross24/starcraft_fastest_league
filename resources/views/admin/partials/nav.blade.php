{{-- Header, section tabs and flash messages shared by the admin pages. --}}
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">Admin</h1>
    <ul class="flex border-b border-gray-200 dark:border-gray-700 mt-4">
        @php $openRequests = \App\Models\InvalidationRequest::open()->distinct()->count('replay_id'); @endphp
        @foreach(['admin.users.index' => 'Users', 'admin.replays.index' => 'Replays', 'admin.invalidation-requests.index' => 'Requests' . ($openRequests ? " ($openRequests)" : '')] as $routeName => $label)
        @php $active = request()->routeIs(str_replace('.index', '.*', $routeName)); @endphp
        <li class="mr-2">
            <a href="{{ route($routeName) }}"
                class="inline-block py-2 px-4 text-sm font-medium {{ $active ? 'border-b-4 border-blue-500 text-white font-bold dark:bg-gray-900' : 'text-gray-300 hover:text-white' }}">{{ $label }}</a>
        </li>
        @endforeach
    </ul>
</div>

@if (session('status'))
<div class="mb-4 rounded bg-emerald-900/40 border border-emerald-700 px-4 py-2 text-sm text-emerald-300">{{ session('status') }}</div>
@endif
@if (session('error'))
<div class="mb-4 rounded bg-red-900/40 border border-red-700 px-4 py-2 text-sm text-red-300">{{ session('error') }}</div>
@endif
