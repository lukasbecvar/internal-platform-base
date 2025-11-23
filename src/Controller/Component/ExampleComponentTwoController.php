<?php

namespace App\Controller\Component;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Class ExampleComponentTwoController
 *
 * Controller for rendering example component page
 *
 * @package App\Controller\Component
 */
class ExampleComponentTwoController extends AbstractController
{
    /**
     * Render example component two page
     *
     * @return Response Example component two page view
     */
    #[Route('/example/two', methods: ['GET'], name: 'example_component_two')]
    public function about(): Response
    {
        return $this->render('component/example-two/example-two.twig');
    }
}
