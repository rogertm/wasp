<?php

declare(strict_types=1);

namespace WaspCli\Tests;

use PHPUnit\Framework\TestCase;
use WaspCli\Config\ProjectConfig;

final class ProjectConfigTest extends TestCase
{
    public function testFromArrayAndJsonRoundTrip(): void
    {
        $config = ProjectConfig::fromArray([
            'namespace' => 'Demo',
            'slug' => 'demo-plugin',
            'function_prefix' => 'demo_plugin_',
            'text_domain' => 'demo-plugin',
        ]);

        $this->assertSame('Demo', $config->namespace);
        $this->assertSame('demo-plugin', $config->slug);

        $decoded = json_decode($config->toJson(), true);
        $this->assertIsArray($decoded);
        $this->assertSame($config->toArray(), $decoded);
    }

    public function testFromFileAllowMissingUsesDefaults(): void
    {
        $config = ProjectConfig::fromFile('/tmp/wasp-missing-config.json', true);
        $this->assertSame('WASP', $config->namespace);
        $this->assertSame('wasp', $config->slug);
    }
}
