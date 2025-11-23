<?php

namespace App\Controller\Component;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Class ExampleComponentOneController
 *
 * Controller for rendering example component page
 *
 * @package App\Controller\Component
 */
class ExampleComponentOneController extends AbstractController
{
    /**
     * Render example component one page
     *
     * @return Response Example component one page view
     */
    #[Route('/example/one', methods: ['GET'], name: 'example_component_one')]
    public function about(): Response
    {
        return $this->render('component/example-one/example-one.twig');
    }
}
