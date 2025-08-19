<?php

namespace App\Tests\Controller\Auth;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Class NonAuthRedirectTest
 *
 * Test for redirect non-authenticated users to login page for admin page routes
 *
 * @package App\Tests\Controller\Auth
 */
class NonAuthRedirectTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Auth required routes list
     *
     * @return array<array<string,string>>
     */
    private const ROUTES = [
        'api' => [
            ['method' => 'GET', 'url' => '/api/notifications/enabled'],
            ['method' => 'POST', 'url' => '/api/notifications/subscribe'],
            ['method' => 'GET', 'url' => '/api/notifications/public-key']
        ],
        'anti_log' => [
            ['method' => 'GET', 'url' => '/13378/antilog']
        ],
        'app_about' => [
            ['method' => 'GET', 'url' => '/about']
        ],
        'admin_dashboard' => [
            ['method' => 'GET', 'url' => '/dashboard']
        ],
        'user_manager' => [
            ['method' => 'GET', 'url' => '/manager/users'],
            ['method' => 'GET', 'url' => '/manager/users/ban'],
            ['method' => 'GET', 'url' => '/manager/users/delete'],
            ['method' => 'GET', 'url' => '/manager/users/register'],
            ['method' => 'POST', 'url' => '/manager/users/role/update'],
            ['method' => 'GET', 'url' => '/manager/users/token/regenerate']
        ],
        'config_manager' => [
            ['method' => 'GET', 'url' => '/settings']
        ],
        'account_settings' => [
            ['method' => 'GET', 'url' => '/account/settings'],
            ['method' => 'GET', 'url' => '/manager/users/profile'],
            ['method' => 'GET', 'url' => '/account/settings/change/picture'],
            ['method' => 'GET', 'url' => '/account/settings/change/username'],
            ['method' => 'GET', 'url' => '/account/settings/change/password']
        ],
        'logs_manager' => [
            ['method' => 'GET', 'url' => '/manager/logs'],
            ['method' => 'GET', 'url' => '/manager/logs/set/readed']
        ]
    ];

    /**
     * Admin routes list provider
     *
     * @return array<int,array<int,string>>
     */
    public static function provideAdminUrls(): array
    {
        $urls = [];
        foreach (self::ROUTES as $category => $routes) {
            foreach ($routes as $route) {
                $urls[] = [$route['method'], $route['url']];
            }
        }
        return $urls;
    }

    /**
     * Test requests to admin routes that require authentication
     *
     * @param string $method The HTTP method
     * @param string $url The admin route URL
     *
     * @return void
     */
    #[DataProvider('provideAdminUrls')]
    public function testNonAuthRedirect(string $method, string $url): void
    {
        $this->client->request($method, $url);

        // assert response
        $this->assertTrue($this->client->getResponse()->isRedirect('/login'));
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
    }
}
