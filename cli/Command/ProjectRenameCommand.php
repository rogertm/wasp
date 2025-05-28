<?php
namespace WaspCli\Command;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProjectRenameCommand extends Command
{
    protected static $defaultName = 'project:rename';

    protected function configure(): void
    {
        $this
            ->setDescription('Renames strings and files in this plugin using existing config')
            ->addArgument('project_name', InputArgument::REQUIRED, 'New project name (e.g., My Custom Plugin)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Base directory and static config file
        $baseDir    = realpath(__DIR__ . '/../../');
        $configPath = $baseDir . '/cli/config.json';

        // Load existing config or defaults
        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);
            $oldNamespace  = $config['namespace'];
            $searchSlug    = $config['slug'];
            $oldPrefix     = $config['function_prefix'];
            $oldTextDomain = $config['text_domain'];
        } else {
            $oldNamespace  = 'WASP';
            $searchSlug    = 'wasp';
            $oldPrefix     = 'wasp_';
            $oldTextDomain = 'wasp';
            $output->writeln('<comment>No existing config found. Using defaults.</comment>');
        }

        // New project settings
        $projectName   = $input->getArgument('project_name');
        $newNamespace  = str_replace(' ', '', ucwords($projectName));
        $newSlug       = $this->slugify($projectName);
        $newPrefix     = str_replace('-', '_', $newSlug) . '_';
        $newTextDomain = $newSlug;

        // Report changes
        $output->writeln("Old Namespace: $oldNamespace");
        $output->writeln("New Namespace: $newNamespace");
        $output->writeln("Old Slug: $searchSlug");
        $output->writeln("New Slug: $newSlug");
        $output->writeln("Old Prefix: $oldPrefix");
        $output->writeln("New Prefix: $newPrefix");
        $output->writeln("Old Text domain: $oldTextDomain");
        $output->writeln("New Text domain: $newTextDomain");

        // 1) Replace in files (php, js, css), excluding vendor
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        $pattern = '/\.(php|js|css)$/i';
        foreach (new RegexIterator($iterator, $pattern, RegexIterator::MATCH) as $file) {
            $path = $file->getRealPath();
            if (strpos($path, $baseDir . '/vendor/') === 0) {
                continue;
            }
            $content = file_get_contents($path);
            $content = str_replace($oldNamespace . '\\', $newNamespace . '\\', $content);
            $content = str_replace($oldPrefix, $newPrefix, $content);
            $content = str_replace("'$oldTextDomain'", "'$newTextDomain'", $content);
            $content = str_replace($searchSlug . '-', $newSlug . '-', $content);
            $content = str_replace($oldNamespace, $projectName, $content);
            file_put_contents($path, $content);
            $output->writeln("Processed: $path");
        }

        // 2) Rename files in classes (excluding vendor)
        $classesDir = $baseDir . '/classes';
        if (is_dir($classesDir)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($classesDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $item) {
                $filePath = $item->getRealPath();
                if (strpos($filePath, $baseDir . '/vendor/') === 0) {
                    continue;
                }
                if ($item->isFile() && stripos($item->getFilename(), $searchSlug) !== false) {
                    $newName = str_ireplace([
                        $searchSlug . '-',
                        $searchSlug
                    ], [
                        $newSlug . '-',
                        $newSlug
                    ], $item->getFilename());
                    $newPath = $item->getPath() . DIRECTORY_SEPARATOR . $newName;
                    rename($filePath, $newPath);
                    $output->writeln("Renamed: $filePath -> $newPath");
                }
            }
        }

        // 3) Rename root PHP files with slug
        foreach (glob($baseDir . '/*.php') as $file) {
            $filename = basename($file);
            if (stripos($filename, $searchSlug) !== false) {
                $newName = str_ireplace($searchSlug, $newSlug, $filename);
                $newPath = $baseDir . '/' . $newName;
                rename($file, $newPath);
                $output->writeln("Renamed root file: $file -> $newPath");
            }
        }

        // 4) Save updated config back to static file
        $newConfig = [
            'namespace'       => $newNamespace,
            'slug'            => $newSlug,
            'function_prefix' => $newPrefix,
            'text_domain'     => $newTextDomain,
        ];
        file_put_contents($configPath, json_encode($newConfig, JSON_PRETTY_PRINT));
        $output->writeln("Config updated: $configPath");

        $output->writeln('<info>Rename complete.</info>');
        return Command::SUCCESS;
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('/[^\p{L}\p{Nd}]+/u', '-', $text);
        return strtolower(trim($text, '-'));
    }
}
