<?php

declare(strict_types=1);

namespace App\Support\Install;

/**
 * Kurulum gereksinimleri: PHP sürümü, eklentiler, yazılabilir klasörler.
 */
class Requirements
{
    public const PHP_VERSION = '8.4.0';

    /** Zorunlu PHP eklentileri. */
    public const EXTENSIONS = [
        'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'ctype', 'json', 'fileinfo',
        'dom', 'xml', 'xmlreader', 'zip', 'iconv', 'curl',
    ];

    public function __construct(private readonly EnvironmentFile $env) {}

    /**
     * @return list<array{key: string, label: string, ok: bool, detail: ?string}>
     */
    public function check(): array
    {
        $checks = [[
            'key' => 'php',
            'label' => __('PHP :version veya üstü', ['version' => '8.4']),
            'ok' => version_compare(PHP_VERSION, self::PHP_VERSION, '>='),
            'detail' => PHP_VERSION,
        ]];

        foreach (self::EXTENSIONS as $extension) {
            $checks[] = [
                'key' => 'ext-'.$extension,
                'label' => __('PHP eklentisi: :name', ['name' => $extension]),
                'ok' => extension_loaded($extension),
                'detail' => null,
            ];
        }

        foreach (['storage', 'bootstrap/cache'] as $directory) {
            $checks[] = [
                'key' => 'dir-'.$directory,
                'label' => __('Yazılabilir klasör: :path', ['path' => $directory]),
                'ok' => is_writable(base_path($directory)),
                'detail' => null,
            ];
        }

        $checks[] = [
            'key' => 'env',
            'label' => __('Yazılabilir dosya: .env'),
            'ok' => $this->env->writable(),
            'detail' => null,
        ];

        return $checks;
    }

    public function passes(): bool
    {
        foreach ($this->check() as $check) {
            if (! $check['ok']) {
                return false;
            }
        }

        return true;
    }
}
