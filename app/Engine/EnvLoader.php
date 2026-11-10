<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Parses .env files using KEY=VALUE format.
 *
 * One variable per line. Lines starting with # are comments.
 * Quoted values ("value with spaces") are supported.
 * Does not overwrite existing environment variables.
 */
final class EnvLoader
{
    public static function load(string $path): void
    {
        // Containers supply secrets through process environment, not a tracked .env file.
        foreach (getenv() as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $_ENV[$name] ??= $value;
                $_SERVER[$name] ??= $value;
            }
        }
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Must contain = to be a valid env line
            if (!str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Skip if name is empty
            if ($name === '') {
                continue;
            }

            // Strip surrounding quotes
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            // Resolve ${VAR} references within values
            $value = preg_replace_callback('/\$\{([A-Z_]+)\}/', function (array $matches): string {
                return $_ENV[$matches[1]] ?? $_SERVER[$matches[1]] ?? '';
            }, $value) ?? $value;

            // Do not overwrite existing environment variables
            if (!array_key_exists($name, $_ENV) && !array_key_exists($name, $_SERVER)) {
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}
