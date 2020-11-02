<?php

declare(strict_types=1);

namespace Spatial\Core;

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
     * @var string
     * Gets the HttpRequest for the executing action.
     */
    public string $request;
    /**
     * @var string
     * Gets the HttpResponse for the executing action.
     */
    public string $response;
    /**
     * @var string
     * Gets the RouteData for the executing action.
     */
    public string $routeData;
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
}