<?php
declare(strict_types=1);

namespace Spatial\Swoole\Bridge;


use Psr\Http\Message\ResponseInterface;
use OpenSwoole\Http\Response;
use Dflydev\FigCookies\SetCookies;

class ResponseMerger
{
    const FSTAT_MODE_S_IFIFO = 0010000;

    public function toSwoole(ResponseInterface $psrResponse, Response $swooleResponse): Response
    {
        $swooleResponse->status($psrResponse->getStatusCode());
        
        $this->copyHeaders($psrResponse, $swooleResponse);
        $this->copyBody($psrResponse, $swooleResponse);

        return $swooleResponse;
    }

    private function copyHeaders(ResponseInterface $psrResponse, Response $swooleResponse): void
    {
        if (empty($psrResponse->getHeaders())) {
            return;
        }

        $this->setCookies($swooleResponse, $psrResponse);

        $psrResponse = $psrResponse->withoutHeader('Set-Cookie');

        foreach ($psrResponse->getHeaders() as $key => $headerArray) {
            $swooleResponse->header((string)$key, implode('; ', $headerArray));
        }
    }

    private function setCookies(Response $swooleResponse, ResponseInterface $psrResponse): void
    {
        if (!$psrResponse->hasHeader('Set-Cookie')) {
            return;
        }

        $setCookies = SetCookies::fromSetCookieStrings($psrResponse->getHeader('Set-Cookie'));
        foreach ($setCookies->getAll() as $setCookie) {
            $swooleResponse->cookie(
                $setCookie->getName(),
                $setCookie->getValue(),
                $setCookie->getExpires(),
                $setCookie->getPath(),
                $setCookie->getDomain(),
                $setCookie->getSecure(),
                $setCookie->getHttpOnly()
            );
        }
    }

    private function copyBody(ResponseInterface $psrResponse, Response $swooleResponse): void
    {
        if ($psrResponse->getBody()->getSize() == 0) {
            $this->copyBodyIfIsAPipe($psrResponse, $swooleResponse);
            return;
        }

        if ($psrResponse->getBody()->isSeekable()) {
            $psrResponse->getBody()->rewind();
        }

        $swooleResponse->write($psrResponse->getBody()->getContents());
    }

    private function copyBodyIfIsAPipe(ResponseInterface $psrResponse, Response $swooleResponse): void
    {
        $resource = $psrResponse->getBody()->detach();

        if (!is_resource($resource)) {
            return;
        }

        if ($this->isPipe($resource)) {
            while (!feof($resource)) {
                $buff = fread($resource, 8192);
                !empty($buff) && $swooleResponse->write($buff);
            }
            pclose($resource);
        }
    }

    private function isPipe($resource): bool
    {
        $stat = fstat($resource);
        return (isset($stat['mode']) && ($stat['mode'] & self::FSTAT_MODE_S_IFIFO) !== 0);
    }
}