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

		$name        = $input->getArgument('name');
		$object_type = $input->getArgument('object_type');
		$slug        = $this->slugify($name);
		$classSuffix = str_replace('-', '_', ucwords($slug, '-'));
		$className   = 'Taxonomy_' . $classSuffix;
		$fileName    = "class-{$this->slugRoot}-taxonomy-{$slug}.php";

		$targetDir = $this->baseDir . '/classes/taxonomy';
		if (!is_dir($targetDir)) {
			mkdir($targetDir, 0755, true);
		}
		$filePath = $targetDir . '/' . $fileName;

		// Check if file already exists to avoid overwriting
		if (file_exists($filePath)) {
			$output->writeln("<error>File already exists: $filePath</error>");
			return Command::FAILURE;
		}

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

		file_put_contents($filePath, $content);
		$output->writeln("Created Taxonomy class file: $filePath");

		$loaderFile = $this->baseDir . '/inc/classes.php';
		$instanceLine = sprintf(
		    "new %s\\Taxonomy\\%s;\n",
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
