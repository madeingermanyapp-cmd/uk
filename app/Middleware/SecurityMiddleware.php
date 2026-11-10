<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Engine\AuditLog;
use App\Engine\Request;
use App\Engine\Response;

/**
 * Adds HTTP security headers to every response.
 *
 * This middleware fires FIRST in the pipeline so that every response
 * (including error responses from downstream middleware) carries security headers.
 *
 * Per PRD §XV Security — headers are applied unconditionally.
 * Context-specific headers (CSP, Cache-Control) vary by path prefix.
 * Routes not matching any prefix still get the default CSP.
 */
final class SecurityMiddleware
{
    private const DEFAULT_CSP = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'";

    public function handle(Request $request, callable $next): Response
    {
        // Generate request correlation ID (UUID v4) — used by AuditLog for tracing
        $requestId = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        $request->setAttribute('request_id', $requestId);
        AuditLog::setRequestId($requestId);

        $forceHttps = ($_ENV['FORCE_HTTPS'] ?? 'false') === 'true';

        // HTTPS redirect
        if ($forceHttps && !$request->isSecure()) {
            $url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $request->path();

            return Response::redirect($url, 301);
        }

        // Detect embed-related paths before dispatching
        $path = $request->path();
        $isEmbedBooking = str_starts_with($path, '/book/') && ($request->string('embed') === '1');
        $isEmbedScript  = str_starts_with($path, '/embed/');
        $isEmbedConfig  = str_starts_with($path, '/api/') && str_ends_with($path, '/embed-config');

        // Mark embed mode on the request so controllers can check it
        if ($isEmbedBooking) {
            $request->setAttribute('is_embed', true);
        }

        /** @var Response $response */
        $response = $next($request);

        // Universal security headers — present on EVERY response
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-XSS-Protection', '0');
        // Capability portals opt into no-referrer to avoid disclosing private tracking links.
        if (!array_key_exists('Referrer-Policy', $response->getHeaders())) {
            $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        $response->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // X-Frame-Options — skip for embed paths that need iframe embedding
        if (!$isEmbedBooking) {
            $response->header('X-Frame-Options', 'SAMEORIGIN');
        }

        // HSTS
        if ($forceHttps) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // CSP — context-specific. Embed booking pages use controller-set
        // frame-ancestors header; everything else gets the blocking default.
        if ($isEmbedBooking) {
            // The controller already set a CSP with frame-ancestors for the
            // configured allowed domains. Do NOT overwrite it here.
            // Only supplement if the controller didn't set one.
        } elseif (str_starts_with($path, '/install') || str_starts_with($path, '/admin') || str_starts_with($path, '/book/')) {
            $response->header('Content-Security-Policy',
                "default-src 'self'; script-src 'self' 'unsafe-inline'; "
                . "style-src 'self' 'unsafe-inline'; "
                . "img-src 'self' data:; font-src 'self'; "
                . "connect-src 'self'; frame-ancestors 'none'"
            );
        } elseif (!array_key_exists('Content-Security-Policy', $response->getHeaders())) {
            $response->header('Content-Security-Policy', self::DEFAULT_CSP);
        }

        // Context-specific cache control
        // Embed script and embed-config set their own cache headers — let them through.
        if ($isEmbedScript || $isEmbedConfig) {
            // Controller already set Cache-Control; don't overwrite.
        } elseif (str_starts_with($path, '/admin') || str_starts_with($path, '/install')) {
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->header('Pragma', 'no-cache');
        } elseif (str_starts_with($path, '/api/')) {
            $response->header('Cache-Control', 'no-store');
        } elseif (str_starts_with($path, '/book/')) {
            $response->header('Cache-Control', 'public, max-age=0, must-revalidate');
        } else {
            // Default: no caching for unmatched routes (health, install, etc.)
            $response->header('Cache-Control', 'no-store');
        }

        return $response;
    }
}
