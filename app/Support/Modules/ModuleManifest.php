<?php

declare(strict_types=1);

namespace App\Support\Modules;

/**
 * Bir modülün `module.json` dosyasının tipli karşılığı.
 *
 * `publish` haritası `{from, to}` çiftlerinden oluşur: `from` paket köküne,
 * `to` proje köküne görelidir. Lifecycle bayrakları install/uninstall
 * davranışını belirler (bkz. AGENTS.md → Module system).
 */
final readonly class ModuleManifest
{
    /**
     * @param  array<int, string>  $areas
     * @param  array<int, array{from: string, to: string}>  $publish
     * @param  array<string, mixed>  $author
     * @param  array<string, mixed>  $links
     * @param  array<string, mixed>  $requires
     */
    public function __construct(
        public string $key,
        public string $name,
        public ?string $description,
        public string $version,
        public string $provider,
        public array $areas = [],
        public array $publish = [],
        public bool $migrate = false,
        public string $permissions = 'keep',
        public bool $purgeData = false,
        public bool $enabled = true,
        public array $author = [],
        public array $links = [],
        public array $requires = [],
        public ?string $path = null,
        public string $type = self::TYPE_EXTENSION,
    ) {}

    /** Eklenti (varsayılan) ya da tema (mağaza ön yüzünün sayfalarını değiştirir). */
    public const TYPE_EXTENSION = 'extension';

    public const TYPE_THEME = 'theme';

    public function isTheme(): bool
    {
        return $this->type === self::TYPE_THEME;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?string $path = null): self
    {
        foreach (['key', 'name', 'version', 'provider'] as $required) {
            if (! isset($data[$required]) || $data[$required] === '') {
                throw new \InvalidArgumentException("Module manifest missing required field: {$required}");
            }
        }

        return new self(
            key: (string) $data['key'],
            name: (string) $data['name'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            version: (string) $data['version'],
            provider: (string) $data['provider'],
            areas: array_values(array_map('strval', $data['areas'] ?? [])),
            publish: self::normalizePublish($data['publish'] ?? []),
            migrate: (bool) ($data['migrate'] ?? false),
            permissions: ($data['permissions'] ?? null) === 'remove' ? 'remove' : 'keep',
            purgeData: (bool) ($data['purge_data'] ?? false),
            enabled: (bool) ($data['enabled'] ?? true),
            author: is_array($data['author'] ?? null) ? $data['author'] : [],
            links: is_array($data['links'] ?? null) ? $data['links'] : [],
            requires: is_array($data['requires'] ?? null) ? $data['requires'] : [],
            path: $path,
            type: ($data['type'] ?? null) === self::TYPE_THEME ? self::TYPE_THEME : self::TYPE_EXTENSION,
        );
    }

    /** `requires` içindeki platform anahtarları (modül değil). */
    public const PLATFORM_REQUIREMENTS = ['php', 'laravel', 'bayiyazilimi'];

    /**
     * Bu modülün ihtiyaç duyduğu diğer modüllerin anahtarları: `requires` içinde
     * platform (php / laravel / bayiyazilimi), `ext-*` ve `vendor/paket` dışındakiler.
     *
     * @return array<int, string>
     */
    public function moduleDependencies(): array
    {
        return array_values(array_filter(
            array_map('strval', array_keys($this->requires)),
            fn (string $key): bool => $key !== $this->key
                && ! in_array($key, self::PLATFORM_REQUIREMENTS, true)
                && ! str_contains($key, '/')
                && ! str_starts_with($key, 'ext-'),
        ));
    }

    /**
     * Modülün kök dizini (module.json'ın bulunduğu klasör).
     */
    public function basePath(): ?string
    {
        return $this->path === null ? null : dirname($this->path);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'provider' => $this->provider,
            'areas' => $this->areas,
            'publish' => $this->publish,
            'migrate' => $this->migrate,
            'permissions' => $this->permissions,
            'purge_data' => $this->purgeData,
            'enabled' => $this->enabled,
            'author' => $this->author,
            'links' => $this->links,
            'requires' => $this->requires,
            'path' => $this->path,
            'type' => $this->type,
        ];
    }

    /**
     * @return array<int, array{from: string, to: string}>
     */
    private static function normalizePublish(mixed $publish): array
    {
        if (! is_array($publish)) {
            return [];
        }

        $normalized = [];

        foreach ($publish as $entry) {
            if (! is_array($entry) || ! isset($entry['from'], $entry['to'])) {
                continue;
            }

            $normalized[] = [
                'from' => (string) $entry['from'],
                'to' => (string) $entry['to'],
            ];
        }

        return $normalized;
    }
}
