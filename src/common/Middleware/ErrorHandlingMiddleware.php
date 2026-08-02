<?php
declare(strict_types=1);

namespace Spatial\Common\Middleware;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Spatial\Core\Exception\HttpAwareExceptionInterface;
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
            $status = 500;
            $title = 'Internal Server Error';
            $headers = ['Content-Type' => 'application/json'];

            // Exceptions that already know their HTTP meaning translate directly,
            // so infrastructure conditions such as an exhausted connection pool
            // shed load as 503 instead of being reported as an application bug.
            if ($e instanceof HttpAwareExceptionInterface) {
                $status = $e->getStatusCode();
                $title = $e->getErrorTitle();

                $retryAfter = $e->getRetryAfter();
                if ($retryAfter !== null) {
                    $headers['Retry-After'] = (string)$retryAfter;
                }
            }

            $context = [
                'exception' => $e,
                'uri' => (string)$request->getUri(),
                'method' => $request->getMethod(),
                'status' => $status,
            ];

            if ($status >= 500) {
                $this->logger->error('Unhandled exception', $context);
            } else {
                $this->logger->warning('Request failed', $context);
            }

            $error = [
                'error' => $title,
                'status' => $status,
            ];

            if ($this->displayErrorDetails) {
                $error['message'] = $e->getMessage();
                $error['file'] = $e->getFile();
                $error['line'] = $e->getLine();
                $error['trace'] = explode("\n", $e->getTraceAsString());
            }

            // Headers and body are passed to the constructor: PSR-7 messages are
            // immutable, so the previous `$response->withHeader(...)` on its own
            // line discarded the result and never set Content-Type.
            return new Response(
                $status,
                $headers,
                json_encode($error, JSON_PRETTY_PRINT)
            );
        }
    }
}
