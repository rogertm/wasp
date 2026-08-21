<?php

declare(strict_types=1);

namespace WaspCli\Config;

use RuntimeException;

final class ProjectConfig
{
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

    public static function defaults(): self
    {
        return new self('WASP', 'wasp', 'wasp_', 'wasp');
    }

    /**
     * @param array<string, mixed> $data
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
     * @return array{namespace:string,slug:string,function_prefix:string,text_domain:string}
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

    public function toJson(): string
    {
        $json = json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode project config as JSON.');
        }

        return $json . PHP_EOL;
    }

    public function writeTo(string $path): void
    {
        $written = file_put_contents($path, $this->toJson(), LOCK_EX);
        if ($written === false) {
            throw new RuntimeException("Cannot write config file: $path");
        }
    }
}
