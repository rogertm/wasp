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
		$targetDir		= '/classes/term-meta';
		$fileName		= "class-{$this->slugRoot}-term-meta-{$slug}.php";
		$filePath 		= $this->file($targetDir, $fileName, $output);

		if (false === $filePath)
			return Command::FAILURE;

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

		$instanceLine = sprintf(
		    "new %s\\Terms\\%s;\n",
		    $this->namespaceRoot,
		    $className
		);
		$label = 'Term Meta';

		$this->write( $filePath, $content, $label, $instanceLine, $output );

		return Command::SUCCESS;

	}
}
