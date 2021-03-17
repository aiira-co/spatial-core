<?php

/**
 * Copyright (c) 2021 Aiira Inc.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the names of the copyright holders nor the names of the
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package     [ aiira ]
 * @subpackage  [ spatial ]
 * @author      Owusu-Afriyie Kofi <koathecedi@gmail.com>
 * @copyright   2021 Aiira Inc.
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link        http://aiira.co
 * @version     @@3.00@@
 */

declare(strict_types=1);


use Spatial\Api\TestModule;
use Spatial\Core\App;
use Spatial\Swoole\BridgeManager;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;


const DS = DIRECTORY_SEPARATOR;
require_once __DIR__ . DS . '..' . DS . 'vendor' . DS . 'autoload.php';

/**
 * This is how you would normally bootstrap your Spatial application
 * For the sake of demonstration, we also add a simple middleware
 * to check that the entire app stack is being setup and executed
 * properly.
 */
$app = new App();

try {
    $app->boot(TestModule::class);
} catch (ReflectionException | Exception $e) {
}


/**
 * CGI NGNIX HttpServer
 */

//$response = $app->processX();
//http_response_code($response->getStatusCode());
//echo $response->getBody();

/**
 *
 * We instanciate the BridgeManager(this library)
 */
$bridgeManager = new BridgeManager($app);

/**
 * We start the Swoole server
 */
$http = new Server("0.0.0.0", 8081);

/**
 * We register the on "start" event
 */
$http->on(
    "start",
    function (Server $server) {
        echo sprintf('Swoole http server is started at http://%s:%s', $server->host, $server->port), PHP_EOL;
    }
);

/**
 * We register the on "request event, which will use the BridgeManager to transform request, process it
 * as a Spatial request and merge back the response
 *
 */
$http->on(
    "request",
    function (Request $request, Response $response) use ($bridgeManager) {
        $bridgeManager->process($request, $response)->end();
    }
);

$http->start();