<?php


namespace Spatial\Api\ArticleApi\Controller;

use Psr\Http\Message\ResponseInterface;
use Spatial\Common\HttpAttributes\HttpGet;
use Spatial\Core\Attributes\ApiController;
use Spatial\Core\Attributes\Area;
use Spatial\Core\Attributes\Route;
use Spatial\Psr7\Response;

#[ApiController]
#[Area('article-api')]
#[Route('[area]/[controller]/[action]')]
class ArticleController
{
    /**
     * @var Response
     */
    private Response $response;

    /**
     * Use constructor to Inject or instanciate dependecies
     */
    public function __construct()
    {
        $this->response = new Response();
    }

    #[HttpGet('{id:int}')]
    public function getSingleArticle(
        int $id
    ): ResponseInterface {
        $data = [$id];
        try {
            $payload = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $payload = 'Error Occurred: ' . $e;
        }
        $this->response->getBody()?->write($payload);
        return $this->response;
    }


    #[HttpGet]
    public function getAllArticles(): ResponseInterface
    {
        $data = [
            'value1',
            'value2',
        ];
        try {
            $payload = json_encode($data, JSON_THROW_ON_ERROR);
            $this->response->getBody()?->write($payload);
        } catch (\JsonException $e) {
            $payload = 'Error Occurred: ' . $e;
        }
        $this->response->getBody()?->write($payload);

        return $this->response;
    }
}