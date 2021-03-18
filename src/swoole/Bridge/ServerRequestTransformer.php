<?php


namespace Spatial\Swoole\Bridge;


use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Swoole\Http\Request as SwooleRequest;

class ServerRequestTransformer extends RequestTransformer implements ServerRequestInterface
{
    public $attributes = [];
    protected SwooleRequest $swooleRequest;

    public function __construct(
        UriFactoryInterface $uriFactory,
        StreamFactoryInterface $streamFactory,
        UploadedFileFactoryInterface $uploadedFileFactory
    ) {
        parent::__construct($uriFactory, $streamFactory);
        $this->uploadedFileFactory = $uploadedFileFactory;
    }

    /**
     * @param SwooleRequest $swooleRequest
     * @return $this
     */
    public function toSwoole(SwooleRequest $swooleRequest): self
    {
        parent::toSwoole($swooleRequest);
        return $this;
    }

    public function getServerParams()
    {
        return $_SERVER ?? [];
    }

    public function getCookieParams()
    {
        return $this->cookies ?? ($this->swooleRequest->cookie ?? []);
    }

    public function withCookieParams(array $cookies)
    {
        $new = clone $this;
        $new->cookies = $cookies;
        return $new;
    }

    public function getQueryParams()
    {
        return $this->query ?? ($this->swooleRequest->get ?? []);
    }

    public function withQueryParams(array $query)
    {
        $new = clone $this;
        $new->query = $query;
        return $new;
    }

    public function getUploadedFiles()
    {
        if (isset($this->files)) {
            return $this->files;
        }

        $files = [];

        foreach ($this->swooleRequest->files as $name => $fileData) {
            $files[$name] = $this->uploadedFileFactory->createUploadedFile(
                $this->streamFactory->createStreamFromFile($fileData['tmp_name']),
                $fileData['size'],
                $fileData['error'],
                $fileData['name'],
                $fileData['type']
            );
        }

        return $files;
    }

    public function withUploadedFiles(array $uploadedFiles)
    {
        $new = clone $this;
        $new->files = $uploadedFiles;
        return $new;
    }

    public function getParsedBody()
    {
        if (!empty($this->parsedBody)) {
            return $this->parsedBody;
        }

        if (!empty($this->swooleRequest->post)) {
            return $this->swooleRequest->post;
        }

        return null;
    }

    public function withParsedBody($data)
    {
        if (!is_object($data) && !is_array($data)) {
            throw new \InvalidArgumentException('Unsupported argument type');
        }

        $new = clone $this;
        $new->parsedBody = $data;
        return $new;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function getAttribute($name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute($name, $value)
    {
        $new = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }

    public function withoutAttribute($name)
    {
        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }
}