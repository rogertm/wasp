<?php
namespace WaspCli\Command;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class ProjectRenameCommand extends Command
{
    protected static $defaultName = 'project:rename';

    protected function configure(): void
    {
        $this
            ->setDescription('Rename strings and files in this plugin using the existing configuration')
            ->addArgument(
                'project_name',
                InputArgument::REQUIRED,
                'New project name (e.g. "My Custom Plugin")'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'If specified, only shows which files would be changed without modifying anything.'
            )
            ->addOption(
                'backup',
                null,
                InputOption::VALUE_NONE,
                'Generate a backup in backup/{timestamp}/ before renaming.'
            )
            ->addOption(
                'config',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to JSON config file (default: cli/config.json)',
                'cli/config.json'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // SymfonyStyle instance for rich output
        $io = new SymfonyStyle($input, $output);
        $filesystem = new Filesystem();

        $baseDir    = realpath(__DIR__ . '/../../');
        if ($baseDir === false) {
            $io->error('Unable to resolve base directory.');
            return Command::FAILURE;
        }
        $configPath = $baseDir . '/' . ltrim($input->getOption('config'), '/');

        $io->title('🚀 Project Rename');

        // 1) Load existing configuration or use defaults
        $io->section('1) Loading configuration');
        if (file_exists($configPath)) {
            $raw = file_get_contents($configPath);
            $config = json_decode($raw, true);
            if (!is_array($config)) {
                $io->error("The JSON in $configPath is not valid.");
                return Command::FAILURE;
            }
            $oldNamespace  = $config['namespace']       ?? 'WASP';
            $searchSlug    = $config['slug']            ?? 'wasp';
            $oldPrefix     = $config['function_prefix'] ?? 'wasp_';
            $oldTextDomain = $config['text_domain']     ?? 'wasp';
            $io->text("Configuration loaded from: $configPath");
        } else {
            $io->warning("No configuration found at $configPath. Using default values.");
            $oldNamespace  = 'WASP';
            $searchSlug    = 'wasp';
            $oldPrefix     = 'wasp_';
            $oldTextDomain = 'wasp';
        }

        // 2) Compute new values based on the given project name
        $io->section('2) Calculating new values');
        $projectName   = $input->getArgument('project_name');
        $newNamespace  = $this->normalizeNamespace($projectName);
        try {
            $newSlug = $this->slugify($projectName);
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
        $newPrefix     = str_replace('-', '_', $newSlug) . '_';
        $newTextDomain = $newSlug;

        $io->text([
            "Old Namespace:      $oldNamespace",
            "New Namespace:      $newNamespace",
            "Old Slug:           $searchSlug",
            "New Slug:           $newSlug",
            "Old Prefix:         $oldPrefix",
            "New Prefix:         $newPrefix",
            "Old Text domain:    $oldTextDomain",
            "New Text domain:    $newTextDomain",
        ]);

        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $io->warning('⚡ DRY-RUN mode enabled: no files will be modified.');
        }

        // 3) Create backup if requested
        if ($input->getOption('backup')) {
            $io->section('3) Creating backup');
            $timestamp = date('Ymd_His');
            $backupDir = $baseDir . '/backup/' . $timestamp;

            // Define a Finder that excludes the backup/ folder itself
            $iterator = Finder::create()
                ->ignoreDotFiles(false)
                ->ignoreVCS(true)
                ->exclude('backup')
                ->in($baseDir);

            if ($dryRun) {
                $io->text("DRY-RUN ▶ Backup would be created at: $backupDir");
            } else {
                try {
                    // mirror(): if $backupDir doesn't exist, create it; then copy everything from $baseDir filtered by $iterator
                    $filesystem->mirror($baseDir, $backupDir, $iterator);
                    $io->success("🌟 Backup created at: $backupDir");
                } catch (IOExceptionInterface $e) {
                    $io->error("Error creating backup at $backupDir: " . $e->getMessage());
                    return Command::FAILURE;
                }
            }
        }

        // 4) Prepare search and replacement arrays
        $io->section('4) Processing files (php, js, css)');
        $replacementMap = [
            $oldNamespace . '\\' => $newNamespace . '\\',
            $oldNamespace => $projectName,
            $oldPrefix => $newPrefix,
            "'$oldTextDomain'" => "'$newTextDomain'",
            "\"$oldTextDomain\"" => "\"$newTextDomain\"",
            $searchSlug . '-' => $newSlug . '-',
            $searchSlug => $newSlug,
        ];
        $searches = [
            $oldNamespace . '\\',
            $oldNamespace,
            $oldPrefix,
            "'$oldTextDomain'",
            "\"$oldTextDomain\"",
            $searchSlug . '-',
            $searchSlug,
        ];
        $finder = new Finder();
        $finder
            ->files()
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->exclude(['vendor', 'node_modules', 'backup', 'cli', '.git'])
            ->in($baseDir)
            ->name('*.php')
            ->name('*.js')
            ->name('*.css');

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            $content  = file_get_contents($filePath);

            $needsChange = false;
            foreach ($searches as $s) {
                if (stripos($content, $s) !== false) {
                    $needsChange = true;
                    break;
                }
            }
            if (!$needsChange) {
                continue;
            }

            $newContent = strtr($content, $replacementMap);
            if ($newContent === $content) {
                continue;
            }

            if ($dryRun) {
                $io->text("DRY-RUN ▶ Modify content: $filePath");
            } else {
                try {
                    $written = file_put_contents($filePath, $newContent, LOCK_EX);
                    if ($written === false) {
                        throw new RuntimeException("Cannot write file: $filePath");
                    }
                    $io->text("✔ Processed: $filePath");
                } catch (\Throwable $e) {
                    $io->error("✖ Error writing $filePath: " . $e->getMessage());
                }
            }
        }

        // 5) Rename files in /classes
        $classesPath = $baseDir . '/classes';
        if (is_dir($classesPath)) {
            $io->section('5) Renaming files in /classes');
            $finderClasses = new Finder();
            $finderClasses
                ->files()
                ->ignoreDotFiles(true)
                ->ignoreVCS(true)
                ->exclude(['vendor', 'node_modules', '.git'])
                ->in($classesPath)
                ->name('*' . $searchSlug . '*');

            foreach ($finderClasses as $file) {
                $oldName = $file->getFilename();
                $oldFull = $file->getRealPath();
                $newName = $this->replaceSlugInFilename($oldName, $searchSlug, $newSlug);
                $newFull = $file->getPath() . DIRECTORY_SEPARATOR . $newName;

                if ($oldFull === $newFull) {
                    continue;
                }

                if ($dryRun) {
                    $io->text("DRY-RUN ▶ Rename: $oldFull → $newFull");
                } else {
                    try {
                        $filesystem->rename($oldFull, $newFull);
                        $io->text("✔ Renamed: $oldFull → $newFull");
                    } catch (IOExceptionInterface $e) {
                        $io->error("✖ Error renaming $oldFull: " . $e->getMessage());
                    }
                }
            }
        }

        // 6) Rename root files (*.php) containing old slug
        $io->section('6) Renaming root files');
        $finderRoot = new Finder();
        $finderRoot
            ->files()
            ->in($baseDir)
            ->depth('== 0')
            ->name('*.php');

        foreach ($finderRoot as $file) {
            $oldName = $file->getFilename();
            if (stripos($oldName, $searchSlug) === false) {
                continue;
            }
            $oldFull = $file->getRealPath();
            $newName = $this->replaceSlugInFilename($oldName, $searchSlug, $newSlug);
            $newFull = $baseDir . DIRECTORY_SEPARATOR . $newName;

            if ($oldFull === $newFull) {
                continue;
            }

            if ($dryRun) {
                $io->text("DRY-RUN ▶ Rename root: $oldFull → $newFull");
            } else {
                try {
                    $filesystem->rename($oldFull, $newFull);
                    $io->text("✔ Root renamed: $oldFull → $newFull");
                } catch (IOExceptionInterface $e) {
                    $io->error("✖ Error renaming root $oldFull: " . $e->getMessage());
                }
            }
        }

        // 7) Update config.json if changed
        $io->section('7) Updating config.json');
        $newConfig = [
            'namespace'       => $newNamespace,
            'slug'            => $newSlug,
            'function_prefix' => $newPrefix,
            'text_domain'     => $newTextDomain,
        ];
        $newConfigJson = json_encode($newConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

        if (!$dryRun) {
            $configChanged = true;
            if (file_exists($configPath)) {
                $oldConfigJson = file_get_contents($configPath);
                $configChanged = (trim($oldConfigJson) !== trim($newConfigJson));
            }

            if ($configChanged) {
                try {
                    $written = file_put_contents($configPath, $newConfigJson, LOCK_EX);
                    if ($written === false) {
                        throw new RuntimeException("Cannot write config file: $configPath");
                    }
                    $io->success("🥳 Config updated: $configPath");
                } catch (\Throwable $e) {
                    $io->error("✖ Error writing $configPath: " . $e->getMessage());
                }
            } else {
                $io->warning('config.json is already up to date. No changes made.');
            }
        }

        if ($dryRun) {
            $io->newLine();
            $io->success('🦄 DRY-RUN complete. No files were modified.');
            return Command::SUCCESS;
        }

        $io->newLine();
        $io->success('🎉 Renaming completed successfully.');
        return Command::SUCCESS;
    }

    /**
     * Transforms "My New Project" into "MyNewProject" (valid namespace).
     */
    private function normalizeNamespace(string $text): string
    {
        $trans = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        if ($trans === false) {
            $trans = $text;
        }
        $clean = preg_replace('/[^A-Za-z0-9 ]/', '', $trans);
        $parts = array_filter(explode(' ', $clean), fn($p) => $p !== '');
        $camel = '';
        foreach ($parts as $p) {
            $camel .= ucfirst(strtolower($p));
        }
        return $camel ?: 'WASP';
    }

    /**
     * Converts text into a slug: "My New Project" → "my-new-project"
     */
    private function slugify(string $text): string
    {
        $original = $text;
        $translit = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        if ($translit !== false) {
            $text = $translit;
        }
        $text = preg_replace('/[^\p{L}\p{Nd}]+/u', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        $slug = strtolower(trim($text, '-'));

        if ($slug === '') {
            throw new RuntimeException(sprintf('Cannot generate a valid slug from "%s".', $original));
        }

        return $slug;
    }

    /**
     * Replaces slug only on token boundaries to avoid duplicated cascades.
     * Example: "class-wasp-post-type.php" -> "class-wasp-child-post-type.php"
     */
    private function replaceSlugInFilename(string $name, string $oldSlug, string $newSlug): string
    {
        $pattern = '/(^|[-_.])' . preg_quote($oldSlug, '/') . '(?=$|[-_.])/i';
        $result = preg_replace_callback(
            $pattern,
            static function (array $matches) use ($newSlug): string {
                return $matches[1] . $newSlug;
            },
            $name
        );

        if ($result === null) {
            return $name;
        }

        return $result;
    }
}
