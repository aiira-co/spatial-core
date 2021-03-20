<?php

declare(strict_types=1);

namespace Spatial\Core\Interfaces;

/**
 * Interfaces IApplicationBuilder
 * @package Spatial\Interfaces
 */
interface ApplicationBuilderInterface
{
    public function useSwooleHttp(): void;

    public function useDeveloperExceptionPage(): void;

    public function useHttpsRedirection(): void;

    public function useRouting(): void;

    public function useAuthorization(): void;

    public function useEndpoints(callable $endpoint): void;
}