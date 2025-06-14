<?php

namespace App\Tests\Controller\Component;

use App\Tests\CustomTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Class DashboardControllerTest
 *
 * Test cases for dashboard component
 *
 * @package App\Tests\Controller\Component
 */
class DashboardControllerTest extends CustomTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // simulate user authentication
        $this->simulateLogin($this->client);
    }

    /**
     * Test load dashboard page
     *
     * @return void
     */
    public function testLoadDashboardPage(): void
    {
        $this->client->request('GET', '/dashboard');

        // assert response
        $this->assertSelectorTextContains('title', 'Internal platform');
        $this->assertSelectorExists('button[id="menu-toggle"]');
        $this->assertSelectorExists('a[title="Logout user"]');
        $this->assertSelectorExists('a[href="/logout"]');
        $this->assertSelectorExists('aside[id="sidebar"]');
        $this->assertSelectorExists('img[alt="profile picture"]');
        $this->assertSelectorExists('h3[id="username"]');
        $this->assertSelectorExists('span[id="role"]');
        $this->assertSelectorExists('a[href="/dashboard"]');
        $this->assertSelectorExists('a[href="/manager/logs"]');
        $this->assertSelectorExists('a[href="/manager/users"]');
        $this->assertSelectorExists('a[href="/account/settings"]');
        $this->assertSelectorExists('main[id="main-content"]');
        $this->assertSelectorTextContains('body', 'Diagnostic Alerts');
        $this->assertSelectorTextContains('body', 'Database Statistics');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }
}
