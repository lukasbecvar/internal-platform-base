<?php

namespace App\Command\User;

use Exception;
use App\Manager\UserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class UserUpdateRoleCommand
 *
 * Command to update user role
 *
 * @package App\Command\User
 */
#[AsCommand(name: 'app:user:update:role', description: 'Update user role')]
class UserUpdateRoleCommand extends Command
{
    private UserManager $userManager;

    public function __construct(UserManager $userManager)
    {
        $this->userManager = $userManager;
        parent::__construct();
    }

    /**
     * Configure command arguments
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED, 'Username of user to update');
        $this->addArgument('role', InputArgument::REQUIRED, 'New role');
    }

    /**
     * Execute command to update user role
     *
     * @param InputInterface $input The input interface
     * @param OutputInterface $output The output interface
     *
     * @return int The command exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // set server headers for cli console
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'console';

        // get command arguments
        $username = $input->getArgument('username');
        $newRole = $input->getArgument('role');

        // validate arguments
        if (empty($username)) {
            $io->error('Username parameter is required');
            return Command::FAILURE;
        }
        if (empty($newRole)) {
            $io->error('Role parameter is required');
            return Command::FAILURE;
        }
        if (!is_string($username)) {
            $io->error('Invalid username type provided (must be string)');
            return Command::FAILURE;
        }
        if (!is_string($newRole)) {
            $io->error('Invalid role type provided (must be string)');
            return Command::FAILURE;
        }

        // get user object by username
        $user = $this->userManager->getUserByUsername($username);

        // check is username used
        if ($user == null) {
            $io->error('Error username: ' . $username . ' does not exist');
            return Command::FAILURE;
        }

        // check is id valid
        if ($user->getId() == null) {
            $io->error('Error user id not found');
            return Command::FAILURE;
        }

        // get current role
        $currentRole = $this->userManager->getUserRoleById($user->getId());

        // convert role to uppercase
        $newRole = strtoupper($newRole);

        // check if role is already assigned to user
        if ($currentRole == $newRole) {
            $io->error('Error role: ' . $newRole . ' is already assigned to user: ' . $username);
            return Command::FAILURE;
        }

        // update role
        try {
            $this->userManager->updateUserRole($user->getId(), $newRole);

            // success message
            $io->success('Role updated successfully');
            return Command::SUCCESS;
        } catch (Exception $e) {
            $io->error('Process error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
