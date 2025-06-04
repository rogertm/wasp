<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateUserMetaCommand extends AbstractGeneratorCommand
{
	protected static $defaultName = 'create:user_meta';

	protected function configure(): void
	{
		$this
			->setDescription('Creates a new User Meta class file using project config')
			->addArgument('name', InputArgument::REQUIRED, 'User Meta name (e.g., My custom fields)')
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
		$className		= 'User_Meta_' . $classSuffix;
		$targetDir		= ($project) ? '../'.$project.'/classes/user-meta' : 'classes/user-meta';
		$projectSlug	= $project ?: $this->slugRoot;
		$fileName		= "class-{$projectSlug}-user-meta-{$slug}.php";
		$filePath 		= $this->file($targetDir, $fileName, $output);

		if (false === $filePath)
			return Command::FAILURE;

		$nsParts		= ($project) ? array_map('ucfirst', explode('-', $project)) : null;
		$nsDeclPrefix	= ($nsParts) ? implode('', $nsParts) : $this->namespaceRoot;

		$namespaceDecl	= $nsDeclPrefix . '\\Users';
		$useDecl		= $this->namespaceRoot . '\\Users\\User_Meta';
		$filter			= str_replace('-', '_', $slug);
		$content		= <<<PHP
<?php
namespace {$namespaceDecl};

use {$useDecl};

class {$className} extends User_Meta
{

	function fields()
	{
		\$fields = array(
			// Your fields goes here...
		);

		return apply_filters( '{$projectSlug}_{$filter}_user_meta_fields', \$fields );
	}
}

PHP;

		$instanceLine = sprintf(
		    "new %s\\Users\\%s;\n",
		    $nsDeclPrefix,
		    $className
		);
		$label = 'User Meta';

		$this->write( $filePath, $content, $label, $instanceLine, $output, $project );

		return Command::SUCCESS;

	}

}
