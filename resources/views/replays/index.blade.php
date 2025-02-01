<x-app-layout>
    <x-slot:title>
        Replays
    </x-slot:title>

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
        </div>
    </div>

    <div class="flex items-center justify-center w-full">
        <div class="dark:bg-gray-900 w-full sm:max-w-lg mt-2 px-6 py-2 shadow-md overflow-hidden sm:rounded-lg">
            <form class="max-w-lg mx-auto space-y-4" action="{{ route('replays.upload') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <label for="dropzone-file" class="flex flex-col items-center justify-center w-full h-56 px-8 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 dark:hover:bg-gray-800 dark:bg-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:hover:border-gray-500 dark:hover:bg-gray-600">
                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                        <svg class="w-10 h-10 mb-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2" />
                        </svg>
                        <p class="mb-2 text-sm text-gray-500 dark:text-gray-400"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">SVG, PNG, JPG or GIF (MAX. 800x400px)</p>
                    </div>
                    <input id="dropzone-file" name="file" type="file" class="hidden" required />
                </label>

                <button
                    type="submit"
                    class="w-full px-4 py-2 text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:bg-blue-700">
                    Upload
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
