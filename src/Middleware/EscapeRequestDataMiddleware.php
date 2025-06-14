<?php

namespace App\Middleware;

use App\Util\SecurityUtil;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Class EscapeRequestDataMiddleware
 *
 * Middleware for escape request data (for security)
 *
 * @package App\Service\Middleware
 */
class EscapeRequestDataMiddleware
{
    private SecurityUtil $securityUtil;

    public function __construct(SecurityUtil $securityUtil)
    {
        $this->securityUtil = $securityUtil;
    }

    /**
     * Handle request data escaping
     *
     * @param RequestEvent $event The request event
     *
     * @return void
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // get form data
        $requestData = $request->query->all() + $request->request->all();

        // escape all inputs
        array_walk_recursive($requestData, function (&$value) {
            $value = $this->securityUtil->escapeString($value);
        });

        // replace request data with escaped data
        $request->query->replace($requestData);
        $request->request->replace($requestData);
    }
}
