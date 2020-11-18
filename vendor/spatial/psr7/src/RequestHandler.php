<?php

namespace Spatial\Psr7;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestHandler implements RequestHandlerInterface
{
    protected Response $response;

    public function __construct()
    {
        $this->response = new Response();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $payload = json_encode(
            [
                'success' => true,
                'message' => 'Spatial Psr7 Works!',
                'data' => ['value1', 'value2']
            ],
            JSON_THROW_ON_ERROR,
            512
        );
        $this->response->getBody()->write($payload);
        return $this->response;
    }
}
