<?php

declare(strict_types=1);

namespace WaspCli;

use ReflectionClass;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Finder\Finder;

final class Application extends BaseApplication
{
    public function __construct(string $cliDir)
    {
        parent::__construct('WASP CLI', '1.0.0');
        $this->registerCommands($cliDir . '/Command');
    }

    private function registerCommands(string $commandDir): void
    {
        $finder = (new Finder())
            ->files()
            ->in($commandDir)
            ->name('*Command.php')
            ->depth('== 0');

        foreach ($finder as $file) {
            $class = 'WaspCli\\Command\\' . $file->getBasename('.php');
            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Command::class)) {
                continue;
            }

            $command = $reflection->newInstance();
            if ($command instanceof Command) {
                $this->add($command);
            }
        }
    }
}
