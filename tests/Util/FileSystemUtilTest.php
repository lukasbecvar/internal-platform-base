<?php

namespace App\Tests\Util;

use App\Util\FileSystemUtil;
use App\Manager\ErrorManager;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class FileSystemUtilTest
 *
 * Comprehensive test cases for file system util
 *
 * @package App\Tests\Util
 */
#[CoversClass(FileSystemUtil::class)]
class FileSystemUtilTest extends TestCase
{
    private FileSystemUtil $fileSystemUtil;
    private ErrorManager & MockObject $errorManagerMock;

    protected function setUp(): void
    {
        $this->errorManagerMock = $this->createMock(ErrorManager::class);
        $this->fileSystemUtil = new FileSystemUtil($this->errorManagerMock);
    }

    /**
     * Test check if file exists when file exists
     *
     * @return void
     */
    public function testCheckIfFileExist(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'fs_util_');

        $this->assertTrue($this->fileSystemUtil->checkIfFileExist($tempFile));

        unlink($tempFile);
    }

    /**
     * Test check if file exists when file does not exist
     *
     * @return void
     */
    public function testCheckIfFileExistWhenFileDoesNotExist(): void
    {
        $this->assertFalse($this->fileSystemUtil->checkIfFileExist('/tmp/does-not-exist-' . uniqid()));
    }

    /**
     * Test check if path is directory
     *
     * @return void
     */
    public function testIsPathDirectory(): void
    {
        $tempDir = sys_get_temp_dir() . '/fs_util_' . uniqid();
        mkdir($tempDir);

        $this->assertTrue($this->fileSystemUtil->isPathDirectory($tempDir));
        $this->assertFalse($this->fileSystemUtil->isPathDirectory($tempDir . '/missing'));

        rmdir($tempDir);
    }

    /**
     * Test get files list returns directories and files
     *
     * @return void
     */
    public function testGetFilesList(): void
    {
        $baseDir = sys_get_temp_dir() . '/fs_util_' . uniqid();
        mkdir($baseDir);
        file_put_contents($baseDir . '/config.json', '{"key":"value"}');
        mkdir($baseDir . '/nested');

        $result = $this->fileSystemUtil->getFilesList($baseDir);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $names = array_column($result, 'name');
        $this->assertContains('config.json', $names);
        $this->assertContains('nested', $names);

        unlink($baseDir . '/config.json');
        rmdir($baseDir . '/nested');
        rmdir($baseDir);
    }

    /**
     * Test get files list recursive returns only files
     *
     * @return void
     */
    public function testGetFilesListRecursive(): void
    {
        $baseDir = sys_get_temp_dir() . '/fs_util_' . uniqid();
        mkdir($baseDir);
        mkdir($baseDir . '/nested');
        file_put_contents($baseDir . '/nested/output.log', 'log');

        $result = $this->fileSystemUtil->getFilesList($baseDir, true);

        $this->assertCount(1, $result);
        $this->assertSame('output.log', $result[0]['name']);

        unlink($baseDir . '/nested/output.log');
        rmdir($baseDir . '/nested');
        rmdir($baseDir);
    }

    /**
     * Test save file content creates file and appends newline
     *
     * @return void
     */
    public function testSaveFileContentCreatesFile(): void
    {
        $baseDir = sys_get_temp_dir() . '/fs_util_' . uniqid();
        $path = $baseDir . '/data/output.json';
        $content = '{"status":"ok"}';

        $result = $this->fileSystemUtil->saveFileContent($path, $content);

        $this->assertTrue($result);
        $this->assertFileExists($path);
        $fileContent = file_get_contents($path) ?: '';
        $this->assertStringContainsString('status', $fileContent);
        $this->assertStringEndsWith("\n", $fileContent);

        unlink($path);
        rmdir($baseDir . '/data');
        rmdir($baseDir);
    }

    /**
     * Test save file content fails when path is directory
     *
     * @return void
     */
    public function testSaveFileContentWhenPathIsDirectory(): void
    {
        $this->errorManagerMock
            ->expects($this->once())
            ->method('handleError')
            ->willThrowException(new HttpException(Response::HTTP_BAD_REQUEST, 'dir error'));
        $this->errorManagerMock
            ->expects($this->once())
            ->method('logError')
            ->with(
                $this->stringContains('dir error'),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );

        $tempDir = sys_get_temp_dir() . '/fs_util_' . uniqid();
        mkdir($tempDir);

        try {
            $this->assertFalse($this->fileSystemUtil->saveFileContent($tempDir, 'content'));
        } finally {
            rmdir($tempDir);
        }
    }

    /**
     * Test delete file removes file
     *
     * @return void
     */
    public function testDeleteFileOrDirectoryRemovesFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'fs_util_');

        $this->assertTrue($this->fileSystemUtil->deleteFileOrDirectory($tempFile));
        $this->assertFileDoesNotExist($tempFile);
    }

    /**
     * Test delete directory removes directory recursively
     *
     * @return void
     */
    public function testDeleteFileOrDirectoryRemovesDirectory(): void
    {
        $tempDir = sys_get_temp_dir() . '/fs_util_' . uniqid();
        mkdir($tempDir);
        file_put_contents($tempDir . '/file.txt', 'value');

        $this->assertTrue($this->fileSystemUtil->deleteFileOrDirectory($tempDir));
        $this->assertDirectoryDoesNotExist($tempDir);
    }

    /**
     * Test delete file or directory when path does not exist
     *
     * @return void
     */
    public function testDeleteFileOrDirectoryWhenPathDoesNotExist(): void
    {
        $missingPath = '/tmp/missing-' . uniqid();
        $this->errorManagerMock
            ->expects($this->once())
            ->method('handleError')
            ->willThrowException(new HttpException(Response::HTTP_BAD_REQUEST, 'missing path'));
        $this->errorManagerMock
            ->expects($this->once())
            ->method('logError')
            ->with(
                $this->stringContains('missing path'),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );

        $this->assertFalse($this->fileSystemUtil->deleteFileOrDirectory($missingPath));
    }

    /**
     * Test get full file content returns file data
     *
     * @return void
     */
    public function testGetFullFileContent(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'fs_util_');
        file_put_contents($tempFile, 'full-content');

        $result = $this->fileSystemUtil->getFullFileContent($tempFile);

        $this->assertSame("full-content", $result);

        unlink($tempFile);
    }

    /**
     * Test get full file content when path is directory
     *
     * @return void
     */
    public function testGetFullFileContentWhenPathIsDirectory(): void
    {
        $this->errorManagerMock
            ->expects($this->once())
            ->method('handleError')
            ->willThrowException(new HttpException(Response::HTTP_BAD_REQUEST, 'open dir'));
        $this->errorManagerMock
            ->expects($this->once())
            ->method('logError')
            ->with(
                $this->stringContains('open dir'),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );

        $tempDir = sys_get_temp_dir() . '/fs_util_' . uniqid();
        mkdir($tempDir);

        try {
            $result = $this->fileSystemUtil->getFullFileContent($tempDir);
            $this->assertSame('open dir', $result);
        } finally {
            rmdir($tempDir);
        }
    }

    /**
     * Test calculate directory size sums file sizes
     *
     * @return void
     */
    public function testCalculateDirectorySize(): void
    {
        $tempDir = sys_get_temp_dir() . '/fs_util_' . uniqid();
        mkdir($tempDir);
        file_put_contents($tempDir . '/short.txt', '12345');
        file_put_contents($tempDir . '/long.txt', str_repeat('a', 2048));

        $result = $this->fileSystemUtil->calculateDirectorySize($tempDir);

        $this->assertGreaterThanOrEqual(2053, $result);

        unlink($tempDir . '/short.txt');
        unlink($tempDir . '/long.txt');
        rmdir($tempDir);
    }

    /**
     * Test format file size returns readable string
     *
     * @return void
     */
    public function testFormatFileSize(): void
    {
        $this->assertSame('0 B', $this->fileSystemUtil->formatFileSize(0));
        $this->assertSame('1.95 KB', $this->fileSystemUtil->formatFileSize(2000));
        $this->assertSame('1.91 MB', $this->fileSystemUtil->formatFileSize(2000000));
    }

    /**
     * Test ensure directory exists creates directory when missing
     *
     * @return void
     */
    public function testEnsureDirectoryExists(): void
    {
        $tempDir = sys_get_temp_dir() . '/fs_util_' . uniqid();

        $this->assertTrue($this->fileSystemUtil->ensureDirectoryExists($tempDir));
        $this->assertDirectoryExists($tempDir);

        rmdir($tempDir);
    }

    /**
     * Test ensure directory exists logs error when path is file
     *
     * @return void
     */
    public function testEnsureDirectoryExistsWhenPathIsFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'fs_util_');
        $this->errorManagerMock
            ->expects($this->once())
            ->method('logError')
            ->with(
                $this->stringContains('not a directory'),
                Response::HTTP_BAD_REQUEST
            );

        $this->assertFalse($this->fileSystemUtil->ensureDirectoryExists($tempFile));

        unlink($tempFile);
    }
}
