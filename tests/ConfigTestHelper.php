<?php

namespace App\Tests;

/**
 * Class ConfigTestHelper
 *
 * Helper for config-related tests
 *
 * @package App\Tests
 */
class ConfigTestHelper
{
    /**
     * Create or overwrite a configuration file under the default config directory
     *
     * @param string $projectDir Project root directory
     * @param string $filename Configuration filename
     * @param string $content File content
     *
     * @return string Absolute file path
     */
    public static function createDefaultConfig(string $projectDir, string $filename, string $content = '{}'): string
    {
        $path = self::getDefaultConfigPath($projectDir, $filename);
        self::ensureDirectory(dirname($path));
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Create or overwrite a configuration file under the custom config directory
     *
     * @param string $customDir Custom config directory
     * @param string $filename Configuration filename
     * @param string $content File content
     *
     * @return string Absolute file path
     */
    public static function createCustomConfig(string $customDir, string $filename, string $content = '{}'): string
    {
        $path = self::getCustomConfigPath($customDir, $filename);
        self::ensureDirectory(dirname($path));
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Get default config path
     *
     * @param string $projectDir Project root directory
     * @param string $filename Configuration filename
     *
     * @return string Absolute file path
     */
    public static function getDefaultConfigPath(string $projectDir, string $filename): string
    {
        return rtrim($projectDir, '/') . '/config/internal/' . ltrim($filename, '/');
    }

    /**
     * Get custom config path
     *
     * @param string $customDir Custom config directory
     * @param string $filename Configuration filename
     *
     * @return string Absolute file path
     */
    public static function getCustomConfigPath(string $customDir, string $filename): string
    {
        return rtrim($customDir, '/') . '/' . ltrim($filename, '/');
    }

    /**
     * Backup existing default config content and replace it with provided content
     *
     * @param string $projectDir Project root directory
     * @param string $filename Configuration filename
     * @param string $content Replacement content
     * @param array<string, string|null> $backups Reference to the backup storage
     *
     * @return void
     */
    public static function backupAndReplaceDefaultConfig(string $projectDir, string $filename, string $content, array &$backups): void {
        $path = self::getDefaultConfigPath($projectDir, $filename);
        self::ensureDirectory(dirname($path));

        $originalContent = file_exists($path) ? file_get_contents($path) : null;
        if ($originalContent === false) {
            $originalContent = null;
        }

        $backups[$path] = $originalContent;
        file_put_contents($path, $content);
    }

    /**
     * Restore default config files from stored backups and reset the backup list
     *
     * @param array<string, string|null> $backups Reference to the backup storage
     *
     * @return void
     */
    public static function restoreDefaultConfigs(array &$backups): void
    {
        foreach ($backups as $path => $originalContent) {
            if ($originalContent === null) {
                if (file_exists($path)) {
                    @unlink($path);
                }
                continue;
            }

            self::ensureDirectory(dirname($path));
            file_put_contents($path, $originalContent);
        }

        $backups = [];
    }

    /**
     * Recursively remove the given path (file or directory)
     *
     * @param string $path Path to remove
     *
     * @return void
     */
    public static function removePath(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @chmod($path, 0777);
            @unlink($path);
            return;
        }

        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            self::removePath($path . DIRECTORY_SEPARATOR . $item);
        }

        @chmod($path, 0777);
        @rmdir($path);
    }

    /**
     * Ensure directory exists (create if missing)
     *
     * @param string $path Directory path
     *
     * @return void
     */
    public static function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }
}
