<?php

declare(strict_types=1);

namespace WaspCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use WaspCli\Generator\CreateSpec;

#[AsCommand(
    name: 'create:post_type',
    description: 'Creates a new Custom Post Type class using stubs and project configuration'
)]
final class CreatePostTypeCommand extends AbstractCreateCommand
{
    /**
     * Returns the specification used to generate a Custom Post Type class.
     * @return CreateSpec Stub, paths, naming and labels for post types
     *
     * @since 1.0.0
     */
    protected function spec(): CreateSpec
    {
        return new CreateSpec(
            title: 'Custom Post Type Creation',
            stub: 'post_type',
            targetSubdir: 'classes/post-type',
            fileInfix: 'post-type',
            classPrefix: 'Post_Type_',
            namespaceSuffix: 'Post_Type',
            parentClass: 'Posts\\Post_Type',
            successLabel: 'Custom Post Type',
            nameArgumentDescription: 'Name of the Post Type (e.g.: Book)',
        );
    }
}
