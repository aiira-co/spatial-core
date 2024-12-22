<?php

declare(strict_types=1);

namespace Spatial\Swoole;


use Spatial\Core\AppHandler;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Spatial\Core\App;

use Http\Factory\Guzzle\UriFactory;
use Http\Factory\Guzzle\StreamFactory;
use Http\Factory\Guzzle\UploadedFileFactory;

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
     * BridgeManager constructor.
     * @param App $app
     * @param Bridge\RequestTransformerInterface|null $requestTransformer
     * @param Bridge\ResponseMergerInterface|null $responseMerger
     */
    public function __construct(
        App $app,
        Bridge\RequestTransformerInterface $requestTransformer = null,
        Bridge\ResponseMergerInterface $responseMerger = null
    ) {
        $this->app = $app;
        $this->requestTransformer = $requestTransformer;
        $this->responseMerger = $responseMerger ?: new Bridge\ResponseMerger();
    }

    /**
     * @param Request $swooleRequest
     * @param Response $swooleResponse
     * @return Response
     * @throws \JsonException
     */
    public function process(
        Request $swooleRequest,
        Response $swooleResponse,
        Server $swooleServer
    ): Response {


//        check for reserved routes
//        - /metrics, /api-docs, health
        if( $swooleRequest->server['request_uri'] == '/metrics'){
            $swooleResponse->header("Content-Type", "text/plain");
            $swooleResponse->write($swooleServer->stats(\OPENSWOOLE_STATS_OPENMETRICS));
            return $swooleResponse;
        }

        // use prod to disable this docs
        if( $swooleRequest->server['request_uri'] == '/api-docs'){
            $swooleResponse->header("Content-Type", "text/html; charset=utf-8");
            $swooleResponse->write($this->app->getRouteTable());
            return $swooleResponse;

        }

        if( $swooleRequest->server['request_uri'] == '/health-check'){
            $swooleResponse->header("Content-Type", "application/json");
            $swooleResponse->write(json_encode(['status' => 200,'message' => 'OK']));
            return $swooleResponse;
        }

        $psr7Request = new Bridge\ServerRequestTransformer(
            new UriFactory(),
            new StreamFactory(),
            new UploadedFileFactory(),
            $swooleRequest
        );

//        print_r((string)$psr7Request->getBody());
//        $psr7Request = clone ($this->requestTransformer)->toSwoole($swooleRequest);
        $psr7Response = $this->app->process($psr7Request, new AppHandler());

        return $this->responseMerger->toSwoole($psr7Response, $swooleResponse);
    }

}