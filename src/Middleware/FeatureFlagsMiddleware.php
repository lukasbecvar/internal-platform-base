<?php

namespace App\Middleware;

use App\Util\AppUtil;
use App\Manager\ErrorManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use App\Controller\Component\ExampleComponentOneController;
use App\Controller\Component\ExampleComponentTwoController;

/**
 * Class FeatureFlagsMiddleware
 *
 * Middleware for handling feature flags
 *
 * @package App\Middleware
 */
class FeatureFlagsMiddleware
{
    private AppUtil $appUtil;
    private ErrorManager $errorManager;

    public function __construct(AppUtil $appUtil, ErrorManager $errorManager)
    {
        $this->appUtil = $appUtil;
        $this->errorManager = $errorManager;
    }

    /**
     * Disable controller if feature flag is disabled
     *
     * @param ControllerEvent $event The controller event
     *
     * @return void
     */
    public function onKernelController(ControllerEvent $event): void
    {
        // get controller instance
        $controller = $event->getController();

        if (is_array($controller)) {
            $controllerObject = $controller[0];

            // disable monitoring if feature flag is disabled
            if ($controllerObject instanceof ExampleComponentOneController) {
                if ($this->appUtil->isFeatureFlagDisabled('example-component-one')) {
                    $this->errorManager->handleError(
                        message: 'example component one is disabled',
                        code: Response::HTTP_NOT_FOUND
                    );
                }
            }

            // disable metrics if feature flag is disabled
            if ($controllerObject instanceof ExampleComponentTwoController) {
                if ($this->appUtil->isFeatureFlagDisabled('example-component-two')) {
                    $this->errorManager->handleError(
                        message: 'example component two is disabled',
                        code: Response::HTTP_NOT_FOUND
                    );
                }
            }
        }
    }
}
