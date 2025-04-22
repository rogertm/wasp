<?php
namespace WaspCli\Command;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RenameProjectCommand extends Command
{
    protected static $defaultName = 'rename_project';

    protected function configure()
    {
        $this
            ->setDescription('Renames strings and files in WASP plugin')
            ->addArgument('project_name', InputArgument::REQUIRED, 'Project name (e.g., The ACME Project)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $projectName = $input->getArgument('project_name');
        $namespace = str_replace(' ', '', ucwords($projectName));
        $slug = $this->slugify($projectName);
        $functionPrefix = str_replace('-', '_', $slug) . '_';
        $textDomain = $slug;

        $output->writeln("Namespace: $namespace");
        $output->writeln("Function prefix: $functionPrefix");
        $output->writeln("Text domain: $textDomain");
        $output->writeln("Slug: $slug");

        $baseDir = realpath(__DIR__ . '/../../');
        $searchSlug = 'wasp';
        $replaceSlug = $slug;

        // 1) Replacements in files (excluding vendor)
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        $pattern = '/\.(php|js|css)$/i';
        foreach (new RegexIterator($iterator, $pattern, RegexIterator::MATCH) as $file) {
            $path = $file->getRealPath();
            if (strpos($path, $baseDir . '/vendor/') === 0) continue;
            $content = file_get_contents($path);
            $content = str_replace('WASP\\', $namespace . '\\', $content);
            $content = str_replace('wasp_', $functionPrefix, $content);
            $content = str_replace("'wasp'", "'" . $textDomain . "'", $content);
            $content = str_replace('wasp-', $slug . '-', $content);
            $content = str_replace('WASP', $projectName, $content);
            file_put_contents($path, $content);
            $output->writeln("Processed: $path");
        }

        // 2) Rename files in /classes recursively (excluding vendor)
        $classesDir = $baseDir . '/classes';
        if (is_dir($classesDir)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($classesDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $item) {
                $filePath = $item->getRealPath();
                if (strpos($filePath, $baseDir . '/vendor/') === 0) continue;
                if ($item->isFile() && stripos($item->getFilename(), $searchSlug) !== false) {
                    $newName = str_ireplace([
                        $searchSlug . '-',
                        $searchSlug
                    ], [
                        $replaceSlug . '-',
                        $replaceSlug
                    ], $item->getFilename());
                    $newPath = $item->getPath() . DIRECTORY_SEPARATOR . $newName;
                    rename($filePath, $newPath);
                    $output->writeln("Renamed: $filePath -> $newPath");
                }
            }
        }

        // 3) Rename root PHP files containing original slug
        foreach (glob($baseDir . '/*.php') as $file) {
            $filename = basename($file);
            if (stripos($filename, $searchSlug) !== false) {
                $newName = str_ireplace($searchSlug, $replaceSlug, $filename);
                $newPath = $baseDir . '/' . $newName;
                rename($file, $newPath);
                $output->writeln("Renamed root file: $file -> $newPath");
            }
        }

        $output->writeln('<info>Done!</info>');
        return Command::SUCCESS;
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('/[^\p{L}\p{Nd}]+/u', '-', $text);
        $text = trim($text, '-');
        return strtolower($text);
    }
}
