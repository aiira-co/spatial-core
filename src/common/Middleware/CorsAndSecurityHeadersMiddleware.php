<?php
declare(strict_types=1);

namespace Spatial\Common\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use GuzzleHttp\Psr7\Response;

class CorsAndSecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Handle preflight OPTIONS request
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $this->withCorsAndSecurityHeaders(new Response(200));
        }

        // Normal flow: let request through, then add headers
        $response = $handler->handle($request);

        return $this->withCorsAndSecurityHeaders($response);
    }

    private function withCorsAndSecurityHeaders(ResponseInterface $response): ResponseInterface
    {
        return $response
            // --- CORS headers ---
            ->withHeader('Access-Control-Allow-Origin', '*') // change "*" to your domain for security
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Max-Age', '86400') // cache preflight response

            // --- Security headers ---
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-XSS-Protection', '1; mode=block')
            ->withHeader('Referrer-Policy', 'no-referrer-when-downgrade')
            ->withHeader('Content-Security-Policy', "default-src 'self'")
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
    }
}
