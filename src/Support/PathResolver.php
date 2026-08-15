<?php

namespace HarrisonRatcliffe\QueueWatch\Support;

final class PathResolver
{
    /**
     * @param  string[]  $paths
     * @return string[] absolute, deduplicated paths
     */
    public static function resolve(array $paths, string $basePath): array
    {
        return array_values(array_unique(array_map(
            static fn (string $path): string => self::isAbsolute($path)
                ? rtrim($path, '/\\')
                : rtrim($basePath, '/\\').DIRECTORY_SEPARATOR.ltrim($path, '/\\'),
            $paths
        )));
    }

    private static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);
    }
}
