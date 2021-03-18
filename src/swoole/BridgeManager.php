<?php

declare(strict_types=1);

namespace Spatial\Swoole;


use Spatial\Core\AppHandler;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Spatial\Core\App;

use Http\Factory\Guzzle\UriFactory;
use Http\Factory\Guzzle\StreamFactory;

class BridgeManager
{

    /**
     * @var App
     */
    private App $app;

    /**
     * @var Bridge\RequestTransformerInterface
     */
    private $requestTransformer;

    /**
     * @var Bridge\ResponseMergerInterface
     */
    private $responseMerger;

    /**
     * @param App $app
     * @param Bridge\RequestTransformerInterface $requestTransformer
     * @param Bridge\ResponseMergerInterface $responseMerger
     */
    public function __construct(
        App $app,
        Bridge\RequestTransformerInterface $requestTransformer = null,
        Bridge\ResponseMergerInterface $responseMerger = null
    ) {
        $this->app = $app;
        $this->requestTransformer = $requestTransformer ?: new Bridge\RequestTransformer(
            new UriFactor(),
            new StreamFactory()
        );
        $this->responseMerger = $responseMerger ?: new Bridge\ResponseMerger();
    }

    /**
     * @param swoole_http_request $swooleRequest
     * @param swoole_http_response $swooleResponse
     *
     * @return swoole_http_response
     */
    public function process(
        Request $swooleRequest,
        Response $swooleResponse
    ): Response {
        $psr7Request = $this->requestTransformer->toSwoole($swooleRequest);
        $psr7Response = $this->app->process($psr7Request, new AppHandler());

        return $this->responseMerger->toSwoole($psr7Response, $swooleResponse);
    }

}