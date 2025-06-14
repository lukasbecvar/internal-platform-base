<?php

namespace App\Tests\Command\User;

use Exception;
use App\Entity\User;
use App\Manager\UserManager;
use PHPUnit\Framework\TestCase;
use App\Command\User\UserUpdateRoleCommand;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Class UserUpdateRoleCommandTest
 *
 * Test cases for execute user update role command
 *
 * @package App\Tests\Command
 */
class UserUpdateRoleCommandTest extends TestCase
{
    private CommandTester $commandTester;
    private UserUpdateRoleCommand $command;
    private UserManager & MockObject $userManager;

    protected function setUp(): void
    {
        // mock dependencies
        $this->userManager = $this->createMock(UserManager::class);

        // initialize the command
        $this->command = new UserUpdateRoleCommand($this->userManager);
        $this->commandTester = new CommandTester($this->command);
    }

    /**
     * Test execute user update role command with empty username
     *
     * @return void
     */
    public function testExecuteCommandWithEmptyUsername(): void
    {
        // execute command
        $exitCode = $this->commandTester->execute(['username' => '', 'role' => 'ADMIN']);

        // get command output
        $output = $this->commandTester->getDisplay();

        // assert result
        $this->assertStringContainsString('Username parameter is required', $output);
        $this->assertSame(Command::FAILURE, $exitCode);
    }

    /**
     * Test execute user update role command with empty role
     *
     * @return void
     */
    public function testExecuteCommandWithEmptyRole(): void
    {
        // execute command
        $exitCode = $this->commandTester->execute(['username' => 'testuser', 'role' => '']);

        // get command output
        $output = $this->commandTester->getDisplay();

        // assert result
        $this->assertStringContainsString('Role parameter is required', $output);
        $this->assertSame(Command::FAILURE, $exitCode);
    }

    /**
     * Test execute user update role command with role is not valid
     *
     * @return void
     */
    public function testExecuteCommandWithRoleNotValid(): void
    {
        // execute command
        $exitCode = $this->commandTester->execute(['username' => 'testuser', 'role' => 1]);

        // get command output
        $output = $this->commandTester->getDisplay();

        // assert result
        $this->assertStringContainsString('Invalid role type provided (must be string)  ', $output);
        $this->assertSame(Command::FAILURE, $exitCode);
    }

    /**
     * Test execute user update role command with invalid role
     *
     * @return void
     */
    public function testExecuteCommandWithInvalidRole(): void
    {
        // execute command
        $exitCode = $this->commandTester->execute(['username' => 'testuser', 'role' => 123]);

        // get command output
        $output = $this->commandTester->getDisplay();

        // assert result
        $this->assertStringContainsString('Invalid role type provided (must be string)  ', $output);
        $this->assertSame(Command::FAILURE, $exitCode);
    }

    /**
     * Test execute user update role command with non existing username
     *
     * @return void
     */
    public function testExecuteCommandWithNonExistingUsername(): void
    {
        // mock user manager
        $this->userManager->method('getUserByUsername')->willReturn(null);

        // execute command
        $exitCode = $this->commandTester->execute(['username' => 'testuser', 'role' => 'ADMIN']);

        // get command output
        $output = $this->commandTester->getDisplay();

        // assert result
        $this->assertStringContainsString('Error username: testuser does not exist', $output);
        $this->assertSame(Command::FAILURE, $exitCode);
    }

    /**
     * Test execute user update role command with same role is already assigned
     *
     * @return void
     */
    public function testExecuteCommandWithRoleAlreadyAssigned(): void
    {
        // create user mock
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        // mock user manager
        $this->userManager->method('getUserByUsername')->willReturn($user);
        $this->userManager->method('getUserRoleById')->willReturn('ADMIN');

        // execute command
        $exitCode = $this->commandTester->execute(['username' => 'testuser', 'role' => 'ADMIN']);

        // get command output
        $output = $this->commandTester->getDisplay();

        // assert result
        $this->assertStringContainsString('Error role: ADMIN is already assigned to user: testuser', $output);
        $this->assertSame(Command::FAILURE, $exitCode);
    }

    /**
     * Test execute user update role command with exception during role update
     *
     * @return void
     */
    public function testExecuteCommandWithExceptionDuringRoleUpdate(): void
    {
        // mock user
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        // mock user manager
        $this->userManager->method('getUserByUsername')->willReturn($user);
        $this->userManager->method('getUserRoleById')->willReturn('USER');
        $this->userManager->method('updateUserRole')->will($this->throwException(
            new Exception('Some error')
        ));

        // execute command
        $exitCode = $this->commandTester->execute(['username' => 'testuser', 'role' => 'ADMIN']);

        // get command output
        $output = $this->commandTester->getDisplay();

        // assert result
        $this->assertStringContainsString('Process error: Some error', $output);
        $this->assertSame(Command::FAILURE, $exitCode);
    }

    /**
     * Test execute user update role command with successful role update
     *
     * @return void
     */
    public function testExecuteCommandWithSuccessfulRoleUpdate(): void
    {
        // mock user
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        // mock user manager
        $this->userManager->method('getUserByUsername')->willReturn($user);
        $this->userManager->method('getUserRoleById')->willReturn('USER');

        // execute command
        $exitCode = $this->commandTester->execute(['username' => 'testuser', 'role' => 'ADMIN']);

        // get command output
        $output = $this->commandTester->getDisplay();

        // assert result
        $this->assertStringContainsString('Role updated successfully', $output);
        $this->assertSame(Command::SUCCESS, $exitCode);
    }
}
