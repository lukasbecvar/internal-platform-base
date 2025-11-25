<?php

namespace App\Tests\Manager;

use App\Util\AppUtil;
use App\Manager\LogManager;
use App\Util\FileSystemUtil;
use App\Util\ConfigPathUtil;
use App\Manager\ErrorManager;
use App\Manager\ConfigManager;
use PHPUnit\Framework\TestCase;
use App\Tests\ConfigTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class ConfigManagerTest
 *
 * Test cases for configuration manager
 *
 * @package App\Tests\Manager
 */
#[CoversClass(ConfigManager::class)]
class ConfigManagerTest extends TestCase
{
    private string $tempRoot;
    private string $customDir;
    private ConfigManager $configManager;
    private FileSystemUtil $fileSystemUtil;
    private ConfigPathUtil $configPathUtil;
    private AppUtil & MockObject $appUtilMock;
    private LogManager & MockObject $logManagerMock;
    private ErrorManager & MockObject $errorManagerMock;

    protected function setUp(): void
    {
        // create temp root directory
        $this->tempRoot = sys_get_temp_dir() . '/config_manager_' . uniqid();
        $this->customDir = $this->tempRoot . '/custom-configs';
        mkdir($this->tempRoot . '/config/internal', 0777, true);
        mkdir($this->customDir, 0777, true);

        // mock dependencies
        $this->appUtilMock = $this->createMock(AppUtil::class);
        $this->logManagerMock = $this->createMock(LogManager::class);
        $this->errorManagerMock = $this->createMock(ErrorManager::class);
        $this->fileSystemUtil = new FileSystemUtil($this->errorManagerMock);
        $this->configPathUtil = new ConfigPathUtil($this->appUtilMock, $this->fileSystemUtil);

        // mock app util
        $this->appUtilMock->method('getAppRootDir')->willReturn($this->tempRoot);
        $this->appUtilMock->method('getCustomConfigDirectory')->willReturn($this->customDir);

        // create config manager instance
        $this->configManager = new ConfigManager(
            $this->logManagerMock,
            $this->errorManagerMock,
            $this->fileSystemUtil,
            $this->configPathUtil
        );
    }

    protected function tearDown(): void
    {
        ConfigTestHelper::removePath($this->tempRoot);
    }

    /**
     * Test get internal configurations list
     *
     * @return void
     */
    public function testGetInternalConfigs(): void
    {
        ConfigTestHelper::createDefaultConfig($this->tempRoot, 'config1.json');
        ConfigTestHelper::createDefaultConfig($this->tempRoot, 'config2.json');
        ConfigTestHelper::createCustomConfig($this->customDir, 'config2.json');

        // call tested method
        $result = $this->configManager->getInternalConfigs();

        // assert result
        $this->assertEquals([
            ['filename' => 'config1.json', 'is_custom' => false],
            ['filename' => 'config2.json', 'is_custom' => true]
        ], $result);
    }

    /**
     * Test read internal configuration file
     *
     * @return void
     */
    public function testReadConfigWhenCustomFileExists(): void
    {
        $filename = 'test.json';
        $content = '{"key":"value"}';
        ConfigTestHelper::createCustomConfig($this->customDir, $filename, $content);

        // call tested method
        $result = $this->configManager->readConfig($filename);

        // assert result
        $this->assertEquals($content, $result);
    }

    /**
     * Test read internal configuration file when only default file exists
     *
     * @return void
     */
    public function testReadConfigWhenOnlyDefaultFileExists(): void
    {
        $filename = 'test.json';
        $content = '{"key":"default"}';
        ConfigTestHelper::createDefaultConfig($this->tempRoot, $filename, $content);

        // call tested method
        $result = $this->configManager->readConfig($filename);

        // assert result
        $this->assertEquals($content, $result);
    }

    /**
     * Test read internal configuration file when file does not exist
     *
     * @return void
     */
    public function testReadConfigWhenFileDoesNotExist(): void
    {
        // call tested method
        $result = $this->configManager->readConfig('missing.json');

        // assert result
        $this->assertNull($result);
    }

    /**
     * Test write internal configuration file success
     *
     * @return void
     */
    public function testWriteConfigSuccess(): void
    {
        $filename = 'test.json';
        $content = '{"foo":"bar"}';
        $path = ConfigTestHelper::getCustomConfigPath($this->customDir, $filename);

        // expect log to be called
        $this->logManagerMock->expects($this->once())->method('log');

        // call tested method
        $result = $this->configManager->writeConfig($filename, $content);

        // assert result
        $this->assertTrue($result);
        $this->assertFileExists($path);
        $this->assertSame($content . "\n", file_get_contents($path));
    }

