<?php

namespace App\Tests\Controller\Component;

use App\Tests\CustomTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Class ConfigManagerControllerTest
 *
 * Test cases for config manager component
 *
 * @package App\Tests\Controller\Component
 */
class ConfigManagerControllerTest extends CustomTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // simulate user authentication
        $this->simulateLogin($this->client);
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
        $this->assertSelectorExists('button[id="menu-toggle"]');
        $this->assertSelectorExists('a[title="Logout user"]');
        $this->assertSelectorExists('a[href="/settings"]');
        $this->assertSelectorExists('a[href="/logout"]');
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
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }
}
