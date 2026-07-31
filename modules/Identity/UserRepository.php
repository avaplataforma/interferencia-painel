<?php

declare(strict_types=1);

namespace Interferencia\Modules\Identity;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final readonly class UserRepository
{
    public function __construct(private PDO $database)
    {
    }

    public function findByEmail(string $email): ?User
    {
        $statement = $this->database->prepare('SELECT * FROM `users` WHERE `email` = :email LIMIT 1');
        $statement->execute(['email' => strtolower(trim($email))]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findById(int $id): ?User
    {
        $statement = $this->database->prepare('SELECT * FROM `users` WHERE `id` = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function createAdministrator(string $name, string $email, string $passwordHash): int
    {
        $this->database->beginTransaction();

        try {
            $statement = $this->database->prepare('INSERT INTO `users` (`name`, `email`, `password_hash`) VALUES (:name, :email, :password_hash)');
            $statement->execute([
                'name' => trim($name),
                'email' => strtolower(trim($email)),
                'password_hash' => $passwordHash,
            ]);
            $userId = (int) $this->database->lastInsertId();
            $role = $this->database->prepare("INSERT INTO `user_roles` (`user_id`, `role_id`) SELECT :user_id, `id` FROM `roles` WHERE `code` = 'super_admin'");
            $role->execute(['user_id' => $userId]);
            $scopes = $this->database->prepare('INSERT INTO `user_unit_scopes` (`user_id`, `unit_id`) SELECT :user_id, `id` FROM `units`');
            $scopes->execute(['user_id' => $userId]);
            $this->database->commit();

            return $userId;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw new RuntimeException('Não foi possível criar o administrador.', 0, $exception);
        }
    }

    /** @return list<string> */
    public function permissions(int $userId): array
    {
        $statement = $this->database->prepare('SELECT DISTINCT p.code FROM permissions p INNER JOIN role_permissions rp ON rp.permission_id = p.id INNER JOIN user_roles ur ON ur.role_id = rp.role_id WHERE ur.user_id = :user_id ORDER BY p.code');
        $statement->execute(['user_id' => $userId]);

        return array_values(array_filter($statement->fetchAll(PDO::FETCH_COLUMN), 'is_string'));
    }

    /** @return list<string> */
    public function unitScopes(int $userId): array
    {
        $statement = $this->database->prepare('SELECT u.code FROM units u INNER JOIN user_unit_scopes s ON s.unit_id = u.id WHERE s.user_id = :user_id AND u.is_active = 1 ORDER BY u.code');
        $statement->execute(['user_id' => $userId]);

        return array_values(array_filter($statement->fetchAll(PDO::FETCH_COLUMN), 'is_string'));
    }

    public function recordFailedLogin(int $userId, int $lockAfter = 5, int $lockMinutes = 15): void
    {
        $lockedUntil = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify(sprintf('+%d minutes', $lockMinutes))
            ->format('Y-m-d H:i:s');
        $statement = $this->database->prepare('UPDATE users SET failed_login_attempts = failed_login_attempts + 1, locked_until = CASE WHEN failed_login_attempts + 1 >= :lock_after THEN :locked_until ELSE locked_until END WHERE id = :id');
        $statement->execute(['lock_after' => $lockAfter, 'locked_until' => $lockedUntil, 'id' => $userId]);
    }

    public function recordSuccessfulLogin(int $userId, ?string $newHash = null): void
    {
        $sql = 'UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = UTC_TIMESTAMP()';
        $parameters = ['id' => $userId];

        if ($newHash !== null) {
            $sql .= ', password_hash = :password_hash';
            $parameters['password_hash'] = $newHash;
        }

        $statement = $this->database->prepare($sql . ' WHERE id = :id');
        $statement->execute($parameters);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): User
    {
        $locked = is_string($row['locked_until'] ?? null)
            ? new DateTimeImmutable($row['locked_until'] . ' UTC')
            : null;

        return new User(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['email'],
            (string) $row['password_hash'],
            (bool) $row['is_active'],
            (int) $row['failed_login_attempts'],
            $locked,
        );
    }
}
