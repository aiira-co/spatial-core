<?php

declare(strict_types=1);

namespace Spatial\Core;

use Psr\Http\Message\ServerRequestInterface;
use Spatial\Mediator\Mediator;

/**
 * Class ControllerBase
 * For Web api
 * @package Spatial\Core
 */
abstract class ControllerBase
{
    /**
     * @var string
     * Gets or sets the ControllerContext.
     */
    public string $controllerContext;

    /**
     * @var string
     * Gets the HttpContext for the executing action.
     */
    public string $httpContext;
    /**
     * @var string
     * Gets or sets the IModelMetadataProvider.
     */
    public string $metadataProvider;

    /**
     * @var string
     * Gets or sets the IModelBinderFactory.
     */
    public string $modelBinderFactory;
    /**
     * @var string
     * Gets the ModelStateDictionary that contains the state of the model and of model-binding validation.
     */
    public string $modelState;

    /**
     * @var string
     * Gets or sets the IObjectModelValidator.
     */
    public string $objectValidator;


    public string $problemDetailsFactory;
    /**
     * @var ServerRequestInterface
     * Gets the HttpRequest for the executing action.
     */
    public ServerRequestInterface $request;
    /**
     * @var string
     * Gets the HttpResponse for the executing action.
     */
    public string $response;
    /**
     * @var array
     * Gets the RouteData for the executing action.
     */
    public array $routeData;
    /**
     * @var string
     * Gets or sets the IUrlHelper.
     */
    public string $url;
    /**
     * @var string
     * Gets the ClaimsPrincipal for user associated with the executing action.
     */
    public string $user;

    /**
     * Use constructor to Inject or instantiate dependencies
     * @param Mediator $mediator
     */
    public function __construct(protected Mediator $mediator)
    {
    }

    public function __invoke(ServerRequestInterface $request): void
    {
        $this->request = $request;
        $this->routeData = $request->getQueryParams();

        // the request will be spread to the cqrs request
        $this->mediator->request = $request;
    }

    // =====================================================
    // Response Helper Methods
    // =====================================================

    /**
     * Returns an HTTP 200 OK response with the given data.
     *
     * @param mixed $data The data to return
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function ok(mixed $data = null): \Psr\Http\Message\ResponseInterface
    {
        return $this->json([
            'status' => 200,
            'success' => true,
            'data' => $data
        ], 200);
    }

    /**
     * Returns an HTTP 201 Created response.
     *
     * @param mixed $data The created resource data
     * @param string|null $location Optional Location header URI
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function created(mixed $data = null, ?string $location = null): \Psr\Http\Message\ResponseInterface
    {
        $response = $this->json([
            'status' => 201,
            'success' => true,
            'data' => $data
        ], 201);

        if ($location !== null) {
            $response = $response->withHeader('Location', $location);
        }

        return $response;
    }

    /**
     * Returns an HTTP 204 No Content response.
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function noContent(): \Psr\Http\Message\ResponseInterface
    {
        return new \GuzzleHttp\Psr7\Response(204);
    }

    /**
     * Returns an HTTP 400 Bad Request response.
     *
     * @param string|array $errors Error message(s)
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function badRequest(string|array $errors): \Psr\Http\Message\ResponseInterface
    {
        return $this->json([
            'status' => 400,
            'success' => false,
            'errors' => is_array($errors) ? $errors : [$errors]
        ], 400);
    }

    /**
     * Returns an HTTP 404 Not Found response.
     *
     * @param string $message Error message
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function notFound(string $message = 'Resource not found'): \Psr\Http\Message\ResponseInterface
    {
        return $this->json([
            'status' => 404,
            'success' => false,
            'error' => $message
        ], 404);
    }

    /**
     * Returns an HTTP 401 Unauthorized response.
     *
     * @param string $message Error message
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function unauthorized(string $message = 'Unauthorized'): \Psr\Http\Message\ResponseInterface
    {
        return $this->json([
            'status' => 401,
            'success' => false,
            'error' => $message
        ], 401);
    }

    /**
     * Returns an HTTP 403 Forbidden response.
     *
     * @param string $message Error message
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function forbidden(string $message = 'Forbidden'): \Psr\Http\Message\ResponseInterface
    {
        return $this->json([
            'status' => 403,
            'success' => false,
            'error' => $message
        ], 403);
    }

    /**
     * Returns a JSON response with the given data and status code.
     *
     * @param mixed $data The data to encode as JSON
     * @param int $status HTTP status code
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function json(mixed $data, int $status = 200): \Psr\Http\Message\ResponseInterface
    {
        $response = new \GuzzleHttp\Psr7\Response($status);
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // =====================================================
    // Validation Helper Methods
    // =====================================================

    /**
     * Validate a DTO object against its validation attributes.
     * Returns null if valid, or a BadRequest response if invalid.
     *
     * @param object $dto The DTO to validate
     * @return \Psr\Http\Message\ResponseInterface|null BadRequest response if invalid, null if valid
     * 
     * @example
     * if ($response = $this->validate($createUserDto)) {
     *     return $response; // Returns 400 with validation errors
     * }
     * // Continue with valid DTO...
     */
    protected function validate(object $dto): ?\Psr\Http\Message\ResponseInterface
    {
        $validator = new \Spatial\Common\ValidationAttributes\Validator();
        $result = $validator->validate($dto);

        if (!$result->isValid()) {
            return $this->badRequest($result->getErrors());
        }

        return null;
    }

    /**
     * Validate an array against a DTO class.
     * Returns null if valid, or a BadRequest response if invalid.
     *
     * @param array $data The data to validate
     * @param string $dtoClass The DTO class to validate against
     * @return \Psr\Http\Message\ResponseInterface|null BadRequest response if invalid, null if valid
     */
    protected function validateArray(array $data, string $dtoClass): ?\Psr\Http\Message\ResponseInterface
    {
        $validator = new \Spatial\Common\ValidationAttributes\Validator();
        $result = $validator->validateArray($data, $dtoClass);

        if (!$result->isValid()) {
            return $this->badRequest($result->getErrors());
        }

        return null;
    }
}