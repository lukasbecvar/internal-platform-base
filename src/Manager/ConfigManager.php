<?php

namespace App\Manager;

use Exception;
use App\Util\AppUtil;
use App\Util\FileSystemUtil;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ConfigManager
 *
 * Manager for configuration system
 *
 * @package App\Manager
 */
class ConfigManager
{
    private AppUtil $appUtil;
    private LogManager $logManager;
    private ErrorManager $errorManager;
    private FileSystemUtil $fileSystemUtil;

    public function __construct(
        AppUtil $appUtil,
        LogManager $logManager,
        ErrorManager $errorManager,
        FileSystemUtil $fileSystemUtil
    ) {
        $this->appUtil = $appUtil;
        $this->logManager = $logManager;
        $this->errorManager = $errorManager;
        $this->fileSystemUtil = $fileSystemUtil;
    }

    /**
     * Get list of internal configuration files
     *
     * @return list<array{filename: string, is_custom: bool}> List of internal configuration files
     */
    public function getinternalConfigs(): array
    {
        // path to internal configuration files
        $defaultConfigPath = $this->appUtil->getAppRootDir() . '/config/internal';

        // get list of files in internal configuration directory
        $files = $this->fileSystemUtil->getFilesList($defaultConfigPath);

        $configs = [];
        foreach ($files as $file) {
            /** @var string $filename */
            $filename = $file['name'];
            $isCustom = $this->isCustomConfig($filename);
            $configs[] = [
                'filename' => $filename,
                'is_custom' => $isCustom
            ];
        }

        return $configs;
    }

    /**
     * Get content of specific internal configuration file
     *
     * @param string $filename The filename of the configuration file
     *
     * @return string|null The content of the configuration file or null if file not found
     */
    public function readConfig(string $filename): ?string
    {
        // build config file paths
        $filename = $this->normalizeFilename($filename);
        $customPath = $this->getExistingCustomConfigPath($filename);
        $defaultPath = $this->getDefaultConfigPath($filename);

        // check if custom config file exists
        $path = $customPath ?? $defaultPath;

        // check if config file exists
        if (!$this->fileSystemUtil->checkIfFileExist($path)) {
            return null;
        }

        // read config file content
        return $this->fileSystemUtil->getFullFileContent($path);
    }

    /**
     * Write content to specific internal configuration file (write to custom config path)
     *
     * @param string $filename The filename of the configuration file
     * @param string $content The content to write to the configuration file
     *
     * @return bool True if write operation was successful, false otherwise
     */
    public function writeConfig(string $filename, string $content): bool
    {
        // build path to custom config file
        $filename = $this->normalizeFilename($filename);
        $path = $this->getWritableCustomConfigPath($filename);

        // rewrite custom config file content
        $result = $this->fileSystemUtil->saveFileContent($path, $content);

        // check if write operation was successful
        if ($result) {
            $this->logManager->log(
                name: 'internal-config',
                message: 'Updated config file: ' . $filename,
                level: LogManager::LEVEL_INFO
            );
        }

        return $result;
    }

    /**
     * Copy specific internal configuration file to root directory
     *
     * @param string $filename The filename of the configuration file
     *
     * @return bool True if copy operation was successful, false otherwise
     */
    public function copyConfigToRoot(string $filename): bool
    {
        $filename = $this->normalizeFilename($filename);
        $sourcePath = $this->getDefaultConfigPath($filename);
        $destinationPath = $this->ensureCustomConfigDirectory()
            ? $this->getCustomConfigPath($filename)
            : $this->getLegacyCustomConfigPath($filename);

        // check if source file exists and destination file does not
        if ($this->fileSystemUtil->checkIfFileExist($sourcePath) && !$this->fileSystemUtil->checkIfFileExist($destinationPath)) {
            // get default config file content
            $content = $this->fileSystemUtil->getFullFileContent($sourcePath);

            // create custom config file
            $result = $this->fileSystemUtil->saveFileContent($destinationPath, $content);

            // check if write operation was successful
            if ($result) {
                $this->logManager->log(
                    name: 'internal-config',
                    message: 'Created custom config file: ' . $filename,
                    level: LogManager::LEVEL_INFO
                );
            }
            return $result;
        }

        return false;
    }

