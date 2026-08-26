<?php

declare(strict_types=1);

namespace WaspCli\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandTester;
use WaspCli\Application;

final class CliCommandsTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function createCommandProvider(): array
    {
        return [
            'post_type' => ['create:post_type', ['name' => 'Book QA']],
            'taxonomy' => ['create:taxonomy', ['name' => 'Genre QA', 'object_type' => 'wasp-book']],
            'meta_box' => ['create:meta_box', ['name' => 'Meta QA', 'screen' => 'wasp-book']],
            'term_meta' => ['create:term_meta', ['name' => 'Terms QA', 'taxonomy' => 'wasp-genre']],
            'admin_page' => ['create:admin_page', ['name' => 'Admin QA']],
            'admin_subpage' => ['create:admin_subpage', ['name' => 'Sub QA', 'parent_slug' => 'wasp-dashboard-setting']],
            'setting_fields' => ['create:setting_fields', ['section' => 'Settings QA', 'page_slug' => 'wasp-dashboard-setting', '--subpage' => true]],
            'user_meta' => ['create:user_meta', ['name' => 'User QA']],
            'shortcode' => ['create:shortcode', ['name' => 'Shortcode QA']],
            'custom_columns' => ['create:custom_columns', ['name' => 'Columns QA']],
        ];
    }

    public function testDiscoversExpectedCommands(): void
    {
        $application = $this->application();
        $names = array_keys($application->all());

        foreach ([
            'project:rename',
            'project:new',
            'create:post_type',
            'create:taxonomy',
            'create:meta_box',
            'create:term_meta',
            'create:admin_page',
            'create:admin_subpage',
            'create:setting_fields',
            'create:user_meta',
            'create:shortcode',
            'create:custom_columns',
        ] as $name) {
            $this->assertContains($name, $names);
        }
    }

    public function testListCommandOutput(): void
    {
        $application = $this->application();
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $status = $tester->run(['command' => 'list'], ['decorated' => false]);

        $this->assertSame(Command::SUCCESS, $status);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('project:rename', $display);
        $this->assertStringContainsString('create:post_type', $display);
    }

    /**
     * @dataProvider createCommandProvider
     * @param array<string, mixed> $input
     */
    public function testCreateCommandsDryRun(string $commandName, array $input): void
    {
        $this->assertDryRun($commandName, $input);
    }

    public function testProjectCommandsDryRun(): void
    {
        $this->assertDryRun('project:rename', [
            'project_name' => 'WASP QA Rename',
            '--backup' => true,
        ]);
        $this->assertDryRun('project:new', [
            'name' => 'Plugin QA',
        ]);
    }

    public function testCreatePostTypeUsesProjectOptionNotPositionalArgument(): void
    {
        $command = $this->application()->find('create:post_type');
        $definition = $command->getDefinition();

        $this->assertFalse($definition->hasArgument('project'));
        $this->assertTrue($definition->hasOption('project'));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function assertDryRun(string $commandName, array $input): void
    {
        $command = $this->application()->find($commandName);
        $tester = new CommandTester($command);
        $status = $tester->execute(
            array_merge($input, ['--dry-run' => true]),
            ['decorated' => false]
        );

        $this->assertSame(Command::SUCCESS, $status, $tester->getDisplay());
        $this->assertStringContainsString('DRY-RUN', $tester->getDisplay());
    }

    private function application(): Application
    {
        return new Application(dirname(__DIR__));
    }
}
