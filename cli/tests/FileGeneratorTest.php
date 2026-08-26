<?php

declare(strict_types=1);

namespace WaspCli\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use WaspCli\Generator\FileGenerator;
use WaspCli\Generator\LoaderRegistrar;

final class FileGeneratorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/wasp-cli-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);
        mkdir($this->tempDir . '/stubs', 0777, true);
        file_put_contents($this->tempDir . '/stubs/sample.stub', "Hello {{NAME}}\n");
    }

    protected function tearDown(): void
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
    }

    public function testDryRunDoesNotWrite(): void
    {
        $generator = new FileGenerator(new Filesystem(), $this->tempDir . '/stubs', true);
        $path = $generator->writeFromStub('sample', $this->tempDir . '/out', 'hello.php', [
            '{{NAME}}' => 'WASP',
        ]);

        $this->assertSame($this->tempDir . '/out/hello.php', $path);
        $this->assertFileDoesNotExist($path);
    }

    public function testWriteFromStubRendersPlaceholders(): void
    {
        $generator = new FileGenerator(new Filesystem(), $this->tempDir . '/stubs', false);
        $path = $generator->writeFromStub('sample', $this->tempDir . '/out', 'hello.php', [
            '{{NAME}}' => 'WASP',
        ]);

        $this->assertFileExists($path);
        $this->assertSame("Hello WASP\n", file_get_contents($path));
    }

    public function testWriteFromStubDoesNotOverwrite(): void
    {
        $targetDir = $this->tempDir . '/out';
        mkdir($targetDir);
        file_put_contents($targetDir . '/hello.php', 'existing');

        $generator = new FileGenerator(new Filesystem(), $this->tempDir . '/stubs', false);

        $this->expectException(RuntimeException::class);
        $generator->writeFromStub('sample', $targetDir, 'hello.php', ['{{NAME}}' => 'WASP']);
    }

    public function testLoaderRegistrarDryRunSkipsWrite(): void
    {
        $loader = $this->tempDir . '/classes.php';
        file_put_contents($loader, "<?php\n");

        $registrar = new LoaderRegistrar(true);
        $this->assertNull($registrar->append($loader, "new Foo\\Bar;\n"));
        $this->assertSame("<?php\n", file_get_contents($loader));
    }

    public function testLoaderRegistrarAppendsOnce(): void
    {
        $loader = $this->tempDir . '/classes.php';
        file_put_contents($loader, "<?php\n");

        $registrar = new LoaderRegistrar(false);
        $this->assertTrue($registrar->append($loader, "new Foo\\Bar;\n"));
        $this->assertFalse($registrar->append($loader, "new Foo\\Bar;\n"));
        $this->assertSame("<?php\nnew Foo\\Bar;\n", file_get_contents($loader));
    }
}