    /**
     * Check if specific internal configuration file is a custom config file
     *
     * @param string $filename The filename of the configuration file
     *
     * @return bool True if the file is a custom config file, false otherwise
     */
    public function isCustomConfig(string $filename): bool
    {
        $filename = $this->normalizeFilename($filename);
        return $this->fileSystemUtil->checkIfFileExist($this->getCustomConfigPath($filename))
            || $this->fileSystemUtil->checkIfFileExist($this->getLegacyCustomConfigPath($filename));
    }

    /**
     * Delete specific internal configuration file (reset to default)
     *
     * @param string $filename The filename of the configuration file
     *
     * @return bool True if delete operation was successful, false otherwise
     */
    public function deleteConfig(string $filename): bool
    {
        $filename = $this->normalizeFilename($filename);
        $deleted = false;
        $customPath = $this->getCustomConfigPath($filename);
        $legacyPath = $this->getLegacyCustomConfigPath($filename);

        if ($this->fileSystemUtil->checkIfFileExist($customPath)) {
            $deleted = $this->fileSystemUtil->deleteFileOrDirectory($customPath);
        }

        if ($this->fileSystemUtil->checkIfFileExist($legacyPath)) {
            $deleted = $this->fileSystemUtil->deleteFileOrDirectory($legacyPath) || $deleted;
        }

        if ($deleted) {
            $this->logManager->log(
                name: 'internal-config',
                message: 'Deleted custom config file: ' . $filename,
                level: LogManager::LEVEL_WARNING
            );
        }

        return $deleted;
    }

    /**
     * Update specific feature flag in feature-flags.json
     *
     * @param string $feature The feature flag key
     * @param bool $value New value
     *
     * @throws Exception If config cannot be read or written
     */
    public function updateFeatureFlag(string $feature, bool $value): void
    {
        $configFilename = 'feature-flags.json';

        // read current config
        $content = $this->readConfig($configFilename);
        if ($content === null) {
            $this->errorManager->handleError(
                message: 'error updating feature flag: ' . $configFilename . ' not found',
                code: Response::HTTP_NOT_FOUND
            );
        }

        // decode json
        $config = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        // check if feature exists
        if (!array_key_exists($feature, $config)) {
            $this->errorManager->handleError(
                message: 'error updating feature flag: ' . $feature . ' does not exist in ' . $configFilename,
                code: Response::HTTP_NOT_FOUND
            );
        }

        // update feature flag value
        $config[$feature] = $value;

        // encode back to json (pretty for readability)
        $newContent = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        // write updated config
        $result = $this->writeConfig($configFilename, $newContent);
        if (!$result) {
            $this->errorManager->handleError(
                message: 'error updating feature flag: failed to write updated config to ' . $configFilename,
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // log update
        $this->logManager->log(
            name: 'feature-flag',
            message: 'Feature flag ' . $feature . ' set to ' . ($value ? 'true' : 'false'),
            level: LogManager::LEVEL_INFO
        );
    }

    /**
     * Normalize filename to avoid unsafe paths
     */
    private function normalizeFilename(string $filename): string
    {
        $normalized = str_replace('\\', '/', $filename);
        $normalized = str_replace('..', '', $normalized);

        return ltrim($normalized, '/');
    }

    /**
     * Get default config path (read-only)
     */
    private function getDefaultConfigPath(string $filename): string
    {
        return $this->appUtil->getAppRootDir() . '/config/internal/' . $filename;
    }

    /**
     * Get path to new writable custom config directory
     */
    private function getCustomConfigPath(string $filename): string
    {
        return rtrim($this->appUtil->getCustomConfigDirectory(), '/') . '/' . $filename;
    }

    /**
     * Get legacy custom config path (project root)
     */
    private function getLegacyCustomConfigPath(string $filename): string
    {
        return $this->appUtil->getAppRootDir() . '/' . $filename;
    }

    /**
     * Ensure custom config directory exists
     */
    private function ensureCustomConfigDirectory(): bool
    {
        return $this->fileSystemUtil->ensureDirectoryExists($this->appUtil->getCustomConfigDirectory());
    }

    /**
     * Get existing custom config path if available (new path preferred)
     *
     * @return string|null
     */
    private function getExistingCustomConfigPath(string $filename): ?string
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
     * Determine writable path for config updates (respecting legacy files)
     */
    private function getWritableCustomConfigPath(string $filename): string
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
