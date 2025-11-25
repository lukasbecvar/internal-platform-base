<?php

namespace App\Tests\Controller\Component;

use App\Tests\CustomTestCase;
use App\Tests\ConfigTestHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use App\Controller\Component\ConfigManagerController;

/**
 * Class ConfigManagerControllerTest
 *
 * Test cases for config manager component
 *
 * @package App\Tests\Controller\Component
 */
#[CoversClass(ConfigManagerController::class)]
class ConfigManagerControllerTest extends CustomTestCase
{
    private KernelBrowser $client;
    private string $customConfigDir;
    private string $projectDir = '';

    /** @var array<string, string|null> */
    private array $defaultConfigBackups = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // simulate user authentication
        $this->simulateLogin($this->client);

        $container = static::getContainer();
        $projectDir = $container->getParameter('kernel.project_dir');
        if (!is_string($projectDir)) {
            self::fail('kernel.project_dir parameter must be a string');
        }
        $this->projectDir = $projectDir;
        $this->customConfigDir = sys_get_temp_dir() . '/custom_config_' . uniqid();
        if (!is_dir($this->customConfigDir)) {
            mkdir($this->customConfigDir, 0777, true);
        }
        $_ENV['APP_CUSTOM_CONFIG_DIR'] = $this->customConfigDir;
        putenv('APP_CUSTOM_CONFIG_DIR=' . $this->customConfigDir);

        ConfigTestHelper::backupAndReplaceDefaultConfig($this->projectDir, 'blocked-usernames.json', '{"blocked":["admin"]}', $this->defaultConfigBackups);
        ConfigTestHelper::createCustomConfig($this->customConfigDir, 'terminal-blocked-commands.json', '{"blocked":["rm"]}');
        ConfigTestHelper::createCustomConfig($this->customConfigDir, 'feature-flags.json', '{"test-feature": false}');
    }

    protected function tearDown(): void
    {
        ConfigTestHelper::removePath($this->customConfigDir);
        ConfigTestHelper::restoreDefaultConfigs($this->defaultConfigBackups);
        unset($_ENV['APP_CUSTOM_CONFIG_DIR']);
        putenv('APP_CUSTOM_CONFIG_DIR');

        parent::tearDown();
    }

    /**
     * Test load settings selector page
     *
     * @return void
     */
    public function testLoadSettingsSelectorPage(): void
    {
        $this->client->request('GET', '/settings');

        // assert response
        $this->assertSelectorTextContains('title', 'Internal platform');
        $this->assertAnySelectorTextContains('p', 'Select settings category');
        $this->assertSelectorExists('button[id="menu-toggle"]');
        $this->assertSelectorExists('form[action="/logout"] button[title="Logout user"]');
        $this->assertSelectorExists('a[href="/settings"]');
        $this->assertSelectorExists('aside[id="sidebar"]');
        $this->assertSelectorExists('img[alt="profile picture"]');
        $this->assertSelectorExists('h3[id="username"]');
        $this->assertSelectorExists('span[id="role"]');
        $this->assertSelectorExists('a[href="/dashboard"]');
        $this->assertSelectorExists('a[href="/manager/logs"]');
        $this->assertSelectorExists('a[href="/manager/users"]');
        $this->assertSelectorExists('main[id="main-content"]');
        $this->assertSelectorTextContains('body', 'Settings');
        $this->assertSelectorTextContains('body', 'Manage your account preferences and security');
        $this->assertSelectorExists('a[href="/account/settings"]');
        $this->assertSelectorTextContains('body', 'Manage internal configuration files');
        $this->assertSelectorExists('a[href="/settings/internal"]');
        $this->assertSelectorTextContains('body', 'Manage feature flags');
        $this->assertSelectorExists('a[href="/settings/feature-flags"]');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    /**
     * Test load internal configurations list page
     *
     * @return void
     */
    public function testLoadInternalConfigIndexPage(): void
    {
        $this->client->request('GET', '/settings/internal');

        // assert response
        $this->assertSelectorTextContains('title', 'Internal platform');
        $this->assertSelectorExists('button[id="menu-toggle"]');
        $this->assertSelectorTextContains('body', 'internal Configuration');
        $this->assertAnySelectorTextContains('p', 'Manage internal configuration files');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    /**
     * Test load internal configuration show page
     *
     * @return void
     */
    public function testLoadInternalConfigShowPage(): void
    {
        $this->client->request('GET', '/settings/internal/show?filename=blocked-usernames.json');

        // assert response
        $this->assertSelectorTextContains('title', 'Internal platform');
        $this->assertSelectorExists('button[id="menu-toggle"]');
        $this->assertSelectorTextContains('body', 'View Configuration');
        $this->assertSelectorTextContains('body', 'Config: blocked-usernames.json');
        $this->assertSelectorExists('form[action="/settings/internal/create"]');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    /**
     * Test create custom internal configuration file when filename is not set
     *
     * @return void
     */
    public function testCreateCustomInternalConfigFileWhenFilenameIsNotSet(): void
    {
        $this->client->request('POST', '/settings/internal/create', [
            'csrf_token' => $this->getCsrfToken($this->client)
        ]);

        // assert response
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    /**
     * Test create custom internal configuration file
     *
     * @return void
     */
    public function testCreateCustomInternalConfigFile(): void
    {
        $this->client->request('POST', '/settings/internal/create', [
            'csrf_token' => $this->getCsrfToken($this->client),
            'filename' => 'blocked-usernames.json'
        ]);

        // assert response
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
    }

    /**
     * Test delete internal configuration file when filename is not set
     *
     * @return void
     */
    public function testDeleteInternalConfigFileWhenFilenameIsNotSet(): void
    {
        $this->client->request('POST', '/settings/internal/delete', [
            'csrf_token' => $this->getCsrfToken($this->client)
        ]);

        // assert response
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    /**
     * Test delete internal configuration file
     *
     * @return void
     */
    public function testDeleteInternalConfigFile(): void
    {
        $this->client->request('POST', '/settings/internal/delete', [
            'csrf_token' => $this->getCsrfToken($this->client),
            'filename' => 'terminal-blocked-commands.json'
        ]);

        // assert response
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
    }

    /**
     * Test load feature flags list page
     *
     * @return void
     */
    public function testLoadFeatureFlagsListPage(): void
    {
        $this->client->request('GET', '/settings/feature-flags');

        // assert response
        $this->assertSelectorTextContains('title', 'Internal platform');
        $this->assertAnySelectorTextContains('p', 'Manage feature flags');
        $this->assertSelectorExists('button[id="menu-toggle"]');
        $this->assertSelectorTextContains('body', 'Feature Flags');
        $this->assertSelectorTextContains('body', 'Manage feature flags');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    /**
     * Test update feature flag value
     *
     * @return void
     */
    public function testUpdateFeatureFlagValue(): void
    {
        $this->client->request('POST', '/settings/feature-flags/update', [
            'csrf_token' => $this->getCsrfToken($this->client),
            'feature' => 'test-feature',
            'value' => 'enable'
        ]);

        // assert response
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
    }
}
