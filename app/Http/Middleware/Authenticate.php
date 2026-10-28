<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /** Routes that answer with a file rather than a page. */
    public const DOWNLOAD_ROUTES = ['replay.download', 'replays.download-zip', 'maps.download'];

    /** Session key for a download to start once the user has logged in (see x-pending-download). */
    public const PENDING_DOWNLOAD = 'download_after_login';

    /**
     * A guest clicking a download goes to the login page as usual, but after
     * logging in returns to the page they clicked it on, where the download
     * then starts. Going straight to the file would leave them staring at
     * the login form while the file downloads.
     */
    public function handle($request, Closure $next, ...$guards)
    {
        if (! $request->user() && $request->isMethod('GET') && ! $request->expectsJson()
            && $request->route()?->named(...self::DOWNLOAD_ROUTES)) {
            $request->session()->put('url.intended', $this->pageClickedOn($request));
            $request->session()->put(self::PENDING_DOWNLOAD, $request->getRequestUri());

            return redirect()->route('login');
        }

        $response = parent::handle($request, $next, ...$guards);

        // File responses default to "Cache-Control: public" with a Last-Modified
        // date, which lets the browser hand the file out again from its cache
        // after logging out, without asking (and checking login) here.
        if ($request->route()?->named(...self::DOWNLOAD_ROUTES)) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    /**
     * Path of the page the link was on, from the Referer when it's this site;
     * otherwise (link opened directly, or from another site) the homepage.
     * Only a path is kept, so this can't redirect anywhere off-site.
     */
    private function pageClickedOn(Request $request): string
    {
        $referer = parse_url((string) $request->headers->get('referer'));
        $sameSite = ($referer['host'] ?? null) === $request->getHost();
        $path = $referer['path'] ?? '/';

        if (! $sameSite || str_starts_with($path, '/login') || str_starts_with($path, '//')) {
            return route('homepage', absolute: false);
        }

        return $path . (isset($referer['query']) ? '?' . $referer['query'] : '');
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('login');
        }
    }
}
