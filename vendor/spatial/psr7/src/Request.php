<?php

namespace Spatial\Psr7;

use GuzzleHttp\Psr7\ServerRequest;

/**
 * Server-side HTTP request
 *
 * Extends the Request definition to add methods for accessing incoming data,
 * specifically server parameters, cookies, matched path parameters, query
 * string arguments, body parameters, and upload file information.
 *
 * "Attributes" are discovered via decomposing the request (and usually
 * specifically the URI path), and typically will be injected by the application.
 *
 * Requests are considered immutable; all methods that might change state are
 * implemented such that they retain the internal state of the current
 * message and return a new instance that contains the changed state.
 */
class Request extends ServerRequest
{
    public function __construct()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $httpRequest = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'];
        } else {
            $httpRequest = $_SERVER['REQUEST_METHOD'];
        }
        parent::__construct($httpRequest, $_SERVER['REQUEST_URI']);
    }
}
