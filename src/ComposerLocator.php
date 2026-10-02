<?php

declare(strict_types=1);

namespace Naluz\Installer;

/** Finds the Composer executable: $COMPOSER_BINARY, `composer` on PATH, or a composer.phar in the working directory. */
final class ComposerLocator
{
    /**
     * @param list<string> $pathDirs
     * @return list<string>|null argv prefix to run Composer, or null when it cannot be found
     */
    public static function find(?string $override, array $pathDirs, ?string $cwd = null, string $php = PHP_BINARY): ?array
    {
        if ($override !== null && $override !== '') {
            return is_file($override) ? self::prefix($override, $php) : null;
        }
        $names = DIRECTORY_SEPARATOR === '\\' ? ['composer.bat', 'composer.cmd', 'composer.exe', 'composer.phar'] : ['composer', 'composer.phar'];
        foreach ($pathDirs as $dir) {
            foreach ($names as $name) {
                $candidate = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;
                if (is_file($candidate)) {
                    return self::prefix($candidate, $php);
                }
            }
        }
        if ($cwd !== null && is_file($phar = rtrim($cwd, '/\\') . DIRECTORY_SEPARATOR . 'composer.phar')) {
            return self::prefix($phar, $php);
        }
        return null;
    }

    /** @return list<string> */
    public static function pathDirs(): array
    {
        $path = getenv('PATH');
        return $path === false || $path === '' ? [] : array_values(array_filter(explode(PATH_SEPARATOR, $path)));
    }

    /** @return list<string> */
    private static function prefix(string $file, string $php): array
    {
        return str_ends_with($file, '.phar') ? [$php, $file] : [$file];
    }
}
