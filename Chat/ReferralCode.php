<?php

/*
 * This file is part of FeatherPanel.
 *
 * Copyright (C) 2025 MythicalSystems Studios
 * Copyright (C) 2025 FeatherPanel Contributors
 * Copyright (C) 2025 Cassian Gherman (aka NaysKutzu)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See the LICENSE file or <https://www.gnu.org/licenses/>.
 */

namespace App\Addons\billingreferrals\Chat;

use App\App;
use App\Chat\Database;
use App\Chat\User;

/**
 * Referral Code chat model for managing user referral codes.
 */
class ReferralCode
{
    private static string $table = 'featherpanel_billingreferrals_codes';

    /**
     * Get a referral code by its code string.
     */
    public static function getByCode(string $code): ?array
    {
        if (empty($code)) {
            return null;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }

    /**
     * Get a referral code by its ID.
     */
    public static function getById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }

    /**
     * Get a referral code by user ID.
     */
    public static function getByUserId(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }

    /**
     * Check if a code is valid (not expired, not maxed out).
     */
    public static function isValid(array $code): bool
    {
        if (empty($code)) {
            return false;
        }

        // Check if code has expired
        if (!empty($code['expires_at'])) {
            $expiresAt = strtotime($code['expires_at']);
            if ($expiresAt !== false && $expiresAt < time()) {
                return false;
            }
        }

        // Check if code has reached max uses
        $uses = (int) ($code['uses'] ?? 0);
        $maxUses = (int) ($code['max_uses'] ?? 0);
        if ($maxUses > 0 && $uses >= $maxUses) {
            return false;
        }

        return true;
    }

    /**
     * Increment the uses count for a code.
     *
     * @param \PDO|null $pdo Optional PDO connection to use (for transactions)
     */
    public static function incrementUses(int $codeId, ?\PDO $pdo = null): bool
    {
        if ($codeId <= 0) {
            return false;
        }

        if ($pdo === null) {
            $pdo = Database::getPdoConnection();
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE ' . self::$table . ' SET uses = uses + 1 WHERE id = :id'
            );
            $stmt->execute(['id' => $codeId]);

            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to increment referral code uses: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Create a new referral code for a user.
     */
    public static function create(int $userId, ?string $customCode = null, ?int $maxUses = null, ?string $expiresAt = null): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        // Verify user exists
        if (!User::getUserById($userId)) {
            return null;
        }

        // Generate code if not provided
        $code = $customCode ?? self::generateCode();

        // Check if code already exists
        if (self::getByCode($code)) {
            return null;
        }

        $pdo = Database::getPdoConnection();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO ' . self::$table . ' (user_id, code, max_uses, expires_at) 
                VALUES (:user_id, :code, :max_uses, :expires_at)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'code' => $code,
                'max_uses' => $maxUses,
                'expires_at' => $expiresAt,
            ]);

            return self::getById((int) $pdo->lastInsertId());
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to create referral code: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Get or create a referral code for a user.
     */
    public static function getOrCreateForUser(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $existing = self::getByUserId($userId);
        if ($existing) {
            return $existing;
        }

        return self::create($userId);
    }

    /**
     * Update a referral code.
     */
    public static function update(int $id, array $data): bool
    {
        if ($id <= 0) {
            return false;
        }

        $pdo = Database::getPdoConnection();
        $updates = [];
        $params = ['id' => $id];

        if (isset($data['code'])) {
            // Check if new code already exists
            $existing = self::getByCode($data['code']);
            if ($existing && (int) $existing['id'] !== $id) {
                return false;
            }
            $updates[] = 'code = :code';
            $params['code'] = $data['code'];
        }
        if (isset($data['max_uses'])) {
            $updates[] = 'max_uses = :max_uses';
            $params['max_uses'] = $data['max_uses'] === null ? null : (int) $data['max_uses'];
        }
        if (isset($data['expires_at'])) {
            $updates[] = 'expires_at = :expires_at';
            $params['expires_at'] = empty($data['expires_at']) ? null : $data['expires_at'];
        }

        if (empty($updates)) {
            return false;
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE ' . self::$table . ' SET ' . implode(', ', $updates) . ' WHERE id = :id'
            );
            $stmt->execute($params);

            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to update referral code: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Delete a referral code.
     */
    public static function delete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $pdo = Database::getPdoConnection();

        try {
            $stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE id = :id');
            $stmt->execute(['id' => $id]);

            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to delete referral code: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Delete all referral codes for a user.
     */
    public static function deleteByUserId(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $pdo = Database::getPdoConnection();

        try {
            $stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE user_id = :user_id');
            $stmt->execute(['user_id' => $userId]);

            return true;
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to delete user referral codes: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Get all referral codes with pagination.
     */
    public static function getAll(int $limit = 50, int $offset = 0): array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT c.*, u.username, u.email 
            FROM ' . self::$table . ' c
            INNER JOIN featherpanel_users u ON c.user_id = u.id
            ORDER BY c.created_at DESC 
            LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get total count of referral codes.
     */
    public static function getCount(): int
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM ' . self::$table);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ? (int) $result['count'] : 0;
    }

    /**
     * Get table name (for use in JOINs).
     */
    public static function getTable(): string
    {
        return self::$table;
    }

    /**
     * Generate a unique referral code.
     */
    public static function generateCode(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        // Check if code already exists and regenerate if needed
        if (self::getByCode($code)) {
            return self::generateCode();
        }

        return $code;
    }
}
