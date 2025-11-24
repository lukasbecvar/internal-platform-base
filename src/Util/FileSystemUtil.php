<?php

namespace App\Util;

use Exception;
use SplFileInfo;
use FilesystemIterator;
use App\Manager\ErrorManager;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class FileSystemUtil
 *
 * Util for manipulate with the file system
 *
 * @package App\Util
 */
class FileSystemUtil
{
    private ErrorManager $errorManager;

    public function __construct(ErrorManager $errorManager)
    {
        $this->errorManager = $errorManager;
    }

    /**
     * Check if file exists
     *
     * @param string $path The path to the file
     *
     * @return bool True if the file exists, false otherwise
     */
    public function checkIfFileExist(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Check if path is a directory
     *
     * @param string $path The path to check
     *
     * @return bool True if the path is a directory, false otherwise
     */
    public function isPathDirectory(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * Get list of files and directories in the specified path
     *
     * @param string $path The path to list files and directories
     * @param bool $recursive Spec for log manager (return all files resursive without directories)
     *
     * @return array<array<mixed>> The list of files and directories
     */
    public function getFilesList(string $path, bool $recursive = false): array
    {
        // set default path if is empty
        if (empty($path)) {
            $path = '/';
        }

        $files = [];

        try {
            $resolvedPath = realpath($path) ?: $path;

            // skip system directories that might cause permission issues
            if (in_array($resolvedPath, ['/proc', '/sys', '/dev', '/run'], true)) {
                return [];
            }

            if (!$this->checkIfFileExist($resolvedPath) || !$this->isPathDirectory($resolvedPath)) {
                return [];
            }

            $iterator = $recursive
                ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolvedPath, FilesystemIterator::SKIP_DOTS))
                : new FilesystemIterator($resolvedPath, FilesystemIterator::SKIP_DOTS);

            /** @var SplFileInfo $fileInfo */
            foreach ($iterator as $fileInfo) {
                $realPath = $fileInfo->getPathname();
                $normalizedPath = realpath($realPath) ?: $realPath;

                // skip system directories and the original path itself
                if (
                    $normalizedPath === '/' ||
                    $normalizedPath === '/boot' ||
                    $normalizedPath === $resolvedPath ||
                    str_starts_with($normalizedPath, '/proc/') ||
                    str_starts_with($normalizedPath, '/sys/') ||
                    str_starts_with($normalizedPath, '/dev/') ||
                    str_starts_with($normalizedPath, '/run/')
                ) {
                    continue;
                }

                $isDir = $fileInfo->isDir();
                if ($recursive && $isDir) {
                    // recursive mode should return files only
                    continue;
                }

                $fileSize = $isDir ? $this->calculateDirectorySize($normalizedPath) : (int) $fileInfo->getSize();
                $formattedSize = $this->formatFileSize($fileSize);

                $files[] = [
                    'name' => $fileInfo->getFilename(),
                    'size' => $formattedSize,
                    'rawSize' => $fileSize,
                    'permissions' => substr(sprintf('%o', $fileInfo->getPerms()), -4),
                    'isDir' => $isDir,
                    'path' => $normalizedPath,
                    'creationTime' => date('Y-m-d H:i:s', $fileInfo->getMTime())
                ];
            }

            // sort the list - directories first, then by name
            usort($files, function ($a, $b) {
                if ($a['isDir'] && !$b['isDir']) {
                    return -1;
                }
                if (!$a['isDir'] && $b['isDir']) {
                    return 1;
                }

                return strcasecmp($a['name'], $b['name']);
            });
        } catch (Exception $e) {
            $this->errorManager->handleError(
                message: 'error listing files: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // return final list
        return $files;
    }

    /**
     * Save content to file
     *
     * @param string $path The path to the file
     * @param string $content The content to save
     *
     * @return bool True if the content was saved successfully, false otherwise
     */
    public function saveFileContent(string $path, string $content): bool
    {
        try {
            $directory = dirname($path);
            if (!$this->isPathDirectory($directory)) {
                if (!$this->ensureDirectoryExists($directory)) {
                    throw new Exception('Failed to prepare directory for file');
                }
            }

            // check if path is directory
            if ($this->isPathDirectory($path) || is_link($path)) {
                $this->errorManager->handleError(
                    message: 'error saving file: ' . $path . ' is a directory or a link',
                    code: Response::HTTP_BAD_REQUEST
                );
            }

            // check if file is a shell script
            $isShellScript = $this->isShellScript($path, $content);

            // fet original file permissions and owner
            $originalPerms = null;
            $fileOwner = null;
            $fileGroup = null;
            if (file_exists($path)) {
                $originalPerms = fileperms($path);
                $fileInfo = stat($path);
                if ($fileInfo !== false) {
                    $fileOwner = $fileInfo['uid'];
                    $fileGroup = $fileInfo['gid'];
                }
            }

            // decode HTML entities in content
            $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5);

            // for shell scripts, ensure we use LF line endings
            if ($isShellScript) {
                // convert all line endings to LF
                $content = str_replace("\r\n", "\n", $content);
                $content = str_replace("\r", "\n", $content);

                // ensure first line has shebang if it's a shell script
                if (!empty($content) && !preg_match('/^#!/', $content)) {
                    // add shebang if it doesn't exist
                    $content = "#!/bin/bash\n" . $content;
                }
            }

            // ensure content ends with a newline character
            if (!empty($content) && substr($content, -1) !== "\n") {
                $content .= "\n";
            }

            // write content to file
            $bytesWritten = @file_put_contents($path, $content, LOCK_EX);
            if ($bytesWritten === false) {
                throw new Exception('Failed to write to file');
            }

            // restore original permissions if it was executable
            if ($originalPerms !== null && ($originalPerms & 0111)) {
                @chmod($path, $originalPerms & 0777);
            } elseif ($isShellScript) {
                // make shell scripts executable
                @chmod($path, 0755);
            }

            // restore original owner and group
            if ($fileOwner !== null && $fileGroup !== null) {
                @chown($path, $fileOwner);
                @chgrp($path, $fileGroup);
            }

            return true;
        } catch (Exception $e) {
            // log error to exception log
            $this->errorManager->logError(
                message: 'error to save file content: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );

            return false;
        }
    }

    /**
     * Delete file or directory
     *
     * @param string $path The path to the file or directory to delete
     *
     * @return bool True if the file or directory was deleted successfully, false otherwise
     */
    public function deleteFileOrDirectory(string $path): bool
    {
        try {
            // check if path exists
            if (!file_exists($path)) {
                $this->errorManager->handleError(
                    message: 'error deleting file: ' . $path . ' does not exist',
                    code: Response::HTTP_BAD_REQUEST
                );
            }

            // delete directory recursively or unlink file
            if ($this->isPathDirectory($path)) {
                $this->deleteDirectoryRecursive($path);
            } elseif (!@unlink($path)) {
                throw new Exception('Failed to delete file');
            }

            return true;
        } catch (Exception $e) {
            // log error to exception log
            $this->errorManager->logError(
                message: 'error to delete file or directory: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );

            return false;
        }
    }

    /**
     * Get full content of file without pagination for editing
     *
     * @param string $path The path to the file
     *
     * @return string The file content or error message
     */
    public function getFullFileContent(string $path): string
    {
        try {
            // check if path is directory
            if ($this->isPathDirectory($path) || is_link($path)) {
                $this->errorManager->handleError(
                    message: 'error opening file: ' . $path . ' is a directory or a link',
                    code: Response::HTTP_BAD_REQUEST
                );
            }

            $fileContent = @file_get_contents($path);
            if ($fileContent === false) {
                return '';
            }

            return $fileContent;
        } catch (Exception $e) {
            // log error to exception log
            $this->errorManager->logError(
                message: 'error to get file content: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );

            // return error message
            return $e->getMessage();
        }
    }

    /**
     * Calculate the total size of a directory including all files and subdirectories
     *
     * @param string $path The path to the directory
     *
     * @return int The total size in bytes
     */
    public function calculateDirectorySize(string $path): int
    {
        try {
            if (!file_exists($path) || !is_dir($path)) {
                return 0;
            }

            $size = 0;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                /** @var SplFileInfo $file */
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }

            return $size;
        } catch (Exception $e) {
            $this->errorManager->logError(
                message: 'Error calculating directory size: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
            return 0;
        }
    }

    /**
     * Format file size to human-readable format
     *
     * @param int $bytes The size in bytes
     * @param int $precision The number of decimal places to round to
     *
     * @return string The formatted size
     */
    public function formatFileSize(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $base = 1024;
        $exponent = (int) floor(log($bytes, $base));
        $value = $bytes / pow($base, $exponent);

        return round($value, $precision) . ' ' . $units[$exponent];
    }

    /**
     * Ensure directory exists (create if missing)
     *
     * @param string $path Directory path
     * @param int $mode Directory permissions
     *
     * @return bool True when directory exists or was created
     */
    public function ensureDirectoryExists(string $path, int $mode = 0775): bool
    {
        if ($this->isPathDirectory($path)) {
            return true;
        }

        if ($this->checkIfFileExist($path)) {
            $this->errorManager->logError(
                message: 'error ensuring directory exists: ' . $path . ' is not a directory',
                code: Response::HTTP_BAD_REQUEST
            );
            return false;
        }

        if (@mkdir($path, $mode, true)) {
            return true;
        }

        $this->errorManager->logError(
            message: 'error ensuring directory exists: failed to create ' . $path,
            code: Response::HTTP_INTERNAL_SERVER_ERROR
        );

        return false;
    }

    /**
     * Delete directory recursively
     *
     * @param string $path Directory path
     *
     * @return void
     */
    private function deleteDirectoryRecursive(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            /** @var SplFileInfo $fileInfo */
            if ($fileInfo->isDir()) {
                @rmdir($fileInfo->getPathname());
            } else {
                @unlink($fileInfo->getPathname());
            }
        }

        @rmdir($path);
    }

    /**
     * Detect if file should be handled as shell script
     *
     * @param string $path File path
     * @param string $content File content
     *
     * @return bool True if file should be handled as shell script
     */
    private function isShellScript(string $path, string $content): bool
    {
        if (str_ends_with($path, '.sh') || str_ends_with($path, '.bash')) {
            return true;
        }

        $trimmedContent = ltrim($content);
        return str_starts_with($trimmedContent, '#!') && stripos($trimmedContent, 'bash') !== false;
    }
}
