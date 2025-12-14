<?php

declare(strict_types=1);

namespace Spatial\Events;

use Psr\Log\LoggerInterface;
use ReflectionClass;
use Spatial\Core\App;
use Spatial\Events\Attributes\Listener;

/**
 * EventDispatcher
 * 
 * Dispatches domain events to registered listeners.
 * Automatically discovers listeners via the #[Listener] attribute.
 * 
 * @package Spatial\Events
 */
class EventDispatcher
{
    private array $listeners = [];
    private bool $discovered = false;

    public function __construct(
        private ?LoggerInterface $logger = null,
        private ?string $listenersPath = null
    ) {}

    /**
     * Dispatch an event to all registered listeners.
     *
     * @param object $event The event object
     * @return void
     */
    public function dispatch(object $event): void
    {
        $this->discoverListeners();

        $eventClass = get_class($event);
        
        if (!isset($this->listeners[$eventClass])) {
            $this->logger?->debug("No listeners for event: {$eventClass}");
            return;
        }

        // Sort by priority (higher first)
        $listeners = $this->listeners[$eventClass];
        usort($listeners, fn($a, $b) => $b['priority'] <=> $a['priority']);

        foreach ($listeners as $listenerConfig) {
            $this->invokeListener($listenerConfig, $event);
        }
    }

    /**
     * Dispatch an event asynchronously using coroutines.
     *
     * @param object $event The event object
     * @return void
     */
    public function dispatchAsync(object $event): void
    {
        if (function_exists('go')) {
            go(fn() => $this->dispatch($event));
        } else {
            $this->dispatch($event);
        }
    }

    /**
     * Register a listener manually.
     *
     * @param string $eventClass Event class name
     * @param callable|string $listener Listener callable or class name
     * @param int $priority Higher priority listeners are called first
     * @return self
     */
    public function listen(string $eventClass, callable|string $listener, int $priority = 0): self
    {
        $this->listeners[$eventClass][] = [
            'listener' => $listener,
            'priority' => $priority,
            'async' => false
        ];

        return $this;
    }

    /**
     * Auto-discover listeners from the listeners path.
     */
    private function discoverListeners(): void
    {
        if ($this->discovered) {
            return;
        }

        $path = $this->listenersPath ?? getcwd() . '/src/core/Application/Listeners';
        
        if (!is_dir($path)) {
            $this->discovered = true;
            return;
        }

        $this->scanDirectory($path);
        $this->discovered = true;
    }

    /**
     * Scan a directory for listener classes.
     */
    private function scanDirectory(string $path): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->registerListenerFromFile($file->getPathname());
            }
        }
    }

    /**
     * Register a listener from a PHP file.
     */
    private function registerListenerFromFile(string $filePath): void
    {
        $content = file_get_contents($filePath);
        
        if (!preg_match('/namespace\s+([^;]+);/', $content, $nsMatch)) {
            return;
        }
        if (!preg_match('/class\s+(\w+)/', $content, $classMatch)) {
            return;
        }

        $fqcn = $nsMatch[1] . '\\' . $classMatch[1];

        if (!class_exists($fqcn)) {
            require_once $filePath;
        }

        if (!class_exists($fqcn)) {
            return;
        }

        try {
            $reflection = new ReflectionClass($fqcn);
            $attributes = $reflection->getAttributes(Listener::class);

            foreach ($attributes as $attr) {
                $listener = $attr->newInstance();
                
                $this->listeners[$listener->event][] = [
                    'listener' => $fqcn,
                    'priority' => $listener->priority,
                    'async' => $listener->async
                ];

                $this->logger?->debug("Registered listener: {$fqcn} for {$listener->event}");
            }
        } catch (\Exception $e) {
            $this->logger?->warning("Failed to register listener from {$filePath}: {$e->getMessage()}");
        }
    }

    /**
     * Invoke a listener with the event.
     */
    private function invokeListener(array $config, object $event): void
    {
        $listener = $config['listener'];

        try {
            if (is_callable($listener)) {
                $listener($event);
            } elseif (is_string($listener)) {
                // Resolve from DI container
                $instance = App::diContainer()->get($listener);
                $instance->handle($event);
            }
        } catch (\Exception $e) {
            $this->logger?->error("Listener failed: {$e->getMessage()}", [
                'listener' => is_string($listener) ? $listener : 'callable',
                'event' => get_class($event)
            ]);
        }
    }

    /**
     * Get all registered listeners.
     */
    public function getListeners(): array
    {
        $this->discoverListeners();
        return $this->listeners;
    }
}
