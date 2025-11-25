<?php

namespace App\Util;

/**
 * Class ConfigPathUtil
 *
 * Util to resolve configuration file locations
 * 
 * @package App\Util
 */
class ConfigPathUtil
{
    private AppUtil $appUtil;
    private FileSystemUtil $fileSystemUtil;

    public function __construct(AppUtil $appUtil, FileSystemUtil $fileSystemUtil)
    {
        $this->appUtil = $appUtil;
        $this->fileSystemUtil = $fileSystemUtil;
    }

    /**
     * Normalize filename to avoid traversal vulnerabilities
     *
     * @param string $filename The filename to normalize
     *
     * @return string The normalized filename
     */
    public function normalizeFilename(string $filename): string
    {
        $normalized = str_replace('\\', '/', $filename);
        $normalized = str_replace('..', '', $normalized);

        return ltrim($normalized, '/');
    }

    /**
     * Get root directory for default config files
     *
     * @return string The root directory
     */
    public function getDefaultConfigDirectory(): string
    {
        return $this->appUtil->getAppRootDir() . '/config/internal';
    }

    /**
     * Build default config path (read-only)
     *
     * @param string $filename The filename of the configuration file
     *
     * @return string The path to the configuration file
     */
    public function getDefaultConfigPath(string $filename): string
    {
        return $this->getDefaultConfigDirectory() . '/' . $filename;
    }

    /**
     * Get path to writable custom config directory
     *
     * @param string $filename The filename of the configuration file
     *
     * @return string The path to the custom config directory
     */
    public function getCustomConfigPath(string $filename): string
    {
        return rtrim($this->appUtil->getCustomConfigDirectory(), '/') . '/' . $filename;
    }

    /**
     * Get legacy config path in the project root
     *
     * @param string $filename The filename of the configuration file
     *
     * @return string The path to the configuration file
     */
    public function getLegacyCustomConfigPath(string $filename): string
    {
        return $this->appUtil->getAppRootDir() . '/' . $filename;
    }

    /**
     * Ensure modern custom config directory exists
     *
     * @return bool True if directory exists, false otherwise
     */
    public function ensureCustomConfigDirectory(): bool
    {
        return $this->fileSystemUtil->ensureDirectoryExists($this->appUtil->getCustomConfigDirectory());
    }

    /**
     * Resolve an existing custom config path when overrides live either in the new or legacy location
     *
     * @param string $filename The filename of the configuration file
     *
     * @return string|null The path to the custom config file or null if not found
     */
    public function getExistingCustomConfigPath(string $filename): ?string
    {
        $customPath = $this->getCustomConfigPath($filename);
        if ($this->fileSystemUtil->checkIfFileExist($customPath)) {
            return $customPath;
        }

        $legacyPath = $this->getLegacyCustomConfigPath($filename);
        if ($this->fileSystemUtil->checkIfFileExist($legacyPath)) {
            return $legacyPath;
        }

        return null;
    }

    /**
     * Determine writable path by preferring existing overrides while keeping backward compatibility
     *
     * @param string $filename The filename of the configuration file
     *
     * @return string The path to the writable configuration file
     */
    public function getWritableCustomConfigPath(string $filename): string
    {
        $existing = $this->getExistingCustomConfigPath($filename);
        if ($existing !== null) {
            return $existing;
        }

        if ($this->ensureCustomConfigDirectory()) {
            return $this->getCustomConfigPath($filename);
        }

        return $this->getLegacyCustomConfigPath($filename);
    }
}
