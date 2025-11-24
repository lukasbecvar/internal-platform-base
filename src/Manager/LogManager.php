<?php

namespace App\Manager;

use DateTime;
use Exception;
use App\Entity\Log;
use App\Entity\User;
use App\Util\AppUtil;
use App\Util\CookieUtil;
use App\Util\SessionUtil;
use App\Entity\ApiAccessLog;
use App\Util\VisitorInfoUtil;
use App\Repository\LogRepository;
use App\Entity\SentNotificationLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class LogManager
 *
 * Manager for log management
 *
 * @package App\Manager
 */
class LogManager
{
    // log levels definitions
    public const LEVEL_CRITICAL = 1;
    public const LEVEL_WARNING = 2;
    public const LEVEL_NOTICE = 3;
    public const LEVEL_INFO = 4;

    private AppUtil $appUtil;
    private CookieUtil $cookieUtil;
    private SessionUtil $sessionUtil;
    private ErrorManager $errorManager;
    private LogRepository $logRepository;
    private VisitorInfoUtil $visitorInfoUtil;
    private EntityManagerInterface $entityManager;

    public function __construct(
        AppUtil $appUtil,
        CookieUtil $cookieUtil,
        SessionUtil $sessionUtil,
        ErrorManager $errorManager,
        LogRepository $logRepository,
        VisitorInfoUtil $visitorInfoUtil,
        EntityManagerInterface $entityManager
    ) {
        $this->appUtil = $appUtil;
        $this->cookieUtil = $cookieUtil;
        $this->sessionUtil = $sessionUtil;
        $this->errorManager = $errorManager;
        $this->logRepository = $logRepository;
        $this->entityManager = $entityManager;
        $this->visitorInfoUtil = $visitorInfoUtil;
    }

