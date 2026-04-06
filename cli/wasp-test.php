#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * WASP CLI smoke test runner.
 *
 * Usage:
 *   php cli/wasp-test.php
 *   php cli/wasp-test.php --verbose
 *   php cli/wasp-test.php --stop-on-failure
 */

$options = getopt('', ['verbose', 'stop-on-failure']);
$verbose = isset($options['verbose']);
$stopOnFailure = isset($options['stop-on-failure']);

$phpBinary = PHP_BINARY;
$cliPath = __DIR__ . '/wasp';
$projectRoot = realpath(__DIR__ . '/..');

if (!is_file($cliPath)) {
    fwrite(STDERR, "Error: CLI script not found at {$cliPath}\n");
    exit(1);
}

if ($projectRoot === false) {
    fwrite(STDERR, "Error: Unable to resolve project root.\n");
    exit(1);
}

$token = date('YmdHis') . '-' . substr(md5((string) microtime(true)), 0, 6);
$uniqueName = 'QA ' . $token;
$objectType = 'wasp-book-' . str_replace('-', '', $token);
$taxonomy = 'wasp-genre-' . str_replace('-', '', $token);

$tests = [
    [
        'name' => 'list',
        'args' => ['list'],
        'contains' => ['project:rename', 'create:post_type'],
    ],
    [
        'name' => 'project:rename',
        'args' => ['project:rename', 'WASP ' . $uniqueName, '--dry-run', '--backup'],
        'contains' => ['DRY-RUN'],
    ],
    [
        'name' => 'project:new',
        'args' => ['project:new', 'Plugin ' . $uniqueName, '--dry-run'],
        'contains' => ['DRY-RUN'],
    ],
    [
        'name' => 'create:post_type',
        'args' => ['create:post_type', 'Book ' . $uniqueName, '--dry-run'],
        'contains' => ['DRY-RUN'],
    ],
    [
        'name' => 'create:taxonomy',
        'args' => ['create:taxonomy', 'Genre ' . $uniqueName, $objectType, '--dry-run'],
        'contains' => ['DRY-RUN'],
    ],
    [
        'name' => 'create:meta_box',
        'args' => ['create:meta_box', 'Meta ' . $uniqueName, $objectType, '--dry-run'],
        'contains' => ['DRY-RUN'],
    ],
    [
        'name' => 'create:term_meta',
        'args' => ['create:term_meta', 'Terms ' . $uniqueName, $taxonomy, '--dry-run'],
        'contains' => ['DRY-RUN'],
    ],
    [
        'name' => 'create:admin_page',
        'args' => ['create:admin_page', 'Admin ' . $uniqueName, '--dry-run'],
        'contains' => ['DRY-RUN'],
    ],
    [
        'name' => 'create:admin_subpage',
        'args' => ['create:admin_subpage', 'Sub ' . $uniqueName, 'wasp-dashboard-setting', '--dry-run'],
        'contains' => ['DRY-RUN'],
    ],
    [
        'name' => 'create:setting_fields',
        'args' => ['create:setting_fields', 'Settings ' . $uniqueName, 'wasp-dashboard-setting', '--subpage', '--dry-run'],
        'contains' => ['DRY-RUN'],
    ],
    [
        'name' => 'create:user_meta',
        'args' => ['create:user_meta', 'User ' . $uniqueName, '--dry-run'],
        'contains' => ['DRY-RUN'],
    ],
    [
        'name' => 'create:shortcode',
        'args' => ['create:shortcode', 'Shortcode ' . $uniqueName, '--dry-run'],
        'contains' => ['DRY-RUN'],
    ],
    [
        'name' => 'create:custom_columns',
        'args' => ['create:custom_columns', 'Columns ' . $uniqueName, '--dry-run'],
        'contains' => ['Dry-run'],
    ],
];

$total = count($tests);
$passed = 0;
$failed = 0;
$failedTests = [];

echo "Running {$total} WASP CLI command tests\n";
echo "CLI: {$cliPath}\n";
echo "PHP: {$phpBinary}\n\n";

foreach ($tests as $index => $test) {
    $result = runCliCommand($phpBinary, $cliPath, $test['args'], $projectRoot);
    $missing = [];

    foreach ($test['contains'] as $expectedFragment) {
        if (strpos($result['output'], $expectedFragment) === false) {
            $missing[] = $expectedFragment;
        }
    }

    $ok = ($result['exit_code'] === 0) && empty($missing);
    $label = sprintf('[%02d/%02d] %s', $index + 1, $total, $test['name']);

    if ($ok) {
        $passed++;
        echo "{$label}: PASS\n";
        if ($verbose) {
            echo "  CMD: {$result['command']}\n";
            echo indent(trim($result['output'])) . "\n";
        }
        continue;
    }

    $failed++;
    $failedTests[] = $test['name'];
    echo "{$label}: FAIL\n";
    echo "  CMD: {$result['command']}\n";
    echo "  Exit code: {$result['exit_code']}\n";

    if (!empty($missing)) {
        echo "  Missing output fragments: " . implode(', ', $missing) . "\n";
    }

    echo indent(trim($result['output'])) . "\n";

    if ($stopOnFailure) {
        break;
    }
}

echo "\nSummary: {$passed} passed, {$failed} failed, {$total} total\n";

if ($failed > 0) {
    echo "Failed tests: " . implode(', ', $failedTests) . "\n";
    exit(1);
}

exit(0);

/**
 * @param string[] $args
 * @return array{command:string,output:string,exit_code:int}
 */
function runCliCommand(string $phpBinary, string $cliPath, array $args, string $cwd): array
{
    $parts = array_merge([$phpBinary, $cliPath], $args);
    $escaped = [];

    foreach ($parts as $part) {
        $escaped[] = escapeshellarg($part);
    }

    $command = implode(' ', $escaped);
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes, $cwd);
    if (!is_resource($process)) {
        return [
            'command' => $command,
            'output' => 'Unable to start process.',
            'exit_code' => 1,
        ];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    $combinedOutput = (string) $stdout . (string) $stderr;

    return [
        'command' => $command,
        'output' => $combinedOutput,
        'exit_code' => (int) $exitCode,
    ];
}

function indent(string $text): string
{
    if ($text === '') {
        return '  (no output)';
    }

    $lines = explode("\n", $text);
    $indented = [];

    foreach ($lines as $line) {
        $indented[] = '  ' . $line;
    }

    return implode("\n", $indented);
}
