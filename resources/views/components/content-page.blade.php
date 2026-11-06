@props(['heading'])

{{-- Shared frame for the written pages (About, Contact, Privacy). There is
     no typography plugin, so headings, paragraphs and lists are styled here. --}}
<div class="py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <article class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 sm:p-8 text-gray-900 dark:text-gray-100 leading-relaxed
            [&_h2]:text-xl [&_h2]:font-bold [&_h2]:mt-8 [&_h2]:mb-3
            [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:mt-6 [&_h3]:mb-2
            [&_p]:mb-4 [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:mb-4 [&_li]:mb-1
            [&_a]:text-blue-600 dark:[&_a]:text-blue-400 [&_a]:underline
            [&_table]:w-full [&_table]:text-sm [&_table]:mb-4 [&_th]:text-left [&_th]:py-1 [&_td]:py-1 [&_th]:pr-4 [&_td]:pr-4">
            <h1 class="text-3xl font-bold font-logo mb-6">{{ $heading }}</h1>
            {{ $slot }}
        </article>
    </div>
</div>
