<?php

namespace Spatial\Common\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use GuzzleHttp\Psr7\Response;
use Respect\Validation\Validator as v;
use Throwable;

/**
 * Example usage
 * use Spatial\Common\Middleware\ValidationMiddleware;
 * use Respect\Validation\Validator as v;
 *
 * // For a user registration route
 * $rules = [
 * 'body' => [
 * 'email' => v::email()->notEmpty(),
 * 'password' => v::stringType()->length(6, null),
 * ],
 * 'query' => [
 * 'invite' => v::optional(v::stringType()->length(10, 20)),
 * ],
 * 'headers' => [
 * 'X-Api-Key' => v::stringType()->length(32, 64),
 * ],
 * ];
 *
 * $app->pipe(new ValidationMiddleware($rules));
 */
class ValidationMiddleware implements MiddlewareInterface
{
    private array $rules;

    /**
     * @param array $rules Example:
     * [
     *   'body' => [
     *       'email' => v::email()->notEmpty(),
     *       'password' => v::stringType()->length(6, null),
     *   ],
     *   'query' => [
     *       'page' => v::optional(v::intVal()->min(1)),
     *   ],
     *   'headers' => [
     *       'X-Api-Key' => v::stringType()->length(32, 64),
     *   ]
     * ]
     */
    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $errors = [];

        try {
            /** Validate body (JSON only) */
            if (isset($this->rules['body'])) {
                $data = json_decode((string)$request->getBody(), true) ?? [];
                foreach ($this->rules['body'] as $field => $validator) {
                    if (!$validator->validate($data[$field] ?? null)) {
                        $errors['body'][$field] = "Invalid value for '$field'";
                    }
                }
                // Rewind body so next handlers can still read it
                $request->getBody()->rewind();
            }

            /** Validate query params */
            if (isset($this->rules['query'])) {
                $query = $request->getQueryParams();
                foreach ($this->rules['query'] as $field => $validator) {
                    if (!$validator->validate($query[$field] ?? null)) {
                        $errors['query'][$field] = "Invalid value for '$field'";
                    }
                }
            }

            /** Validate headers */
            if (isset($this->rules['headers'])) {
                foreach ($this->rules['headers'] as $header => $validator) {
                    $value = $request->getHeaderLine($header);
                    if (!$validator->validate($value)) {
                        $errors['headers'][$header] = "Invalid value for '$header'";
                    }
                }
            }
        } catch (Throwable $e) {
            return $this->badRequest(['general' => 'Validation processing error']);
        }

        if (!empty($errors)) {
            return $this->badRequest($errors);
        }

        return $handler->handle($request);
    }

    private function badRequest(array $errors): ResponseInterface
    {
        $response = new Response(400);
        $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'error' => 'Validation Failed',
            'messages' => $errors,
            'status' => 400,
        ], JSON_PRETTY_PRINT));

        return $response;
    }
}


