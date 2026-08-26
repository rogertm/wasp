<?php

declare(strict_types=1);

namespace WaspCli\Config;

use RuntimeException;

final class ProjectConfig
{
    /**
     * Creates a validated project configuration.
     * @param string $namespace PHP namespace prefix for generated classes
     * @param string $slug Project slug using lowercase letters, numbers and dashes
     * @param string $functionPrefix Prefix used for generated PHP functions
     * @param string $textDomain WordPress text domain for translations
     * @return void
     *
     * @since 1.0.0
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $slug,
        public readonly string $functionPrefix,
        public readonly string $textDomain,
    ) {
        if ($this->namespace === '') {
            throw new RuntimeException('Invalid config: namespace cannot be empty.');
        }

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $this->slug)) {
            throw new RuntimeException('Invalid config: slug must use lowercase letters, numbers and dashes.');
        }

        if ($this->functionPrefix === '') {
            throw new RuntimeException('Invalid config: function_prefix cannot be empty.');
        }

        if ($this->textDomain === '') {
            throw new RuntimeException('Invalid config: text_domain cannot be empty.');
        }
    }

    /**
     * Returns the default WASP project configuration.
     * @return self Default namespace, slug, function prefix and text domain
     *
     * @since 1.0.0
     */
    public static function defaults(): self
    {
        return new self('WASP', 'wasp', 'wasp_', 'wasp');
    }

    /**
     * Builds a configuration from an associative array, filling missing keys with defaults.
     * @param array<string, mixed> $data Raw config values keyed by namespace, slug, function_prefix and text_domain
     * @return self Validated project configuration
     *
     * @since 1.0.0
     */
    public static function fromArray(array $data): self
    {
        $defaults = self::defaults();

        return new self(
            (string) ($data['namespace'] ?? $defaults->namespace),
            (string) ($data['slug'] ?? $defaults->slug),
            (string) ($data['function_prefix'] ?? $defaults->functionPrefix),
            (string) ($data['text_domain'] ?? $defaults->textDomain),
        );
    }

    /**
     * Loads configuration from a JSON file on disk.
     * @param string $path Absolute path to the JSON config file
     * @param bool $allowMissing When true, missing files return defaults instead of throwing
     * @return self Configuration parsed from JSON or defaults
     *
     * @since 1.0.0
     */
    public static function fromFile(string $path, bool $allowMissing = false): self
    {
        if (! is_file($path)) {
            if ($allowMissing) {
                return self::defaults();
            }

            throw new RuntimeException("Config not found at $path. Run project:rename first.");
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("Unable to read config file: $path");
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new RuntimeException("JSON at $path is not valid.");
        }

        return self::fromArray($decoded);
    }

    /**
     * Exports the configuration as an associative array for JSON encoding.
     * @return array{namespace:string,slug:string,function_prefix:string,text_domain:string} Serializable config values
     *
     * @since 1.0.0
     */
    public function toArray(): array
    {
        return [
            'namespace' => $this->namespace,
            'slug' => $this->slug,
            'function_prefix' => $this->functionPrefix,
            'text_domain' => $this->textDomain,
        ];
    }

    /**
     * Encodes the configuration as pretty-printed JSON with a trailing newline.
     * @return string JSON document ready to write to disk
     *
     * @since 1.0.0
     */
    public function toJson(): string
    {
        $json = json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode project config as JSON.');
        }

        return $json . PHP_EOL;
    }

    /**
     * Writes the JSON configuration to the given path.
     * @param string $path Destination file path
     * @return void
     *
     * @since 1.0.0
     */
    public function writeTo(string $path): void
    {
        $written = file_put_contents($path, $this->toJson(), LOCK_EX);
        if ($written === false) {
            throw new RuntimeException("Cannot write config file: $path");
        }
    }
}
