<?php
declare(strict_types=1);

namespace Spatial\Common\Processor;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Spatial\Core\App;
use ReflectionClass;

class MiddlewareProcessor implements MiddlewareInterface
{
    /**
     * @param <string,ReflectionClass>[] $middlewareProviders
     * @return void
     */
    public function __construct(private readonly array $middlewareProviders){
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler):ResponseInterface{
        // 1. Separate middlewares from other providers
        $middlewares = [];

        foreach ($this->middlewareProviders as $providerClassName => $providerReflectionClass) {
            try {
                if ($providerReflectionClass->implementsInterface(MiddlewareInterface::class)) {
                    $middlewares[] = App::diContainer()->get($providerClassName);
                }
            } catch (\Exception $e) {
                // Log or handle the error if a provider class is not found.
                // For this example, we'll just continue.
            }
        }

        // 2. Build the middleware chain.
        // The core application logic is the final step in the chain.
        $coreLogic =  function (ServerRequestInterface $request) use($handler): ResponseInterface {
            return $handler->handle($request);
        };

        $pipeline = array_reduce(
            array_reverse($middlewares),
            function (callable $next, MiddlewareInterface $middleware) {
                return function (ServerRequestInterface $request) use ($middleware, $next): ResponseInterface {
                    return $middleware->process($request, new class($next) implements RequestHandlerInterface {
                        private $next;
                        public function __construct(callable $next) { $this->next = $next; }
                        public function handle(ServerRequestInterface $request): ResponseInterface {
                            return ($this->next)($request); // delegate to the next middleware
                        }
                    });
                };
            },
            $coreLogic
        );

        // 3. Execute the middleware chain.
//        echo "Starting request processing...\n";
        //        echo "Finished request processing.\n";

        return $pipeline($request);
    }
}