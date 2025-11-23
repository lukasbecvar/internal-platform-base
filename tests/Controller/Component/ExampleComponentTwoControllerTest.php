<?php

namespace App\Tests\Controller\Component;

use App\Tests\CustomTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Class ExampleComponentTwoControllerTest
 *
 * Test cases for example component two
 *
 * @package App\Tests\Controller\Component
 */
class ExampleComponentTwoControllerTest extends CustomTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // simulate user authentication
        $this->simulateLogin($this->client);
    }

    /**
     * Test render example component two page
     *
     * @return void
     */
    public function testRenderExampleComponentTwoPage(): void
    {
        // render example component two page
        $this->client->request('GET', '/example/two');

        // assert response
        $this->assertSelectorTextContains('body', 'TEST TWO');
        $this->assertSelectorTextContains('body', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }
}
