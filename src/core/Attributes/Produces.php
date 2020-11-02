<?php

declare(strict_types=1);

namespace Spatial\Core\Attributes;

use Attribute;

/**
 * Class Bind
 * Specifies data types that an action returns.
 * @package Spatial\Core\Attributes
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Produces
{
    /**
     * @var string
     * Gets or sets the supported response content types. Used to set ContentTypes.
     */
    public string $contentTypes;
    public int $order;
    public int $statusCode;
    public object $type;

    public function __construct()
    {
    }

    /**
     * @param mixed $resultExecutionContext
     */
    public function onResultExecuting(mixed $resultExecutionContext)
    {
    }

    /**
     * @param mixed $resultExecutionContext
     */
    public function onResultExecuted(mixed $resultExecutionContext)
    {
    }

    /**
     * @param $mediaTypeCollection
     */
    public function setContestTypes($mediaTypeCollection)
    {
    }


}