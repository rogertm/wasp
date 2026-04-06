<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class ProjectNewCommand extends AbstractGeneratorCommand
{
    protected static $defaultName = 'project:new';

    protected function configure(): void
    {
        $this
            ->setDescription('Creates a new "child" plugin that inherits from WASP')
            ->addArgument('name', InputArgument::REQUIRED, 'Name of the new plugin (e.g.: "WASP Child")')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'If specified, only simulates creation without writing files.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // SymfonyStyle for logging
        $this->io->title('🔌 Creating new “child” plugin');

        $dryRun = (bool) $input->getOption('dry-run');
        if ($dryRun) {
            $this->io->warning('⚡ DRY-RUN mode enabled: no files or folders will be created.');
        }

        // Initialize inherited properties
        parent::initialize($input, $output);

        // 1) Plugin name, slug and directory
        $name      = $input->getArgument('name');
        try {
            $slug = $this->slugify($name);
        } catch (\Throwable $e) {
            $this->io->error($e->getMessage());
            return Command::FAILURE;
        }
        $pluginDir = $this->baseDir . '/../' . $slug; // plugins/{slug}

        $this->io->section('1) Preparing basic data');
        $this->io->text([
            "Plugin name:        $name",
            "Slug (folder name): $slug",
            "Destination folder: $pluginDir",
        ]);

        // 2) Check that it doesn't already exist
        if (is_dir($pluginDir)) {
            $this->io->error("💣 The plugin directory already exists: $pluginDir");
            return Command::FAILURE;
        }

        // 3) Create main folder (or simulate)
        if (! $dryRun) {
            try {
                $this->filesystem->mkdir($pluginDir, 0755);
                $this->io->text("✔ Folder created: $pluginDir");
            } catch (IOExceptionInterface $e) {
                $this->io->error("Error creating folder $pluginDir: " . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $this->io->text("DRY-RUN ▶ mkdir $pluginDir");
        }

        // 4) Define “classic” subfolders of a WASP-child plugin:
        //     We use the same “classes” structure from WASP,
        //     plus an /inc folder, and generate the corresponding stubs.
        $this->io->section('2) Creating folder and file structure');

        // 2.1) Recreate the “classes” structure from the base WASP plugin
        $waspPluginDir = $this->baseDir . '/../' . $this->slugRoot;
        if (! is_dir($waspPluginDir)) {
            $this->io->error("WASP plugin not found at: $waspPluginDir");
            return Command::FAILURE;
        }

        // 2.1.a) Find all subfolders inside WASP’s classes/ and replicate them
        $finder = new Finder();
        $finder
            ->directories()
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->in($waspPluginDir . '/classes')
            ->exclude(['vendor', 'node_modules', '.git', 'helpers', 'interfaces']);

        foreach ($finder as $dir) {
            $relativePath = $dir->getRelativePathname();
            $newDir       = $pluginDir . '/classes/' . $relativePath;

            if (! $dryRun) {
                try {
                    $this->filesystem->mkdir($newDir, 0755);
                    $this->io->text("✔ Folder: $newDir");
                } catch (IOExceptionInterface $e) {
                    $this->io->error("Error creating $newDir: " . $e->getMessage());
                    return Command::FAILURE;
                }
            } else {
                $this->io->text("DRY-RUN ▶ mkdir $newDir");
            }

            // 2.1.b) In each “classes/...” folder, create an index.php via stub
            $destinationFile = $newDir . '/index.php';
            $replacements = [
                '{{SLUG}}' => $slug,
            ];

            if (! $dryRun) {
                try {
                    $fullPath = $this->createFileFromStub(
                        'index',
                        $newDir,
                        'index.php',
                        $replacements
                    );
                    $this->io->text("✔ Stub created: $fullPath");
                } catch (\Throwable $e) {
                    // Error is already printed inside createFileFromStub
                    return Command::FAILURE;
                }
            } else {
                $this->io->text("DRY-RUN ▶ createFileFromStub(index.php → $destinationFile)");
            }
        }

        // 2.2) Create /inc folder and files index.php + classes.php
        $incDir = $pluginDir . '/inc';
        if (! $dryRun) {
            try {
                $this->filesystem->mkdir($incDir, 0755);
                $this->io->text("✔ Folder: $incDir");
            } catch (IOExceptionInterface $e) {
                $this->io->error("Error creating $incDir: " . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $this->io->text("DRY-RUN ▶ mkdir $incDir");
        }

        // inc/index.php
        $replacementsIncIndex = [
            '{{SLUG}}' => $slug,
        ];
        $destIncIndex = $incDir . '/index.php';
        if (! $dryRun) {
            try {
                $fullPath = $this->createFileFromStub(
                    'inc.index',
                    $incDir,
                    'index.php',
                    $replacementsIncIndex
                );
                $this->io->text("✔ Stub created: $fullPath");
            } catch (\Throwable $e) {
            	$this->io->error("Exception when invoking createFileFromStub('index'): " . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $this->io->text("DRY-RUN ▶ createFileFromStub(index.php → $destIncIndex)");
        }

        // inc/classes.php
        $replacementsClassesPhp = [
            '{{SLUG}}'       => $slug,
            '{{SLUG_PARENT}}' => $this->slugRoot,
        ];
        $destIncClasses = $incDir . '/classes.php';
        if (! $dryRun) {
            try {
                $fullPath = $this->createFileFromStub(
                    'classes',
                    $incDir,
                    'classes.php',
                    $replacementsClassesPhp
                );
                $this->io->text("✔ Stub created: $fullPath");
            } catch (\Throwable $e) {
            	$this->io->error("Exception when invoking createFileFromStub('classes'): " . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $this->io->text("DRY-RUN ▶ createFileFromStub(classes.php → $destIncClasses)");
        }

        // 2.3) Create autoloader.php at the root of the plugin
        $replacementsAutoloader = [
            '{{SLUG}}'       => $slug,
            '{{SLUG_PARENT}}' => $this->slugRoot,
        ];
        $destAutoloader = $pluginDir . '/autoloader.php';
        if (! $dryRun) {
            try {
                $fullPath = $this->createFileFromStub(
                    'autoloader',
                    $pluginDir,
                    'autoloader.php',
                    $replacementsAutoloader
                );                $this->io->text("✔ Stub created: $fullPath");
            } catch (\Throwable $e) {
            	$this->io->error("Exception when invoking createFileFromStub('autoloader'): " . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $this->io->text("DRY-RUN ▶ createFileFromStub(autoloader → $destAutoloader)");
        }

        // 2.4) Create main {slug}.php file with plugin header
        $replacementsPluginMain = [
            '{{PLUGIN_NAME}}' => $name,
            '{{SLUG}}'        => $slug,
            '{{SLUG_PARENT}}'  => $this->slugRoot,
            '{{TEXT_DOMAIN}}' => $slug,
            '{{AUTHOR}}'      => 'RogerTM',
            '{{AUTHOR_URI}}'  => 'https://rogertm.com',
            '{{PLUGIN_URI}}'  => 'https://github.com/rogertm/wasp',
            '{{VERSION}}'     => '1.0.0',
        ];
        $destPluginFile = $pluginDir . '/' . $slug . '.php';
        if (! $dryRun) {
            try {
                $fullPath = $this->createFileFromStub(
                    'plugin',
                    $pluginDir,
                    $slug . '.php',
                    $replacementsPluginMain
                );
                $this->io->text("✔ Stub created: $fullPath");
            } catch (\Throwable $e) {
            	$this->io->error("Exception when invoking createFileFromStub('plugin'): " . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $this->io->text("DRY-RUN ▶ createFileFromStub(plugin → $destPluginFile)");
        }

        // 3) Finish
        $this->io->newLine();
        if ($dryRun) {
            $this->io->success('🦄 DRY-RUN completed. No files were created.');
        } else {
            $this->io->success("🎉 Plugin “{$name}” successfully generated at: $pluginDir");
        }

        return Command::SUCCESS;
    }
}
