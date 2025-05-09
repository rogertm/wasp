<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

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
}
