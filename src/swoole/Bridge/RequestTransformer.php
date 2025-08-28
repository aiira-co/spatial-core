<?php

declare(strict_types=1);

namespace Spatial\Swoole\Bridge;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;
use Swoole\Http\Request as SwooleRequest;

class RequestTransformer implements RequestInterface
{

    private string $method;
    private string $requestTarget;
    private UriInterface $uri;
    private StreamInterface $body;
    private string $protocol;


    public function __construct(
        private UriFactoryInterface $uriFactory,
        private StreamFactoryInterface $streamFactory,
        private SwooleRequest $swooleRequest
    ) {
    }


    public function getRequestTarget(): string
    {
        return !empty($this->requestTarget)
            ? $this->requestTarget
            : ($this->requestTarget = $this->buildRequestTarget());
    }

    private function buildRequestTarget(): string
    {
        $queryString = !empty($this->swooleRequest->server['query_string'])
            ? '?' . $this->swooleRequest->server['query_string']
            : '';

        return $this->swooleRequest->server['request_uri']
            . $queryString;
    }

    public function withRequestTarget($requestTarget): RequestInterface
    {
        $new = clone $this;
        $new->requestTarget = $requestTarget;
        return $new;
    }

    public function getMethod(): string
    {
        return !empty($this->method)
            ? $this->method
            : ($this->method = $this->swooleRequest->server['request_method']);
    }

    public function withMethod($method): static
    {
        $validMethods = ['options', 'get', 'head', 'post', 'put', 'delete', 'trace', 'connect'];
        if (!in_array(strtolower($method), $validMethods)) {
            throw new \InvalidArgumentException('Invalid HTTP method');
        }

        $new = clone $this;
        $new->method = $method;
        return $new;
    }


    public function getUri(): UriInterface
    {
        if (!empty($this->uri)) {
            return $this->uri;
        }

        $userInfo = $this->parseUserInfo() ?? null;
        $http = isset($this->swooleRequest->header['x-forwarded-proto']) ? $this->swooleRequest->header['x-forwarded-proto'] . '://' : '';
        $uri = (!empty($userInfo) ? '//' . $userInfo . '@' : $http)
            . $this->swooleRequest->header['host']
            . $this->getRequestTarget();

        return $this->uri = $this->uriFactory->createUri(
            $uri
        );
    }

    private function parseUserInfo(): ?string
    {
        $authorization = $this->swooleRequest->header['authorization'] ?? '';

        if (strpos($authorization, 'Basic') === 0) {
            $parts = explode(' ', $authorization);
            return base64_decode($parts[1]);
        }

        return null;
    }

    public function withUri(UriInterface $uri, $preserveHost = false): RequestInterface
    {
        $new = clone $this;
        $new->uri = $uri;
        return $new;
    }

    public function getProtocolVersion(): string
    {
        return $this->protocol ?? ($this->protocol = '1.1');
    }

    public function withProtocolVersion($version): RequestInterface
    {
        $new = clone $this;
        $new->protocol = $version;
        return $new;
    }

    public function getHeaders(): array
    {
        return $this->swooleRequest->header;
    }

    public function hasHeader($name): bool
    {
        foreach ($this->swooleRequest->header as $key => $value) {
            if (strtolower($name) == strtolower($key)) {
                return true;
            }
        }

        return false;
    }

    public function getHeader($name): array
    {
        if (!$this->hasHeader($name)) {
            return [];
        }

        foreach ($this->swooleRequest->header as $key => $value) {
            if (strtolower($name) == strtolower($key)) {
                return is_array($value)
                    ? $value
                    : [$value];
            }
        }

        return [];
    }

    public function getHeaderLine($name): string
    {
        return \implode(',', $this->getHeader($name));
    }

    public function withHeader($name, $value): RequestInterface
    {
        $new = clone $this;
        $new->swooleRequest->header[$name] = $value;
        return $new;
    }

    public function withAddedHeader($name, $value): RequestInterface
    {
        if (!$this->hasHeader($name)) {
            return $this->withHeader($name, $value);
        }

        $new = clone $this;
        if (is_array($new->swooleRequest->header[$name])) {
            $new->swooleRequest->header[$name][] = $value;
        } else {
            $new->swooleRequest->header[$name] = [
                $new->swooleRequest->header[$name],
                $value
            ];
        }

        return $new;
    }

    public function withoutHeader($name): RequestInterface
    {
        $new = clone $this;

        if (!$new->hasHeader($name)) {
            return $new;
        }

        foreach ($new->swooleRequest->header as $key => $value) {
            if (strtolower($name) == strtolower($key)) {
                unset($new->swooleRequest->header[$key]);
                break;
            }
        }

        return $new;
    }

    public function getBody(): StreamInterface
    {
        return $this->body ?? $this->streamFactory->createStream($this->swooleRequest->rawContent());
    }

    public function withBody(StreamInterface $body): RequestInterface
    {
        $new = clone $this;
        $new->body = $body;
        return $new;
    }
}