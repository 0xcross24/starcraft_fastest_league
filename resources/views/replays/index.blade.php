<x-app-layout>

    <div class="flex items-center justify-center w-full">
        <div class="dark:bg-gray-900 sm:max-w-lg mt-6 px-6 py-2 shadow-md sm:rounded-lg">
            @if(session('success'))
            <div class="bg-green-100 text-green-700 border border-green-400 rounded-lg p-4 w-full max-w-md text-center">
                <p class="font-semibold">{{ session('success') }}</p>
                <p class="text-sm mt-1">Uploaded File: <span class="font-medium">{{ session('file') }}</span></p>
            </div>
            @endif

            @if(session('error'))
            <div class="bg-red-100 text-red-700 border border-red-400 rounded-lg p-4 w-full max-w-md text-center mt-4">
                <p class="font-semibold">{{ session('error') }}</p>
            </div>
            @endif

            @if(session('results'))
            {{-- Batch upload: one line per file, in the order the games were rated --}}
            <div class="rounded-lg border border-gray-600 bg-gray-800 p-4 w-full max-w-md mt-4 text-sm">
                <p class="font-semibold text-gray-100 mb-2">{{ session('summary') }}</p>
                <ul class="space-y-2">
                    @foreach(session('results') as $result)
                    <li class="flex gap-2">
                        <span class="{{ $result['ok'] ? 'text-emerald-400' : 'text-red-400' }}">{{ $result['ok'] ? '✓' : '✗' }}</span>
                        <div class="min-w-0">
                            <div class="font-medium text-gray-200 truncate">{{ $result['file'] }}</div>
                            <div class="text-xs {{ $result['ok'] ? 'text-gray-400' : 'text-red-300' }}">{{ $result['message'] }}</div>
                        </div>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>
    </div>

    <div class="flex items-center justify-center w-full">
        <div class="dark:bg-gray-900 w-full sm:max-w-lg mt-2 px-6 py-2 shadow-md overflow-hidden sm:rounded-lg">
            <form class="max-w-lg mx-auto space-y-4" action="{{ route('upload.upload') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <label for="dropzone-file" class="flex flex-col items-center justify-center w-full h-56 px-8 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 dark:hover:bg-gray-800 dark:bg-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:hover:border-gray-500 dark:hover:bg-gray-600">
                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                        <svg class="w-10 h-10 mb-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2" />
                        </svg>
                        <p class="mb-2 text-sm text-gray-500 dark:text-gray-400"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">StarCraft replays (.rep), up to {{ \App\Http\Controllers\ReplayController::MAX_FILES_PER_UPLOAD }} at once, max 2 MB each</p>
                        <p id="dropzone-selected" class="mt-3 text-xs font-semibold text-blue-400"></p>
                    </div>
                    <input id="dropzone-file" name="files[]" type="file" accept=".rep" multiple class="hidden" required />
                </label>

                <button
                    type="submit"
                    class="w-full px-4 py-2 text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:bg-blue-700">
                    Upload
                </button>
            </form>
        </div>
    </div>

    <script>
        // Show what was picked (or dropped), since the input itself is hidden.
        (() => {
            const input = document.getElementById('dropzone-file');
            const label = input.closest('label');
            const shown = document.getElementById('dropzone-selected');
            const max = {{ \App\Http\Controllers\ReplayController::MAX_FILES_PER_UPLOAD }};
            const render = () => {
                const names = [...input.files].map(f => f.name);
                shown.textContent = names.length === 0 ? ''
                    : names.length > max ? `${names.length} files picked - only ${max} can be uploaded at once`
                    : names.length === 1 ? names[0]
                    : `${names.length} replays: ${names.join(', ')}`;
            };
            input.addEventListener('change', render);
            label.addEventListener('dragover', e => e.preventDefault());
            label.addEventListener('drop', e => {
                e.preventDefault();
                input.files = e.dataTransfer.files;
                render();
            });
        })();
    </script>
</x-app-layout>