<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractGeneratorCommand extends Command
{
	/** @var string */
	protected $baseDir;

	/** @var string */
	protected $namespaceRoot;

	/** @var string */
	protected $slugRoot;

	/** @var string */
	protected $textDomain;

	/**
	 * Overrides Symfony Command initialize to set up common config
	 *
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 */
	protected function initialize(InputInterface $input, OutputInterface $output): void
	{
		// Plugin root folder
		$this->baseDir = realpath(__DIR__ . '/../../');

		// Config vars
		$configPath = $this->baseDir . '/cli/config.json';
		if (!file_exists($configPath)) {
			throw new RuntimeException("Config not found. Run rename_project first.");
		}
		$config = json_decode(file_get_contents($configPath), true);
		$this->namespaceRoot = $config['namespace'];
		$this->slugRoot      = $config['slug'];
		$this->textDomain    = $config['text_domain'];
	}

	/**
	 * Convert "Foo Bar" in "foo-bar"
	 */
	protected function slugify(string $text): string
	{
		$text = preg_replace('/[^\p{L}\p{Nd}]+/u', '-', $text);
		return strtolower(trim($text, '-'));
	}

	protected function file( $targetDir, $fileName, $output )
	{
		if (!is_dir($targetDir)) {
			mkdir($targetDir, 0755, true);
		}
		$filePath = $targetDir . '/' . $fileName;

		// Check if file already exists to avoid overwriting
		if (file_exists($filePath)) {
			$output->writeln("<error>File already exists: $filePath</error>");
			return false;
		} else {
			return $filePath;
		}
	}

	protected function write( $filePath, $content, $label, $instanceLine, $output, $project )
	{
		file_put_contents($filePath, $content);
		$output->writeln("Created $label class file: $filePath");

		$loaderFile = ($project)
						? '../'. $project .'/inc/classes.php'
						: 'inc/classes.php';

		if (file_exists($loaderFile) && is_writable($loaderFile)) {
			file_put_contents($loaderFile, $instanceLine, FILE_APPEND);
			$output->writeln("Appended instance to: $loaderFile");
		} else {
			$output->writeln("<comment>Warning: Could not write to $loaderFile</comment>");
		}

		$output->writeln('<info>Done!</info>');
	}
}
