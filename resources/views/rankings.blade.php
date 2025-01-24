<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
      {{ __('Rankings') }}
    </h2>
  </x-slot>

  <div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
      <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900 dark:text-gray-100">
          <div class="container">
            <div class="card py-6">
              <div class="card-header">
                <h2>All User Rankings</h2>
              </div>

              @if($usersWithStats->isEmpty())
              <p>No registered users found.</p>
              @else
              <table class="table">
                <tbody>
                  @foreach($usersWithStats as $user)
                  <tr>
                    <td>{{ $user->player_name }}</td>
                    <td>{{ $user->stats->wins ?? 'N/A' }}</td> <!-- Use 'N/A' if stats not found -->
                    <td>{{ $user->stats->losses ?? 'N/A' }}</td> <!-- Use 'N/A' if stats not found -->
                    <td>{{ $user->stats->elo ?? 'N/A' }}</td>
                  </tr>
                  @endforeach
                </tbody>
              </table>
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</x-app-layout>
