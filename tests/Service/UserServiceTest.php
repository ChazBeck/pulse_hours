<?php

class UserServiceTest extends DatabaseTestCase {
    private UserService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = new UserService($this->pdo);
    }

    public function testCreateUserHashesPassword(): void {
        $result = $this->service->createUser([
            'email'      => 'new@example.com',
            'first_name' => 'New',
            'last_name'  => 'User',
            'password'   => 'plaintext',
            'role'       => 'User',
            'is_active'  => 1,
        ]);
        $this->assertTrue($result['success'], $result['message']);

        $row = $this->fetchOne('SELECT password_hash FROM users WHERE id = ?', [$result['user_id']]);
        $this->assertNotSame('plaintext', $row['password_hash']);
        $this->assertTrue(password_verify('plaintext', $row['password_hash']));
    }

    public function testCreateUserRejectsDuplicateEmail(): void {
        $result = $this->service->createUser([
            'email'      => 'admin@plusehours.com',
            'first_name' => 'Dup',
            'last_name'  => 'User',
            'password'   => 'pw',
            'role'       => 'User',
        ]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already exists', $result['message']);
    }

    public function testCreateUserRejectsInvalidRole(): void {
        $result = $this->service->createUser([
            'email'      => 'x@example.com',
            'first_name' => 'X',
            'last_name'  => 'Y',
            'password'   => 'pw',
            'role'       => 'Superhero',
        ]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid role', $result['message']);
    }

    public function testUpdateUserPreservesHashWhenPasswordEmpty(): void {
        $created = $this->service->createUser([
            'email'      => 'keep@example.com',
            'first_name' => 'Keep',
            'last_name'  => 'Hash',
            'password'   => 'original-pw',
            'role'       => 'User',
        ]);
        $originalHash = $this->fetchOne('SELECT password_hash FROM users WHERE id = ?', [$created['user_id']])['password_hash'];

        $result = $this->service->updateUser($created['user_id'], [
            'email'      => 'keep@example.com',
            'first_name' => 'Keep',
            'last_name'  => 'Hash',
            'role'       => 'Admin',
            'password'   => '',
        ]);

        $this->assertTrue($result['success']);
        $newHash = $this->fetchOne('SELECT password_hash, role FROM users WHERE id = ?', [$created['user_id']]);
        $this->assertSame($originalHash, $newHash['password_hash']);
        $this->assertSame('Admin', $newHash['role']);
    }

    public function testDeleteUserRefusesSelfDelete(): void {
        $result = $this->service->deleteUser(
            $this->fixtures['admin_user_id'],
            $this->fixtures['admin_user_id']
        );
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('own account', $result['message']);
    }
}
