<?php
declare(strict_types=1);

namespace Spatial\Common\Middleware;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class ErrorHandlingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $displayErrorDetails = false // toggle dev/prod
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            // Log error
            $this->logger->error('Unhandled exception', [
                'exception' => $e,
                'uri' => (string) $request->getUri(),
                'method' => $request->getMethod(),
            ]);

            // Build error payload
            $error = [
                'error' => 'Internal Server Error',
                'status' => 500,
            ];

            if ($this->displayErrorDetails) {
                $error['message'] = $e->getMessage();
                $error['file'] = $e->getFile();
                $error['line'] = $e->getLine();
                $error['trace'] = explode("\n", $e->getTraceAsString());
            }

            // JSON response by default
            $response = new Response(500);
            $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($error, JSON_PRETTY_PRINT));

            return $response;
        }
    }
}
