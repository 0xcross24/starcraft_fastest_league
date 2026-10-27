{{-- Starts a download a guest clicked before logging in (see App\Http\Middleware\Authenticate).
     Navigating to a file download keeps this page on screen. --}}
@auth
@php($pendingDownload = session()->pull(\App\Http\Middleware\Authenticate::PENDING_DOWNLOAD))
@if($pendingDownload)
<script>window.addEventListener('load', () => { window.location.href = @json($pendingDownload); });</script>
@endif
@endauth
