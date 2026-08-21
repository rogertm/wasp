#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * WASP CLI test runner (PHPUnit + CommandTester).
 *
 * Usage:
 *   php cli/wasp-test.php
 *   php cli/wasp-test.php --filter=CreatePostType
 */

$phpunit = dirname(__DIR__) . '/vendor/bin/phpunit';
if (! is_file($phpunit)) {
    fwrite(STDERR, "Error: PHPUnit not found. Run `composer install` in the plugin root.\n");
    exit(1);
}

$phpunitArgs = array_slice($argv, 1);
$command = array_merge([PHP_BINARY, $phpunit], $phpunitArgs);
$escaped = array_map('escapeshellarg', $command);

passthru(implode(' ', $escaped), $exitCode);
exit((int) $exitCode);
