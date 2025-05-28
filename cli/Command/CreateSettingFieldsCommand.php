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
			->addArgument('is_subpage', InputArgument::OPTIONAL, 'If the setting fields are to be displayed on a sub page, this value must be specified as "subpage", default is null', null);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->initialize($input, $output);

		$section		= $input->getArgument('section');
		$page_slug		= $input->getArgument('page_slug');
		$is_subpage		= $input->getArgument('is_subpage');
		$slug			= $this->slugify($section);
		$classSuffix	= str_replace('-', '_', ucwords($slug, '-'));
		$className		= 'Setting_Fields_' . $classSuffix;
		$targetDir		= '/classes/setting-fields';
		$fileName		= "class-{$this->slugRoot}-setting-fields-{$slug}.php";
		$filePath 		= $this->file($targetDir, $fileName, $output);

		if (false === $filePath)
			return Command::FAILURE;

		$namespaceDecl	= $this->namespaceRoot . '\\Setting_Fields';
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
		\$this->option_group 	= '{$this->slugRoot}_{$subpage}setting';
		\$this->option_name 		= '{$this->slugRoot}_{$subpage}options';
		\$this->section_id 		= '{$slug}-section-id';
		\$this->section_title 	= __( '{$section}', '{$this->textDomain}' );
		\$this->field_id 		= '{$slug}-field-id';
		\$this->field_title 		= __( '{$section} fields', '{$this->textDomain}' );
	}

	function fields()
	{
		\$fields = array(
			// Your fields goes here...
		);

		return apply_filters( '{$this->slugRoot}_{$filter}_custom_fields', \$fields );
	}
}

PHP;

		$instanceLine = sprintf(
		    "new %s\\Setting_Fields\\%s;\n",
		    $this->namespaceRoot,
		    $className
		);

		$this->write( $filePath, $content, $instanceLine, $output );

		return Command::SUCCESS;

	}
}
