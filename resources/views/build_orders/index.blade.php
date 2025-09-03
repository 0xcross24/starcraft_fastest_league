<x-app-layout>
    <h1 class="text-2xl font-bold mb-4">Build Orders</h1>
    <ul>
        @foreach ($buildOrders as $buildOrder)
        <li>
            <a href="{{ route('builds.show', $buildOrder->id) }}">{{ $buildOrder->title }}</a>
        </li>
        @endforeach
    </ul>
</x-app-layout>
