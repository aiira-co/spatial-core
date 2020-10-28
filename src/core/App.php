<?php


namespace Spatial\Core;


use JetBrains\PhpStorm\Pure;
use ReflectionClass;
use ReflectionException;

class App
{

    /**
     * @var array|string[]
     */
    private array $httpVerbs = [
        'HttpGet',
        'HttpPost',
        'HttpPut',
        'HttpDelete',
        'HttpHead',
        'HttpPatch',
    ];

    /**
     * For Conventional routing -> pattern: "{controller=Home}/{action=Index}/{id?}"
     * For Attributes -> [Route("[controller]/[action]")]
     * @var array|string[]
     */
    private array $reservedRoutingNames = [
        'action',
        'area',
        'controller',
        'handler',
        'page'
    ];

    private array $patternArray;
    private object $defaults;

    public array $status = ['code' => 401, 'reason' => 'Unauthorized'];


    #[Pure]
    public function __construct(
        string $uri
    ) {
        $this->patternArray['uri'] = explode('/', trim(urldecode($uri), '/'));
        $this->patternArray['count'] = count($this->patternArray['uri']);
    }


    /**
     * @param array $uriArr
     * @param string $token
     * @return bool
     */
    public function isUriRoute(array $uriArr, string $token = '{}'): bool
    {
        $isMatch = true;
        $isToken = false;

        for ($i = 0; $i < $this->patternArray['count']; $i++) {
            if (str_starts_with($this->patternArray['uri'][$i][0], $token[0])) {
                $isToken = true;

                if (!isset($uriArr[$i]) || !($this->patternArray['uri'][$i] === $uriArr[$i])) {
                    $isMatch = false;
                    $isToken = false;
                    break;
                }
            }


            if ($isToken) {
                $placeholder = str_replace(
                    [$token[0], $token[1]],
                    '',
                    $this->patternArray['uri'][$i]
                );
//                verify token for only [], {} can be used for everything
                if ($token === '[]' && !in_array($placeholder, $this->reservedRoutingNames, true)) {
                    $this->status['reason'] = $placeholder . ' is not a reserved routing token';
                    break;
                }
            } else {
                $placeholder = $this->patternArray['uri'][$i];
            }

            // check to see if its the last placeholder
            // AND if the placeholder is prefixed with `...`
            // meaning the placeholder is an array of the rest of the uriArr member
            if ($i === ($this->patternArray['count'] - 1) && str_starts_with($placeholder, '...')) {
                $placeholder = ltrim($placeholder, '/././.');
                if (isset($uriArr[$i])) {
                    for ($uri = $i, $uriMax = count($uriArr); $uri < $uriMax; $uri++) {
                        $this->replaceRouteToken($placeholder, $uriArr[$uri], true);
                    }
                }
                break;
            }
            $this->replaceRouteToken($placeholder, $uriArr[$i] ?? null);
        }
        return $isMatch;
    }

    /**
     * @param string $placeholderString
     * @param string|null $uriValue
     * @param bool $isList
     */
    private function replaceRouteToken(string $placeholderString, ?string $uriValue, bool $isList = false): void
    {
        // separate constraints
        $placeholder = explode(':', $placeholderString);

        $value = $uriValue ?? $this->defaults->{$placeholder[0]} ?? null;

        if (isset($placeholder[1])) {
            $typeValue = explode('=', $placeholder[1]);
            if (isset($typeValue[1])) {
                $value = $value ?? $typeValue[1];
            }
            if ($value !== null) {
                $value = match ($placeholder[1]) {
                    'int' => (int)$value,
                    'bool' => (bool)$value,
                    'array' => (array)$value,
                    'float' => (float)$value,
                    'object' => (object)$value,
                    default => (string)$value,
                };
            }
        }
        // set value
        $isList ?
            $this->defaults->{$placeholder[0]}[] = $value :
            $this->defaults->{$placeholder[0]} = $value;
    }


    /**
     * @param string $controllerClass
     * @return array
     * @throws ReflectionException
     */
    private function resolveController(string $controllerClass): array
    {
        $reflectionClass = new ReflectionClass($controllerClass);

        $listeners = [];

        foreach ($reflectionClass->getMethods() as $method) {
            $attributes = $method->getAttributes();

            foreach ($attributes as $attribute) {
                $listener = $attribute->newInstance();

                $listeners[] = [
                    // The event that's configured on the attribute
                    $listener->event,

                    // The listener for this event
                    [$controllerClass, $method->getName()],
                ];
            }
        }

        return $listeners;
    }
}