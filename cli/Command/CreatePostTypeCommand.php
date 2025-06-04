<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreatePostTypeCommand extends AbstractGeneratorCommand
{
	protected static $defaultName = 'create:post_type';

	protected function configure(): void
	{
		$this
			->setDescription('Creates a new Custom Post Type class file using project config')
			->addArgument('name', InputArgument::REQUIRED, 'Post Type name (e.g., Book)')
			->addArgument('project', InputArgument::OPTIONAL, 'Project slug where this CPT should be created (e.g., wasp-child). If omitted, uses WASP.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->initialize($input, $output);

		$name			= $input->getArgument('name');
		$project 		= $input->getArgument('project');
		if (isset($project) && !is_dir('../'. $project)){
			$output->writeln("<error>Project not found: $project</error>");
			return Command::FAILURE;
		}

		$this->baseDir	= ($project) ? $this->baseDir .'/../'. $project : $this->baseDir;
		$slug			= $this->slugify($name);
		$classSuffix	= str_replace('-', '_', ucwords($slug, '-'));
		$className		= 'Post_Type_' . $classSuffix;
		$targetDir		= ($project) ? '../'.$project.'/classes/post-type' : 'classes/post-type';
		$projectSlug	= $project ?: $this->slugRoot;
		$fileName		= "class-{$projectSlug}-post-type-{$slug}.php";
		$filePath 		= $this->file($targetDir, $fileName, $output);

		if (false === $filePath)
			return Command::FAILURE;

		$textDomain		= ($project) ? $project : $this->textDomain;
		$nsParts		= ($project) ? array_map('ucfirst', explode('-', $project)) : null;
		$nsDeclPrefix	= ($nsParts) ? implode('', $nsParts) : $this->namespaceRoot;

		$namespaceDecl = $nsDeclPrefix . '\\Post_Type';
		$useDecl       = $this->namespaceRoot . '\\Posts\\Post_Type';
		$content       = <<<PHP
<?php
namespace {$namespaceDecl};

use {$useDecl};

class {$className} extends Post_Type
{
	public function __construct()
	{
		parent::__construct();

		// CPT slug
		\$this->post_type = '{$projectSlug}-{$slug}';

		// CPT labels
		\$this->labels = array(
			'name'		=> _x( '{$name}', 'Post type general name', '{$textDomain}' )
		);

		// CPT arguments
		\$this->args = array(
			'public'	=> true
		);
	}
}

PHP;

		$instanceLine = sprintf(
		    "new %s\\Post_Type\\%s;\n",
		    $nsDeclPrefix,
		    $className
		);
		$label = 'Post Type';

		$this->write( $filePath, $content, $label, $instanceLine, $output, $project );

		return Command::SUCCESS;
	}
}
