<?php

namespace App\Tests;

use DateTime;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class EntityTestHelper
 *
 * Helpers for creating entities in integration tests
 *
 * @package App\Tests
 */
class EntityTestHelper
{
    /**
     * Create and persist a user entity with optional field overrides
     *
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     * @param array<string, mixed> $overrides
     *
     * @return User Persisted user entity
     */
    public static function createUser(EntityManagerInterface $entityManager, array $overrides = []): User
    {
        $user = new User();
        $user->setUsername($overrides['username'] ?? ('test-user-' . uniqid('_', true)));
        $user->setPassword($overrides['password'] ?? 'password');
        $user->setRole($overrides['role'] ?? 'ADMIN');
        $user->setIpAddress($overrides['ip_address'] ?? '127.0.0.1');
        $user->setUserAgent($overrides['user_agent'] ?? 'PHPUnit');
        $user->setRegisterTime($overrides['register_time'] ?? new DateTime());
        $user->setLastLoginTime($overrides['last_login_time'] ?? new DateTime());
        $user->setToken($overrides['token'] ?? uniqid('token_', true));
        $user->setAllowApiAccess($overrides['allow_api_access'] ?? true);
        $user->setProfilePic($overrides['profile_pic'] ?? 'pic');

        // persist and flush test user to database
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
