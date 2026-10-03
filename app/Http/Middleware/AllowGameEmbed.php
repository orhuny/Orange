<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allow the crash game page to be embedded in operator-side iframes
 * (e.g. OrangeConsumer). Strips X-Frame-Options and sets a CSP
 * frame-ancestors directive based on config('embed.allowed_origins').
 *
 * Dev default is http://localhost:8001 — production deployments should
 * set EMBED_ALLOWED_ORIGINS to the operator's actual domain(s).
 */
class AllowGameEmbed
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $origins = (array) config('embed.allowed_origins', []);
        $origins = array_values(array_filter(array_map('trim', $origins)));

        if ($origins === []) {
            return $response;
        }

        // X-Frame-Options is a legacy single-origin header — frame-ancestors
        // supersedes it in modern browsers. Drop the legacy header so the
        // CSP directive is the only thing in effect.
        $response->headers->remove('X-Frame-Options');

        $directive = "frame-ancestors 'self' ".implode(' ', $origins);

        // Merge with any existing CSP value rather than clobbering it.
        $existing = $response->headers->get('Content-Security-Policy');

        if ($existing && ! str_contains($existing, 'frame-ancestors')) {
            $response->headers->set('Content-Security-Policy', rtrim($existing, '; ').'; '.$directive);
        } else {
            $response->headers->set('Content-Security-Policy', $directive);
        }

        return $response;
    }
}
