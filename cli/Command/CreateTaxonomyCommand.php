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
			->addArgument('object_type', InputArgument::REQUIRED, 'Object type with which the taxonomy should be associated. (e.g., wasp-book)')
			->addArgument('project', InputArgument::OPTIONAL, 'Project slug where this CPT should be created (e.g., wasp-child). If omitted, uses WASP.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->initialize($input, $output);

		$name			= $input->getArgument('name');
		$object_type	= $input->getArgument('object_type');
		$project 		= $input->getArgument('project');
		if (isset($project) && !is_dir('../'. $project)){
			$output->writeln("<error>Project not found: $project</error>");
			return Command::FAILURE;
		}

		$this->baseDir	= ($project) ? $this->baseDir .'/../'. $project : $this->baseDir;
		$slug			= $this->slugify($name);
		$classSuffix	= str_replace('-', '_', ucwords($slug, '-'));
		$className		= 'Taxonomy_' . $classSuffix;
		$targetDir		= ($project) ? '../'.$project.'/classes/taxonomy' : 'classes/taxonomy';
		$projectSlug	= $project ?: $this->slugRoot;
		$fileName		= "class-{$projectSlug}-taxonomy-{$slug}.php";
		$filePath 		= $this->file($targetDir, $fileName, $output);

		if (false === $filePath)
			return Command::FAILURE;

		$textDomain		= ($project) ? $project : $this->textDomain;
		$nsParts		= ($project) ? array_map('ucfirst', explode('-', $project)) : null;
		$nsDeclPrefix	= ($nsParts) ? implode('', $nsParts) : $this->namespaceRoot;

		$namespaceDecl = $nsDeclPrefix . '\\Taxonomy';
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
		\$this->taxonomy = '{$projectSlug}-{$slug}';

		// Object type
		\$this->object_type = '{$object_type}';

		// Taxonomy labels
		\$this->labels = array(
			'name'		=> _x( '{$name}', 'Taxonomy general name', '{$textDomain}' )
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
		    $nsDeclPrefix,
		    $className
		);
		$label = 'Taxonomy';

		$this->write( $filePath, $content, $label, $instanceLine, $output, $project );

		return Command::SUCCESS;
	}
}
