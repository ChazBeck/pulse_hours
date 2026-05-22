<?php
/**
 * User Service
 *
 * Business logic for user CRUD: validation, email uniqueness,
 * password hashing, and self-deletion protection.
 */

require_once __DIR__ . '/../Repository/autoload.php';
require_once __DIR__ . '/../../config/constants.php';

class UserService {
    private const NAME_MAX_LENGTH = 100;
    private const EMAIL_MAX_LENGTH = 255;

    private $pdo;
    private $userRepo;

    public function __construct($pdo = null) {
        $this->pdo = $pdo ?? get_db_connection();
        $this->userRepo = new UserRepository($this->pdo);
    }

    // ------------------------------------------------------------------
    // Read
    // ------------------------------------------------------------------

    public function getAll() {
        return $this->userRepo->getAll();
    }

    public function getActive() {
        return $this->userRepo->getActive();
    }

    public function getUserById($id) {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }
        return $this->userRepo->getById($id) ?: null;
    }

    // ------------------------------------------------------------------
    // Write
    // ------------------------------------------------------------------

    public function createUser(array $data) {
        $error = $this->validateProfile($data);
        if ($error) {
            return $this->fail($error);
        }

        $password = (string) ($data['password'] ?? '');
        if ($password === '') {
            return $this->fail('Password is required.');
        }

        $email = trim($data['email']);
        if ($this->userRepo->emailExists($email)) {
            return $this->fail('Email already exists. Please use a different email.');
        }

        try {
            $id = $this->userRepo->create([
                'email'      => $email,
                'password'   => $password,
                'first_name' => trim($data['first_name']),
                'last_name'  => trim($data['last_name']),
                'role'       => $data['role'],
                'is_active'  => !empty($data['is_active']) ? 1 : 0,
            ]);

            if (!$id) {
                return $this->fail('Failed to create user.');
            }
            return [
                'success' => true,
                'message' => 'User added successfully!',
                'user_id' => $id,
            ];
        } catch (Throwable $e) {
            return $this->logAndFail('create user', $e, 'Error adding user.');
        }
    }

    public function updateUser($id, array $data) {
        $id = (int) $id;
        if ($id <= 0) {
            return $this->fail('Invalid user ID.');
        }
        if (!$this->userRepo->getById($id)) {
            return $this->fail('User not found.');
        }

        $error = $this->validateProfile($data);
        if ($error) {
            return $this->fail($error);
        }

        $email = trim($data['email']);
        if ($this->userRepo->emailExists($email, $id)) {
            return $this->fail('Email already exists. Please use a different email.');
        }

        try {
            $this->userRepo->updateUser($id, [
                'email'      => $email,
                'first_name' => trim($data['first_name']),
                'last_name'  => trim($data['last_name']),
                'role'       => $data['role'],
                'is_active'  => !empty($data['is_active']) ? 1 : 0,
            ]);

            $password = (string) ($data['password'] ?? '');
            if ($password !== '') {
                $this->userRepo->updatePassword($id, $password);
            }
            return $this->ok('User updated successfully!');
        } catch (Throwable $e) {
            return $this->logAndFail('update user', $e, 'Error updating user.');
        }
    }

    /**
     * Delete a user, refusing if the target is the current user.
     */
    public function deleteUser($id, $currentUserId) {
        $id = (int) $id;
        if ($id <= 0) {
            return $this->fail('Invalid user ID.');
        }
        if ($id === (int) $currentUserId) {
            return $this->fail('You cannot delete your own account.');
        }
        if (!$this->userRepo->getById($id)) {
            return $this->fail('User not found.');
        }

        try {
            $this->userRepo->delete($id);
            return $this->ok('User deleted successfully!');
        } catch (Throwable $e) {
            return $this->logAndFail('delete user', $e, 'Error deleting user.');
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function validateProfile(array $data) {
        $email = trim($data['email'] ?? '');
        if ($email === '') {
            return 'Email is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid email format.';
        }
        if (strlen($email) > self::EMAIL_MAX_LENGTH) {
            return 'Email is too long.';
        }

        $firstName = trim($data['first_name'] ?? '');
        if ($firstName === '') {
            return 'First name is required.';
        }
        if (strlen($firstName) > self::NAME_MAX_LENGTH) {
            return 'First name is too long.';
        }

        $lastName = trim($data['last_name'] ?? '');
        if ($lastName === '') {
            return 'Last name is required.';
        }
        if (strlen($lastName) > self::NAME_MAX_LENGTH) {
            return 'Last name is too long.';
        }

        $role = $data['role'] ?? '';
        if (!in_array($role, UserRole::all(), true)) {
            return 'Invalid role selected.';
        }

        return null;
    }

    private function ok($message) {
        return ['success' => true, 'message' => $message];
    }

    private function fail($message) {
        return ['success' => false, 'message' => $message];
    }

    private function logAndFail($context, Throwable $e, $userMessage) {
        error_log("UserService::{$context} failed: " . $e->getMessage());
        return $this->fail($userMessage);
    }
}
