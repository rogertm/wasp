<?php

declare(strict_types=1);

namespace WaspCli\Tests;

use PHPUnit\Framework\TestCase;
use WaspCli\Naming\PhpNamer;

final class PhpNamerTest extends TestCase
{
    private PhpNamer $namer;

    protected function setUp(): void
    {
        $this->namer = new PhpNamer();
    }

    public function testSlugify(): void
    {
        $this->assertSame('photo-gallery', $this->namer->slugify('Photo Gallery'));
    }

    public function testClassSuffixFromSlug(): void
    {
        $this->assertSame('Photo_Gallery', $this->namer->classSuffixFromSlug('photo-gallery'));
    }

    public function testNamespaceFromSlug(): void
    {
        $this->assertSame('WaspChild', $this->namer->namespaceFromSlug('wasp-child'));
    }

    public function testNamespaceFromProjectName(): void
    {
        $this->assertSame('MyCustomPlugin', $this->namer->namespaceFromProjectName('My Custom Plugin'));
    }

    public function testFunctionPrefixFromSlug(): void
    {
        $this->assertSame('my_plugin_', $this->namer->functionPrefixFromSlug('my-plugin'));
    }

    public function testReplaceSlugInFilename(): void
    {
        $this->assertSame(
            'class-wasp-child-post-type.php',
            $this->namer->replaceSlugInFilename('class-wasp-post-type.php', 'wasp', 'wasp-child')
        );
    }
}
