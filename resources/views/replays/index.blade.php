<x-layout>
  <x-slot:title>
    Replays
  </x-slot:title>
  <div class="flex items-center justify-center w-full">
    @if(session('success'))
        <p style="color: green;">{{ session('success') }}</p>
        <p>Uploaded File: {{ session('file') }}</p> <!-- Display the file name -->
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif
  </div>
  <form action="{{ route('replays.upload') }}" method="POST" enctype="multipart/form-data">
      @csrf
      <label for="file">Upload File:</label>
      <input type="file" name="file" id="file" required>
      <button type="submit">Upload</button>
  </form>
</x-layout>
