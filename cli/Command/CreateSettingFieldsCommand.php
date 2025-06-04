<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateSettingFieldsCommand extends AbstractGeneratorCommand
{
	protected static $defaultName = 'create:setting_fields';

	protected function configure(): void
	{
		$this
			->setDescription('Creates a new Settings Fields class file using project config')
			->addArgument('section', InputArgument::REQUIRED, 'Section name (e.g., My section fields)')
			->addArgument('page_slug', InputArgument::REQUIRED, 'The slug-name of the settings page on which to show the section. (e.g., wasp-dashboard-setting)')
			->addArgument('is_subpage', InputArgument::OPTIONAL, 'If the setting fields are to be displayed on a sub page, this value must be specified as "subpage", default is null', null)
			->addArgument('project', InputArgument::OPTIONAL, 'Project slug where this CPT should be created (e.g., wasp-child). If omitted, uses WASP.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->initialize($input, $output);

		$section		= $input->getArgument('section');
		$page_slug		= $input->getArgument('page_slug');
		$is_subpage		= $input->getArgument('is_subpage');
		$project 		= $input->getArgument('project');
		if (isset($project) && !is_dir('../'. $project)){
			$output->writeln("<error>Project not found: $project</error>");
			return Command::FAILURE;
		}

		$this->baseDir	= ($project) ? $this->baseDir .'/../'. $project : $this->baseDir;
		$slug			= $this->slugify($section);
		$classSuffix	= str_replace('-', '_', ucwords($slug, '-'));
		$className		= 'Setting_Fields_' . $classSuffix;
		$targetDir		= ($project) ? '../'.$project.'/classes/setting-fields' : 'classes/setting-fields';
		$projectSlug	= $project ?: $this->slugRoot;
		$fileName		= "class-{$projectSlug}-setting-fields-{$slug}.php";
		$filePath 		= $this->file($targetDir, $fileName, $output);

		if (false === $filePath)
			return Command::FAILURE;

		$textDomain		= ($project) ? $project : $this->textDomain;
		$nsParts		= ($project) ? array_map('ucfirst', explode('-', $project)) : null;
		$nsDeclPrefix	= ($nsParts) ? implode('', $nsParts) : $this->namespaceRoot;

		$namespaceDecl	= $nsDeclPrefix . '\\Setting_Fields';
		$useDecl		= $this->namespaceRoot . '\\Setting_Fields\\Setting_Fields';
		$filter			= str_replace('-', '_', $slug);
		$subpage 		= ('subpage' === $is_subpage) ? 'sub' : null;
		$content		= <<<PHP
<?php
namespace {$namespaceDecl};

use {$useDecl};

class {$className} extends Setting_Fields
{
	public function __construct()
	{
		parent::__construct();
		\$this->slug 			= '{$page_slug}';
		\$this->option_group 	= '{$projectSlug}_{$subpage}setting';
		\$this->option_name 		= '{$projectSlug}_{$subpage}options';
		\$this->section_id 		= '{$slug}-section-id';
		\$this->section_title 	= __( '{$section}', '{$textDomain}' );
		\$this->field_id 		= '{$slug}-field-id';
		\$this->field_title 		= __( '{$section} fields', '{$textDomain}' );
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
		    "new %s\\Setting_Fields\\%s;\n",
		    $nsDeclPrefix,
		    $className
		);
		$label = 'Setting Fields';

		$this->write( $filePath, $content, $label, $instanceLine, $output, $project );

		return Command::SUCCESS;

	}
}