    /**
     * Log message to the database
     *
     * @param string $name The log name
     * @param string $message The log message
     * @param int $level The log level
     *
     * @return void
     */
    public function log(string $name, string $message, int $level = 1): void
    {
        // check if log can be saved
        if (str_contains($message, 'Connection refused')) {
            return;
        }

        // check if anti-log is enabled
        if ($this->isAntiLogEnabled()) {
            return;
        }

        // check if database logging is enabled
        if (!$this->appUtil->isDatabaseLoggingEnabled()) {
            return;
        }

        // check required log level
        if ($level > (int) $this->appUtil->getEnvValue('LOG_LEVEL')) {
            return;
        }

        // get user data
        $ipAddress = $this->visitorInfoUtil->getIP();
        $userAgent = (string) $this->visitorInfoUtil->getUserAgent();

        // check if visitor ip address is unknown
        if ($ipAddress == null) {
            $ipAddress = 'Unknown';
        }

        // create log entity
        $log = new Log();
        $log->setName($name)
            ->setMessage($message)
            ->setStatus('UNREADED')
            ->setUserAgent($userAgent)
            ->setIpAddress($ipAddress)
            ->setTime(new DateTime())
            ->setLevel($level);

        // set user (if user identifier is set)
        $userId = $this->sessionUtil->getSessionValue('user-identifier', 0);
        if (is_numeric($userId)) {
            $userId = (int) $userId;
            if ($userId > 0) {
                /** @var User|null $user */
                $user = $this->entityManager->find(User::class, $userId);
                if ($user !== null) {
                    $log->setUser($user);
                }
            }
        }

        try {
            // persist and flush log to database
            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (Exception $e) {
            $this->errorManager->handleError(
                message: 'log-error: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Set anti-log cookie
     *
     * @return void
     */
    public function setAntiLog(): void
    {
        // log anti-log enable event
        $this->log('anti-log', 'anti-log enabled', self::LEVEL_WARNING);

        // set anti-log cookie
        $this->cookieUtil->set(
            name: 'anti-log',
            value: $this->appUtil->getEnvValue('ANTI_LOG_TOKEN'),
            expiration: time() + (60 * 60 * 24 * 7 * 365)
        );
    }

    /**
     * Unset anti-log cookie
     *
     * @return void
     */
    public function unSetAntiLog(): void
    {
        // log anti-log disable event
        $this->log('anti-log', 'anti-log disabled', self::LEVEL_WARNING);

        // unset anti-log cookie
        $this->cookieUtil->unset('anti-log');
    }

    /**
     * Check if anti-log is enabled
     *
     * @return bool True if anti-log is enabled, false otherwise
     */
    public function isAntiLogEnabled(): bool
    {
        // check if anti-log is set
        if (!$this->cookieUtil->isCookieSet('anti-log')) {
            return false;
        }

        // get anti-log token from cookie
        $cookieToken = $this->cookieUtil->get('anti-log');

        // check if anti-log token is valid
        if ($cookieToken == $this->appUtil->getEnvValue('ANTI_LOG_TOKEN')) {
            return true;
        }

        return false;
    }

    /**
     * Get count of logs based on their status
     *
     * @param string $status The status of the logs to count (default is 'all')
     * @param int $userId The user id for get all count logs
     *
     * @return int The count of logs
     */
    public function getLogsCountWhereStatus(string $status = 'all', int $userId = 0): int
    {
        // get logs count
        if ($status == 'all') {
            if ($userId != 0) {
                $user = $userId > 0 ? $this->entityManager->getReference(User::class, (int) $userId) : null;
                $count = $user !== null ? $this->logRepository->count(['user' => $user]) : 0;
            } else {
                $count = $this->logRepository->count();
            }
        } else {
            $count = $this->logRepository->count(['status' => $status]);
        }

        return $count;
    }

    /**
     * Get count of auth logs
     *
     * @return int The count of auth logs
     */
    public function getAuthLogsCount(): int
    {
        $count = $this->logRepository->count(['name' => 'authenticator', 'status' => 'UNREADED']);

        return $count;
    }

    /**
     * Fetch logs based on their status
     *
     * @param string $status The status of the logs to retrieve (default is 'all')
     * @param int $userId The user id for get all logs
     * @param int $page The logs list page number
     *
     * @return array<mixed>|null An array of logs if found, or null if no logs are found
     */
    public function getLogsWhereStatus(string $status = 'all', int $userId = 0, int $page = 1): ?array
    {
        // get page limitter
        $perPage = (int) $this->appUtil->getEnvValue('LIMIT_CONTENT_PER_PAGE');

        // calculate offset
        $offset = ($page - 1) * $perPage;

        // get logs list
        if ($status == 'all') {
            if ($userId != 0) {
                $user = $userId > 0 ? $this->entityManager->getReference(User::class, (int) $userId) : null;
                $logs = $user !== null ? $this->logRepository->findBy(['user' => $user], null, $perPage, $offset) : [];
            } else {
                $logs = $this->logRepository->findBy([], null, $perPage, $offset);
            }
        } else {
            $logs = $this->logRepository->findBy(['status' => $status], ['id' => 'DESC'], $perPage, $offset);
        }

        // log logs viewed event
        $this->log('log-manager', strtolower($status) . ' logs viewed', self::LEVEL_INFO);

        return $logs;
    }

    /**
     * Update status of a log by ID
     *
     * @param int $id The ID of the log entry to update
     * @param string $newStatus The new status to set for the log entry
     *
     * @return void
     */
    public function updateLogStatusById(int $id, string $newStatus): void
    {
        /** @var \App\Entity\Log $log */
        $log = $this->logRepository->find($id);

        // check if log found in database
        if (!$log) {
            $this->errorManager->handleError(
                message: 'log status update error: log id: ' . $id . ' not found',
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // update status
        try {
            $log->setStatus($newStatus);

            // flush data to database
            $this->entityManager->flush();
        } catch (Exception $e) {
            $this->errorManager->handleError(
                message: 'error to update log status: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // log action
        $this->log(
            name: 'log-manager',
            message: 'Log: ' . $log->getId() . ' status was updated to: ' . $newStatus,
            level: self::LEVEL_INFO
        );
    }

    /**
     * Set all logs with status 'UNREADED' to 'READED'
     *
     * @return void
     */
    public function setAllLogsToReaded(): void
    {
        /** @var array<Log> $logs */
        $logs = $this->logRepository->findBy(['status' => 'UNREADED']);

        if (is_iterable($logs)) {
            // set all logs to readed status
            foreach ($logs as $log) {
                $log->setStatus('READED');
            }
        }

        // flush changes to the database
        try {
            $this->entityManager->flush();
        } catch (Exception $e) {
            $this->errorManager->handleError(
                message: 'error to set all logs status to "READED": ' . $e,
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Log api access
     *
     * @param string $url The url of the api access
     * @param string $method The method of the api access
     * @param int $userId The id of the user who made the api access
     *
     * @return void
     */
    public function logApiAccess(string $url, string $method, int $userId): void
    {
        // get user reference
        $user = $userId > 0 ? $this->entityManager->getReference(User::class, (int) $userId) : null;
        if ($user === null) {
            $this->errorManager->logError(
                message: 'log api access error: invalid user id',
                code: Response::HTTP_BAD_REQUEST
            );
            return;
        }

        // create log entity
        $log = new ApiAccessLog();
        $log->setUrl($url)
            ->setMethod($method)
            ->setTime(new DateTime())
            ->setUser($user);

        try {
            // persist and flush log to database
            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (Exception $e) {
            $this->errorManager->logError(
                message: 'log api access error: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Log sent notification
     *
     * @param string $title The title of the notification
     * @param string $message The body of the notification
     * @param int $receiverId The receiver id of the notification
     *
     * @return void
     */
    public function logSentNotification(string $title, string $message, int $receiverId): void
    {
        // get receiver reference
        $receiver = $receiverId > 0 ? $this->entityManager->getReference(User::class, (int) $receiverId) : null;
        if ($receiver === null) {
            $this->errorManager->logError(
                message: 'log sent notification error: invalid receiver id',
                code: Response::HTTP_BAD_REQUEST
            );
            return;
        }

        // create sent notification log entity
        $log = new SentNotificationLog();
        $log->setTitle($title)
            ->setMessage($message)
            ->setSentTime(new DateTime())
            ->setReceiver($receiver);

        try {
            // persist and flush log to database
            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (Exception $e) {
            $this->errorManager->logError(
                message: 'log sent notification error: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
