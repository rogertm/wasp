<?php

declare(strict_types=1);

namespace WaspCli\Naming;

use RuntimeException;

final class PhpNamer
{
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

    public function classSuffixFromSlug(string $slug): string
    {
        return str_replace('-', '_', ucwords($slug, '-'));
    }

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

    public function functionPrefixFromSlug(string $slug): string
    {
        return str_replace('-', '_', $slug) . '_';
    }

    public function filterKey(string $value): string
    {
        return str_replace('-', '_', $value);
    }

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
