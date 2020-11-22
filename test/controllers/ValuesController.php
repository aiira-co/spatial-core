<?php

namespace Spatial\Api\Controllers;

use Spatial\Core\Attributes\ApiController;
use Spatial\Core\Attributes\Area;
use Spatial\Core\Attributes\Route;
use Spatial\Psr7\Response;

/**
 * ValuesController Class exists in the Api\Controllers namespace
 * A Controller represets the individual URIs client apps access to interact with data
 * URI:  https://api.com/values
 *
 * @category Controller
 */
#[ApiController]
#[Area('aiira')]
#[Route('[area]/[controller]/secret/[action]')]
class ValuesController
{
    /**
     * Use constructor to Inject or instanciate dependecies
     */
    public function __construct()
    {
        $this->response = new Response();
    }

    /**
     * The Method httpGet() called to handle a GET request
     * URI: POST: https://api.com/values
     * URI: POST: https://api.com/values/2 ,the number 2 in the uri is passed as int ...$id to the method
     */
    public function httpGet(int ...$id): ?Response
    {
        $data = [
            'value1',
            'value2',
        ];
        $payload = json_encode($data);
        $this->response->getBody()->write($payload);
        return $this->response;
    }

    /**
     * The Method httpPost() called to handle a POST request
     * This method requires a body(json) which is passed as the var array $content
     * URI: POST: https://api.com/values
     */
    #[Area('studio')]
    public function httpPost(
        string $content
    ): Response {
        $postId = null;

        // code here
        $data = ['success' => true, 'alert' => 'We have it at post', 'id' => $postId];
        $payload = json_encode($data);
        $this->response->getBody()->write($payload);
        return $this->response;
    }

    /**
     * The Method httpPut() called to handle a PUT request
     * This method requires a body(json) which is passed as the var array $content and
     * An id as part of the uri.
     * URI: POST: https://api.com/values/2 the number 2 in the uri is passed as int $id to the method
     */
    public function httpPut(string $content, int $id): Response
    {
        // code here
        $data = ['success' => true, 'alert' => 'We have it at put'];
        $payload = json_encode($data); // code here
        $data = ['success' => true, 'alert' => 'We have it at put'];
        $payload = json_encode($data);
        $this->response->getBody()->write($payload);
        return $this->response;
        $this->response->getBody()->write($payload);
        return $this->response;
    }

    /**
     * The Method httpDelete() called to handle a DELETE request
     * URI: POST: https://api.com/values/2 ,the number 2 in the uri is passed as int ...$id to the method
     */
    public function httpDelete(int $id): Response
    {
        // code here
        $data = ['id' => $id];
        $payload = json_encode($data);
        $this->response->getBody()->write($payload);
        return $this->response;
    }
}
