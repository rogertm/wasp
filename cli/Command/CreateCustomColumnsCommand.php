<?php

declare(strict_types=1);

namespace WaspCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use WaspCli\Generator\CreateSpec;

#[AsCommand(
    name: 'create:custom_columns',
    description: 'Creates a new Custom Columns class file using project config'
)]
final class CreateCustomColumnsCommand extends AbstractCreateCommand
{
    /**
     * Returns the specification used to generate a Custom Columns class.
     * @return CreateSpec Stub, paths, naming and labels for custom columns
     *
     * @since 1.0.0
     */
    protected function spec(): CreateSpec
    {
        return new CreateSpec(
            title: 'Create Custom Columns',
            stub: 'custom_columns',
            targetSubdir: 'classes/custom-columns',
            fileInfix: 'custom-columns',
            classPrefix: 'Custom_Columns_',
            namespaceSuffix: 'Custom_Columns',
            parentClass: 'Custom_Columns\\Custom_Columns',
            successLabel: 'Custom Columns',
            nameArgumentDescription: 'Custom columns name (e.g., Product Columns)',
        );
    }
}
