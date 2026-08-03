<?php

declare(strict_types=1);

/**
 * ConfigurationLoader — APP_ENV → enableProdMode and %env()% resolution.
 *
 * Run: php test/configuration-loader-test.php
 */

require dirname(__DIR__) . '/src/core/ConfigurationLoader.php';

use Spatial\Core\ConfigurationLoader;

function assertTrue(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL  {$msg}\n");
        exit(1);
    }
    echo "ok    {$msg}\n";
}

echo "== resolveEnableProdMode ==\n";
putenv('APP_ENV=development');
assertTrue(ConfigurationLoader::resolveEnableProdMode(true) === false, 'development forces false');
putenv('APP_ENV=production');
assertTrue(ConfigurationLoader::resolveEnableProdMode(false) === true, 'production forces true');
putenv('APP_ENV=local');
assertTrue(ConfigurationLoader::resolveEnableProdMode(false) === true, 'local is prod mode');
putenv('APP_ENV=');
assertTrue(ConfigurationLoader::resolveEnableProdMode(true) === true, 'empty env uses yaml true');
assertTrue(ConfigurationLoader::resolveEnableProdMode(false) === false, 'empty env uses yaml false');

echo "\n== resolveEnv ==\n";
putenv('TEST_CFG_HOST=db.example');
$loader = new ConfigurationLoader();
$resolved = $loader->resolveEnv([
    'host' => '%env(TEST_CFG_HOST)%',
    'nested' => ['port' => '%env(TEST_CFG_MISSING)%'],
    'plain' => 'ok',
]);
assertTrue($resolved['host'] === 'db.example', 'env placeholder resolved');
assertTrue($resolved['nested']['port'] === '', 'missing env becomes empty string');
assertTrue($resolved['plain'] === 'ok', 'plain values unchanged');

echo "\nAll configuration loader checks passed.\n";
