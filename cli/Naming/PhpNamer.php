<?php

declare(strict_types=1);

namespace WaspCli\Naming;

use RuntimeException;

final class PhpNamer
{
    /**
     * Converts free-form text into a lowercase slug separated by dashes.
     * @param string $text Source text to normalize
     * @return string Non-empty slug
     *
     * @since 1.0.0
     */
    public function slugify(string $text): string
    {
        $original = $text;
        $translit = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        if ($translit !== false) {
            $text = $translit;
        }

        $text = preg_replace('/[^\p{L}\p{Nd}]+/u', '-', $text) ?? '';
        $text = preg_replace('/-+/', '-', $text) ?? '';
        $slug = strtolower(trim($text, '-'));

        if ($slug === '') {
            throw new RuntimeException(sprintf('Cannot generate a valid slug from "%s".', $original));
        }

        return $slug;
    }

    /**
     * Builds a PHP class-name suffix from a slug, using underscores between words.
     * @param string $slug Dash-separated slug
     * @return string Class suffix such as Photo_Gallery
     *
     * @since 1.0.0
     */
    public function classSuffixFromSlug(string $slug): string
    {
        return str_replace('-', '_', ucwords($slug, '-'));
    }

    /**
     * Derives a PHP namespace identifier from a project slug.
     * @param string $slug Dash-separated project slug
     * @return string PascalCase namespace, prefixed with Project when it would start with a digit
     *
     * @since 1.0.0
     */
    public function namespaceFromSlug(string $slug): string
    {
        $parts = explode('-', $slug);
        $namespace = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $namespace .= ucfirst(strtolower($part));
        }

        if ($namespace === '') {
            throw new RuntimeException("Cannot generate namespace from slug \"$slug\".");
        }

        if (preg_match('/^\d/', $namespace) === 1) {
            $namespace = 'Project' . $namespace;
        }

        return $namespace;
    }

    /**
     * Derives a PHP namespace from a human-readable project name.
     * @param string $text Project display name
     * @return string PascalCase namespace, or WASP when the name yields nothing
     *
     * @since 1.0.0
     */
    public function namespaceFromProjectName(string $text): string
    {
        $trans = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        if ($trans === false) {
            $trans = $text;
        }

        $clean = preg_replace('/[^A-Za-z0-9 ]/', '', $trans) ?? '';
        $parts = array_filter(explode(' ', $clean), static fn (string $part): bool => $part !== '');
        $camel = '';

        foreach ($parts as $part) {
            $camel .= ucfirst(strtolower($part));
        }

        return $camel !== '' ? $camel : 'WASP';
    }

    /**
     * Builds a function prefix from a slug, ending with an underscore.
     * @param string $slug Dash-separated slug
     * @return string Prefix such as wasp_child_
     *
     * @since 1.0.0
     */
    public function functionPrefixFromSlug(string $slug): string
    {
        return str_replace('-', '_', $slug) . '_';
    }

    /**
     * Converts dashes to underscores for WordPress filter or option keys.
     * @param string $value Slug or compound identifier
     * @return string Underscore-separated key
     *
     * @since 1.0.0
     */
    public function filterKey(string $value): string
    {
        return str_replace('-', '_', $value);
    }

    /**
     * Replaces a slug token in a filename while keeping surrounding separators.
     * @param string $name Original filename
     * @param string $oldSlug Slug to replace
     * @param string $newSlug Replacement slug
     * @return string Filename with the slug swapped when a bounded match is found
     *
     * @since 1.0.0
     */
    public function replaceSlugInFilename(string $name, string $oldSlug, string $newSlug): string
    {
        $pattern = '/(^|[-_.])' . preg_quote($oldSlug, '/') . '(?=$|[-_.])/i';
        $result = preg_replace_callback(
            $pattern,
            static function (array $matches) use ($newSlug): string {
                return $matches[1] . $newSlug;
            },
            $name
        );

        return $result ?? $name;
    }
}
