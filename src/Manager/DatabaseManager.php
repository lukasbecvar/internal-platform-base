<?php

namespace App\Manager;

use Exception;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class DatabaseManager
 *
 * Manager for database operations
 *
 * @package App\Manager
 */
class DatabaseManager
{
    private LogManager $logManager;
    private Connection $connection;
    private ErrorManager $errorManager;
    private EntityManagerInterface $entityManager;

    public function __construct(
        LogManager $logManager,
        Connection $connection,
        ErrorManager $errorManager,
        EntityManagerInterface $entityManager
    ) {
        $this->logManager = $logManager;
        $this->connection = $connection;
        $this->errorManager = $errorManager;
        $this->entityManager = $entityManager;
    }

    /**
     * Get database connection
     *
     * @return Connection|null The database connection
     */
    public function getDatabaseConnection(): ?Connection
    {
        return $this->connection;
    }

    /**
     * Check if database is down
     *
     * @return bool True if database is down, false otherwise
     */
    public function isDatabaseDown(): bool
    {
        try {
            $this->connection->executeQuery('SELECT 1');
        } catch (Exception) {
            return true;
        }

        return false;
    }

    /**
     * Truncate table in specific database
     *
     * @param string $dbName The name of the database
     * @param string $tableName The name of the table
     *
     * @return void
     */
    public function tableTruncate(string $dbName, string $tableName): void
    {
        $databaseIdentifier = $this->quoteIdentifier($dbName, 'database');
        $tableIdentifier = $this->quoteIdentifier($tableName, 'table');
        $sql = sprintf('TRUNCATE TABLE %s.%s', $databaseIdentifier, $tableIdentifier);

        try {
            // execute truncate table query
            $this->connection->executeStatement($sql);

            // log truncate table event
            $this->logManager->log(
                name: 'database-manager',
                message: 'truncated table: ' . $tableName . ' in database: ' . $dbName,
                level: LogManager::LEVEL_CRITICAL
            );
        } catch (Exception $e) {
            $this->errorManager->handleError(
                message: 'error truncating table: ' . $e->getMessage() . ' in database: ' . $dbName,
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get entity table name
     *
     * @param string $entityClass The entity class
     *
     * @return string The entity table name
     */
    public function getEntityTableName(string $entityClass): string
    {
        if (!class_exists($entityClass)) {
            $this->errorManager->handleError(
                message: 'entity class not found: ' . $entityClass,
                code: Response::HTTP_NOT_FOUND
            );
        }

        $metadata = $this->entityManager->getClassMetadata($entityClass);
        return $metadata->getTableName();
    }

    /**
     * Validate and quote database identifiers to avoid SQL injection
     *
     * @param string $name Identifier value
     * @param string $type Identifier type for error context
     *
     * @return string Quoted identifier
     */
    public function quoteIdentifier(string $name, string $type): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            $this->errorManager->handleError(
                message: sprintf('invalid %s name: %s', $type, $name),
                code: Response::HTTP_BAD_REQUEST
            );
        }

        $platform = $this->connection->getDatabasePlatform();
        return $platform->quoteSingleIdentifier($name);
    }
}
