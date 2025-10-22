<?php

declare(strict_types=1);


namespace Spatial\Swoole\Bridge;


use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use OpenSwoole\Http\Request as SwooleRequest;

class ServerRequestTransformer extends RequestTransformer implements ServerRequestInterface
{
    public $attributes = [];
    protected $parsedBody;
    protected $files;
    protected $query;
    protected $cookies;

    public function __construct(
        private UriFactoryInterface $uriFactory,
        private StreamFactoryInterface $streamFactory,
        private UploadedFileFactoryInterface $uploadedFileFactory,
        private SwooleRequest $swooleRequest
    ) {
        parent::__construct($uriFactory, $streamFactory, $swooleRequest);
        $this->uploadedFileFactory = $uploadedFileFactory;
    }


    public function getServerParams(): array
    {
        return $_SERVER ?? [];
    }

    public function getCookieParams(): array
    {
        return $this->cookies ?? ($this->swooleRequest->cookie ?? []);
    }

    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        $new = clone $this;
        $new->cookies = $cookies;
        return $new;
    }

    public function getQueryParams(): array
    {
        return $this->query ?? ($this->swooleRequest->get ?? []);
    }

    public function withQueryParams(array $query): ServerRequestInterface
    {
        $new = clone $this;
        $new->query = $query;
        return $new;
    }

    public function getUploadedFiles(): array
    {
        if (isset($this->files)) {
            return $this->files;
        }

        $files = [];

        if ($this->swooleRequest->files) {
            foreach ($this->swooleRequest->files as $name => $fileData) {
                $files[$name] = $this->uploadedFileFactory->createUploadedFile(
                    $this->streamFactory->createStreamFromFile($fileData['tmp_name']),
                    $fileData['size'],
                    $fileData['error'],
                    $fileData['name'],
                    $fileData['type']
                );
            }
        }

        return $files;
    }

    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        $new = clone $this;
        $new->files = $uploadedFiles;
        return $new;
    }

    public function getParsedBody(): array|null|object
    {
        if (!empty($this->parsedBody)) {
            return $this->parsedBody;
        }

        if (!empty($this->swooleRequest->post)) {
            return $this->swooleRequest->post;
        }


        return null;
    }

    public function withParsedBody($data): ServerRequestInterface
    {
        if (!is_object($data) && !is_array($data)) {
            throw new \InvalidArgumentException('Unsupported argument type');
        }

        $new = clone $this;
        $new->parsedBody = $data;
        return $new;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute(string $name, mixed $value): ServerRequestInterface
    {
        $new = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }

    public function withoutAttribute(string $name): ServerRequestInterface
    {
        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }
}
