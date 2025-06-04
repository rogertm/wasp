<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateMetaBoxCommand extends AbstractGeneratorCommand
{
	protected static $defaultName = 'create:meta_box';

	protected function configure(): void
	{
		$this
			->setDescription('Creates a new Meta Box class file using project config')
			->addArgument('name', InputArgument::REQUIRED, 'Meta Box name (e.g., My custom fields)')
			->addArgument('screen', InputArgument::REQUIRED, 'The screen or screens on which to show the box, such as a post type. (e.g., wasp-book)')
			->addArgument('project', InputArgument::OPTIONAL, 'Project slug where this CPT should be created (e.g., wasp-child). If omitted, uses WASP.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->initialize($input, $output);

		$name			= $input->getArgument('name');
		$screen			= $input->getArgument('screen');
		$project 		= $input->getArgument('project');
		if (isset($project) && !is_dir('../'. $project)){
			$output->writeln("<error>Project not found: $project</error>");
			return Command::FAILURE;
		}

		$this->baseDir	= ($project) ? $this->baseDir .'/../'. $project : $this->baseDir;
		$slug			= $this->slugify($name);
		$classSuffix	= str_replace('-', '_', ucwords($slug, '-'));
		$className		= 'Meta_Box_' . $classSuffix;
		$targetDir		= ($project) ? '../'.$project.'/classes/meta-box' : 'classes/meta-box';
		$projectSlug	= $project ?: $this->slugRoot;
		$fileName		= "class-{$projectSlug}-meta-box-{$slug}.php";
		$filePath 		= $this->file($targetDir, $fileName, $output);

		if (false === $filePath)
			return Command::FAILURE;

		$textDomain		= ($project) ? $project : $this->textDomain;
		$nsParts		= ($project) ? array_map('ucfirst', explode('-', $project)) : null;
		$nsDeclPrefix	= ($nsParts) ? implode('', $nsParts) : $this->namespaceRoot;

		$namespaceDecl	= $nsDeclPrefix . '\\Meta_Box';
		$useDecl		= $this->namespaceRoot . '\\Meta_Box\\Meta_Box';
		$filter			= str_replace('-', '_', $slug);
		$content		= <<<PHP
<?php
namespace {$namespaceDecl};

use {$useDecl};

class {$className} extends Meta_Box
{
	public function __construct()
	{
		parent::__construct();
		\$this->id				= '{$slug}-custom-field';
		\$this->title			= __( '{$name}', '{$textDomain}' );
		\$this->screen			= '{$screen}';
		\$this->context			= 'advanced';
		\$this->priority		= 'default';
		\$this->callback_args	= null;
	}

	function fields()
	{
		\$fields = array(
			// Your fields goes here...
		);

		return apply_filters( '{$projectSlug}_{$filter}_custom_fields', \$fields );
	}
}

PHP;

		$instanceLine = sprintf(
		    "new %s\\Meta_Box\\%s;\n",
		    $nsDeclPrefix,
		    $className
		);
		$label = 'Meta Box';

		$this->write( $filePath, $content, $label, $instanceLine, $output, $project );

		return Command::SUCCESS;

	}
}
