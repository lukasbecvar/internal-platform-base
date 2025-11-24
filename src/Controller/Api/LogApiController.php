<?php

namespace App\Controller\Api;

use Exception;
use App\Util\XmlUtil;
use App\Manager\LogManager;
use App\Manager\ErrorManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Class LogApiController
 *
 * Controller for external log API
 *
 * @package App\Controller\Api
 */
class LogApiController extends AbstractController
{
    private XmlUtil $xmlUtil;
    private LogManager $logManager;
    private ErrorManager $errorManager;

    public function __construct(
        XmlUtil $xmlUtil,
        LogManager $logManager,
        ErrorManager $errorManager
    ) {
        $this->xmlUtil = $xmlUtil;
        $this->logManager = $logManager;
        $this->errorManager = $errorManager;
    }

    /**
     * Handle log from external service
     *
     * This endpoint is used in external services.
     * Supports traditional query parameters or XML payloads
     * with the following structure:
     *
     * XML payload:
     *  <log>
     *    <name>string</name>
     *    <message>string</message>
     *    <level>int</level>
     *  </log>
     *
     * Request query parameters:
     *  - name: log name (string)
     *  - message: log message (string)
     *  - level: log level (int)
     *
     * @param Request $request The request object
     *
     * @return JsonResponse The JSON response with status
     */
    #[Route('/api/external/log', methods:['POST'], name: 'app_api_external_log')]
    public function externalLog(Request $request): JsonResponse
    {
        // get log data from request
        $message = (string) $request->query->get('message');
        $name = (string) $request->query->get('name');
        $level = (int) $request->query->get('level');

        // parse XML payload if provided
        if ($this->xmlUtil->isXmlRequest($request)) {
            try {
                $xmlPayload = $this->xmlUtil->parseXmlPayload($request->getContent());
            } catch (Exception $e) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Invalid XML payload: ' . $e->getMessage()
                ], JsonResponse::HTTP_BAD_REQUEST);
            }

            if (isset($xmlPayload->name) && (string) $xmlPayload->name !== '') {
                $name = (string) $xmlPayload->name;
            }
            if (isset($xmlPayload->message) && (string) $xmlPayload->message !== '') {
                $message = (string) $xmlPayload->message;
            }
            if (isset($xmlPayload->level) && (string) $xmlPayload->level !== '') {
                $level = (int) $xmlPayload->level;
            }
        }

        // check parameters are set
        if (empty($name) || empty($message) || empty($level)) {
            return $this->json([
                'status' => 'error',
                'message' => 'Parameters name, message and level are required'
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            // save log to database
            $this->logManager->log($name, $message, $level);

            // return success message
            return $this->json([
                'status' => 'success',
                'message' => 'Log message has been logged'
            ], JsonResponse::HTTP_OK);
        } catch (Exception $e) {
            // log error to exception log
            $this->errorManager->logError(
                message: 'error to log external message: ' . $e->getMessage(),
                code: JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );

            // return error response
            return $this->json([
                'status' => 'error',
                'message' => 'Error to log message'
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
