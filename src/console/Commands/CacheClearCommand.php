<?php

declare(strict_types=1);

namespace Spatial\Console\Commands;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Cache Clear Command
 * 
 * Clears all cached data.
 * 
 * @example php spatial cache:clear
 * 
 * @package Spatial\Console\Commands
 */
class CacheClearCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'cache:clear';
    }

    public function getDescription(): string
    {
        return 'Clear all cached data';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        $cacheDir = $this->getBasePath() . '/var/cache';
        
        if (!is_dir($cacheDir)) {
            $this->output("No cache directory found.");
            return 0;
        }

        $count = 0;
        $files = glob($cacheDir . '/*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if (unlink($file)) {
                    $count++;
                    $this->output("Deleted: " . basename($file));
                }
            }
        }

        if ($count > 0) {
            $this->success("Cleared {$count} cached file(s).");
        } else {
            $this->output("Cache is already empty.");
        }

        return 0;
    }
}
