<?php

namespace App\Tests\Controller\Component;

use App\Tests\CustomTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Class ExampleComponentOneControllerTest
 *
 * Test cases for example component one
 *
 * @package App\Tests\Controller\Component
 */
class ExampleComponentOneControllerTest extends CustomTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // simulate user authentication
        $this->simulateLogin($this->client);
    }

    /**
     * Test render example component one page
     *
     * @return void
     */
    public function testRenderExampleComponentOnePage(): void
    {
        // render example component one page
        $this->client->request('GET', '/example/one');

        // assert response
        $this->assertSelectorTextContains('body', 'TEST ONE');
        $this->assertSelectorTextContains('body', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }
}
