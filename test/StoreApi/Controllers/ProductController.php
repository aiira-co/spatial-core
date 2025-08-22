<?php

namespace Spatial\Api\StoreApi\Controllers;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Spatial\Common\BindSourceAttributes\FromBody;
use Spatial\Common\BindSourceAttributes\FromQuery;
use Spatial\Common\BindSourceAttributes\FromRoute;
use Spatial\Common\HttpAttributes\HttpGet;
use Spatial\Common\HttpAttributes\HttpPost;
use Spatial\Common\HttpAttributes\HttpPut;
use Spatial\Core\Attributes\ApiController;
use Spatial\Core\Attributes\Area;
use Spatial\Core\Attributes\Route;
use Spatial\Psr7\Response;
use Psr\Log\LoggerInterface;
use OpenTelemetry\API\Trace\TracerInterface;

/**
 * ValuesController Class exists in the Api\Controllers namespace
 * A Controller represets the individual URIs client apps access to interact with data
 * URI:  https://api.com/values
 *
 * @category Controller
 */
#[ApiController]
#[Area('store-api')]
#[Route('[area]/products/')]
class ProductController
{

    private Response $response;
    private LoggerInterface $logger;
    private TracerInterface $tracer;

    /**
     * Use constructor to Inject or instantiate dependencies
     */
    public function __construct(LoggerInterface $logger, TracerInterface $tracer)
    {
        $this->response = new Response();
        $this->logger = $logger;
        $this->tracer = $tracer;
    }

    /**
     * The Method httpGet() called to handle a GET request
     * URI: POST: https://api.com/values
     * URI: POST: https://api.com/values/2 ,the number 2 in the uri is passed as int ...$id to the method
     * @throws JsonException
     */
    #[HttpGet]
    public function productList(): ResponseInterface
    {
        $span = $this->tracer->spanBuilder('ProductController::productList')->startSpan();

        $this->logger->info('Fetching product list');

        $data = [
            'app api',
            'value1',
            'value2'
        ];
        $payload = json_encode($data, JSON_THROW_ON_ERROR);

        $this->response
            ->getBody()
            ?->write(json_encode($payload, JSON_THROW_ON_ERROR));

        $span->end();

        return $this->response;
        // ->withHeader('Content-Type', 'application/json');
        // ->withHeader('Content-Disposition', 'attachment;filename="downloaded.pdf"');
    }

    #[HttpGet('{id:int}')]
    public function getProduct(
        #[FromRoute] int $id,
        #[FromQuery] string $name
    ): Response {
        $data = [
            'app api',
            'value1',
            'value2',
            $id,
            $name
        ];
        $payload = json_encode($data, JSON_THROW_ON_ERROR);

        $this->response->getBody()->write($payload);
        return $this->response;
        // ->withHeader('Content-Type', 'application/json');
        // ->withHeader('Content-Disposition', 'attachment;filename="downloaded.pdf"');
    }


    #[HttpPost]
    public function createProduct(
        #[FromBody] string $content
    ): Response {
        // code here
        $data = ['success' => true, 'alert' => 'We have it at post', 'field' => $content];
        $payload = json_encode($data, JSON_THROW_ON_ERROR);
        $this->response->getBody()->write($payload);
        return $this->response;
    }


    #[Route('edit')]
    #[HttpPut('{id:int}')]
    public function editProduct(
        #[FromBody] string $content,
        int $id
    ): Response {
        // code here
        $data = ['success' => true, 'alert' => 'We have it at put', 'id' => $id, 'field' => $content];
        $payload = json_encode($data, JSON_THROW_ON_ERROR);
        $this->response->getBody()->write($payload);
        return $this->response;
    }
}
