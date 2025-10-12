<?php
declare(strict_types=1);

namespace Spatial\Common\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $jwtSecret,
        private readonly bool $useSessions = true // allow fallback to PHP sessions
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $user = null;

            /** --- JWT AUTH --- */
            $authHeader = $request->getHeaderLine('Authorization');
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];

                try {
                    $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
                    $user = (array) $decoded;
                } catch (Throwable $e) {
                    return $this->unauthorized('Invalid or expired JWT');
                }
            }

            /** --- SESSION AUTH --- */
            if (!$user && $this->useSessions) {
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    @session_start();
                }
                if (!empty($_SESSION['user'])) {
                    $user = $_SESSION['user'];
                }
            }

            /** --- AUTH CHECK --- */
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            // Attach user to request for downstream middlewares/controllers
            $request = $request->withAttribute('user', $user);

            return $handler->handle($request);
        } catch (Throwable $e) {
            return $this->unauthorized('Auth processing error');
        }
    }

    private function unauthorized(string $message): ResponseInterface
    {
        $payload = [
            'error' => 'Unauthorized',
            'message' => $message,
            'status' => 401,
        ];

        $response = new Response(401);
        $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));

        return $response;
    }
}