    /**
     * Test copy internal configuration file to root directory success
     *
     * @return void
     */
    public function testCopyConfigToRootSuccess(): void
    {
        $filename = 'new.json';
        $content = '{"data":"new"}';
        ConfigTestHelper::createDefaultConfig($this->tempRoot, $filename, $content);

        // expect log to be called
        $this->logManagerMock->expects($this->once())->method('log');

        // call tested method
        $result = $this->configManager->copyConfigToRoot($filename);

        // assert result
        $this->assertTrue($result);
        $this->assertSame($content, trim((string) file_get_contents(ConfigTestHelper::getCustomConfigPath($this->customDir, $filename))));
    }

    /**
     * Test copy internal configuration file to root directory when source does not exist
     *
     * @return void
     */
    public function testCopyConfigToRootWhenSourceDoesNotExist(): void
    {
        // call tested method
        $result = $this->configManager->copyConfigToRoot('missing.json');

        // assert result
        $this->assertFalse($result);
    }

    /**
     * Test copy internal configuration file to root directory when destination exists
     *
     * @return void
     */
    public function testCopyConfigToRootWhenDestinationExists(): void
    {
        $filename = 'new.json';
        ConfigTestHelper::createDefaultConfig($this->tempRoot, $filename, '{"data":"default"}');
        ConfigTestHelper::createCustomConfig($this->customDir, $filename, '{"data":"custom"}');

        // call tested method
        $result = $this->configManager->copyConfigToRoot($filename);

        // assert result
        $this->assertFalse($result);
    }

    /**
     * Test check if internal configuration file is a custom file
     *
     * @return void
     */
    public function testIsCustomConfigWhenFileExists(): void
    {
        $filename = 'custom.json';
        ConfigTestHelper::createCustomConfig($this->customDir, $filename);

        // call tested method
        $result = $this->configManager->isCustomConfig($filename);

        // assert result
        $this->assertTrue($result);
    }

    /**
     * Test check if internal configuration file is a custom file when file does not exist
     *
     * @return void
     */
    public function testIsCustomConfigWhenFileDoesNotExist(): void
    {
        // call tested method
        $result = $this->configManager->isCustomConfig('custom.json');

        // assert result
        $this->assertFalse($result);
    }

    /**
     * Test delete internal configuration file success
     *
     * @return void
     */
    public function testDeleteConfigSuccess(): void
    {
        $filename = 'custom.json';
        $path = ConfigTestHelper::createCustomConfig($this->customDir, $filename, '{"value":1}');

        // expect log to be called
        $this->logManagerMock->expects($this->once())->method('log');

        // call tested method
        $result = $this->configManager->deleteConfig($filename);

        // assert result
        $this->assertTrue($result);
        $this->assertFileDoesNotExist($path);
    }

    /**
     * Test delete internal configuration file when file does not exist
     *
     * @return void
     */
    public function testDeleteConfigWhenFileDoesNotExist(): void
    {
        // expect log to be called
        $this->logManagerMock->expects($this->never())->method('log');

        // call tested method
        $result = $this->configManager->deleteConfig('custom.json');

        // assert result
        $this->assertFalse($result);
    }

    /**
     * Test update feature flag with success status
     *
     * @return void
     */
    public function testUpdateFeatureFlagSuccess(): void
    {
        $filename = 'feature-flags.json';
        ConfigTestHelper::createCustomConfig($this->customDir, $filename, json_encode(['metrics' => false], JSON_PRETTY_PRINT) ?: '{}');

        // expect log to be called
        $this->logManagerMock->expects($this->exactly(2))->method('log');

        // call tested method
        $this->configManager->updateFeatureFlag('metrics', true);

        // assert result
        $content = file_get_contents(ConfigTestHelper::getCustomConfigPath($this->customDir, $filename)) ?: '';
        $this->assertStringContainsString('"metrics": true', $content);
    }

    /**
     * Test update feature flag when feature flag does not exist
     *
     * @return void
     */
    public function testUpdateFeatureFlagWhenFeatureDoesNotExist(): void
    {
        $filename = 'feature-flags.json';
        ConfigTestHelper::createCustomConfig($this->customDir, $filename, json_encode(['other-feature' => true], JSON_PRETTY_PRINT) ?: '{}');

        // expect error handler to be called
        $this->errorManagerMock->expects($this->once())->method('handleError')->with(
            $this->stringContains('does not exist'),
            Response::HTTP_NOT_FOUND
        )->willThrowException(new HttpException(Response::HTTP_NOT_FOUND));
        $this->expectException(HttpException::class);

        // call tested method
        $this->configManager->updateFeatureFlag('metrics', true);
    }
}
