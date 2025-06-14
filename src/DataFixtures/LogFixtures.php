<?php

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\Log;
use App\Manager\LogManager;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;

/**
 * Class LogFixtures
 *
 * Testing log data fixtures for fill database with test data
 *
 * @package App\DataFixtures
 */
class LogFixtures extends Fixture
{
    /**
     * Load log fixtures
     *
     * @param ObjectManager $manager The entity manager
     *
     * @return void
     */
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        // create 100 logs
        for ($i = 0; $i < 100; $i++) {
            $log = new Log();

            // set log properties
            $log->setName($faker->word)
                ->setMessage($faker->sentence)
                ->setTime($faker->dateTimeThisYear)
                ->setUserAgent($faker->userAgent)
                ->setIpAddress($faker->ipv4)
                ->setStatus('UNREADED')
                ->setLevel(LogManager::LEVEL_CRITICAL)
                ->setUserId(1);

            // persist log
            $manager->persist($log);
        }

        // flush data to database
        $manager->flush();
    }
}
