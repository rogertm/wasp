<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateTaxonomyCommand extends AbstractGeneratorCommand
{
	protected static $defaultName = 'create:taxonomy';

	protected function configure(): void
	{
		$this
			->setDescription('Creates a new Taxonomy class file using project config')
			->addArgument('name', InputArgument::REQUIRED, 'Taxonomy name (e.g., Genre)')
			->addArgument('object_type', InputArgument::REQUIRED, 'Object type with which the taxonomy should be associated. (e.g., wasp-book)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->initialize($input, $output);

		$name			= $input->getArgument('name');
		$object_type	= $input->getArgument('object_type');
		$slug			= $this->slugify($name);
		$classSuffix	= str_replace('-', '_', ucwords($slug, '-'));
		$className		= 'Taxonomy_' . $classSuffix;
		$targetDir		= '/classes/taxonomy';
		$fileName		= "class-{$this->slugRoot}-taxonomy-{$slug}.php";
		$filePath 		= $this->file($targetDir, $fileName, $output);

		if (false === $filePath)
			return Command::FAILURE;

		$namespaceDecl = $this->namespaceRoot . '\\Taxonomy';
		$useDecl       = $this->namespaceRoot . '\\Taxonomy\\Taxonomy';
		$content       = <<<PHP
<?php
namespace {$namespaceDecl};

use {$useDecl};

class {$className} extends Taxonomy
{
	public function __construct()
	{
		parent::__construct();

		// Taxonomy slug
		\$this->taxonomy = '{$this->slugRoot}-{$slug}';

		// Object type
		\$this->object_type = '{$object_type}';

		// Taxonomy labels
		\$this->labels = array(
			'name'		=> _x( '{$name}', 'Taxonomy general name', '{$this->textDomain}' )
		);

		// Taxonomy arguments
		\$this->args = array(
			'public'	=> true
		);
	}
}

PHP;

		$instanceLine = sprintf(
		    "new %s\\Taxonomy\\%s;\n",
		    $this->namespaceRoot,
		    $className
		);

		$this->write( $filePath, $content, $instanceLine, $output );

		return Command::SUCCESS;
	}
}
