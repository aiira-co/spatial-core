<?php

declare(strict_types=1);

namespace Spatial\Database;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Seeder Base Class
 * 
 * Base class for database seeders.
 * 
 * @package Spatial\Database
 */
abstract class Seeder
{
    protected string $connection = 'default';
    protected mixed $output = null;

    /**
     * Run the seeder.
     *
     * @param EntityManagerInterface|null $em
     * @return void
     */
    abstract public function run(?EntityManagerInterface $em): void;

    /**
     * Set output handler.
     */
    public function setOutput($output): void
    {
        $this->output = $output;
    }

    /**
     * Output info message.
     */
    protected function info(string $message): void
    {
        if ($this->output && method_exists($this->output, 'output')) {
            $this->output->output("      {$message}");
        } else {
            echo "      {$message}\n";
        }
    }

    /**
     * Output success message.
     */
    protected function success(string $message): void
    {
        if ($this->output && method_exists($this->output, 'output')) {
            $this->output->output("      ✓ {$message}", 'success');
        } else {
            echo "      ✓ {$message}\n";
        }
    }

    /**
     * Output error message.
     */
    protected function error(string $message): void
    {
        if ($this->output && method_exists($this->output, 'output')) {
            $this->output->output("      ✗ {$message}", 'error');
        } else {
            echo "      ✗ {$message}\n";
        }
    }

    /**
     * Get the connection name.
     */
    public function getConnection(): string
    {
        return $this->connection;
    }
}
