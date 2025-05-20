<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateTermMetaCommand extends AbstractGeneratorCommand
{
	protected static $defaultName = 'create:term_meta';

	protected function configure(): void
	{
		$this
			->setDescription('Creates a new Term Meta class file using project config')
			->addArgument('name', InputArgument::REQUIRED, 'Term Meta name (e.g., My custom fields)')
			->addArgument('taxonomy', InputArgument::REQUIRED, 'The taxonomy slug. (e.g., wasp-genre)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->initialize($input, $output);

		$name			= $input->getArgument('name');
		$taxonomy		= $input->getArgument('taxonomy');
		$slug			= $this->slugify($name);
		$classSuffix	= str_replace('-', '_', ucwords($slug, '-'));
		$className		= 'Term_Meta_' . $classSuffix;
		$fileName		= "class-{$this->slugRoot}-term-meta-{$slug}.php";

		$targetDir = $this->baseDir . '/classes/term-meta';
		if (!is_dir($targetDir)) {
			mkdir($targetDir, 0755, true);
		}
		$filePath = $targetDir . '/' . $fileName;

		// Check if file already exists to avoid overwriting
		if (file_exists($filePath)) {
			$output->writeln("<error>File already exists: $filePath</error>");
			return Command::FAILURE;
		}

		$namespaceDecl	= $this->namespaceRoot . '\\Terms';
		$useDecl		= $this->namespaceRoot . '\\Terms\\Term_Meta';
		$filter			= str_replace('-', '_', $slug);
		$content		= <<<PHP
<?php
namespace {$namespaceDecl};

use {$useDecl};

class {$className} extends Term_Meta
{
	public function __construct()
	{
		parent::__construct();
		\$this->taxonomy	= '{$taxonomy}';
	}

	function fields()
	{
		\$fields = array(
			// Your fields goes here...
		);

		return apply_filters( '{$this->slugRoot}_{$filter}_term_meta_fields', \$fields );
	}
}

PHP;

		file_put_contents($filePath, $content);
		$output->writeln("Created Term Meta class file: $filePath");

		$loaderFile = $this->baseDir . '/inc/classes.php';
		$instanceLine = sprintf(
		    "new %s\\Terms\\%s;\n",
		    $this->namespaceRoot,
		    $className
		);

		if (file_exists($loaderFile) && is_writable($loaderFile)) {
		    file_put_contents($loaderFile, $instanceLine, FILE_APPEND);
		    $output->writeln("Appended instance to: $loaderFile");
		} else {
		    $output->writeln("<comment>Warning: Could not write to $loaderFile</comment>");
		}

		$output->writeln('<info>Done!</info>');
		return Command::SUCCESS;

	}
}
