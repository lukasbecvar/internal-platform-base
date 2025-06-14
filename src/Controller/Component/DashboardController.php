<?php

namespace App\Controller\Component;

use Exception;
use App\Manager\BanManager;
use App\Manager\LogManager;
use App\Manager\UserManager;
use App\Manager\AuthManager;
use App\Manager\ErrorManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Class DashboardController
 *
 * Controller for dashboard component
 *
 * @package App\Controller
 */
class DashboardController extends AbstractController
{
    private LogManager $logManager;
    private BanManager $banManager;
    private UserManager $userManager;
    private AuthManager $authManager;
    private ErrorManager $errorManager;

    public function __construct(
        LogManager $logManager,
        BanManager $banManager,
        UserManager $userManager,
        AuthManager $authManager,
        ErrorManager $errorManager
    ) {
        $this->logManager = $logManager;
        $this->banManager = $banManager;
        $this->userManager = $userManager;
        $this->authManager = $authManager;
        $this->errorManager = $errorManager;
    }

    /**
     * Render dashboard page
     *
     * @return Response The dashboard page view
     */
    #[Route('/dashboard', methods:['GET'], name: 'app_dashboard')]
    public function dashboard(): Response
    {
        try {
            // get data for warning card
            $antiLogStatus = $this->logManager->isAntiLogEnabled();

            // get logs counters
            $authLogsCount = $this->logManager->getAuthLogsCount();
            $allLogsCount = $this->logManager->getLogsCountWhereStatus();
            $readedLogsCount = $this->logManager->getLogsCountWhereStatus('READED');
            $unreadedLogsCount = $this->logManager->getLogsCountWhereStatus('UNREADED');

            // get user stats counters
            $onlineUsersCount = count($this->authManager->getOnlineUsersList());
            $bannedUsersCount = $this->banManager->getBannedCount();
            $usersCount = $this->userManager->getUsersCount();
        } catch (Exception $e) {
            $this->errorManager->handleError(
                message: 'error to get dashboard data: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // return dashboard page view
        return $this->render('component/dashboard/dashboard.twig', [
            // warning card data
            'antiLogStatus' => $antiLogStatus,

            // logs counters
            'allLogsCount' => $allLogsCount,
            'authLogsCount' => $authLogsCount,
            'readedLogsCount' => $readedLogsCount,
            'unreadedLogsCount' => $unreadedLogsCount,

            // users stats counters
            'usersCount' => $usersCount,
            'onlineUsersCount' => $onlineUsersCount,
            'bannedUsersCount' => $bannedUsersCount
        ]);
    }
}
