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
use App\Chat\User;
use App\Chat\Database;

/**
 * Referral Usage chat model for tracking who used which referral code.
 */
class ReferralUsage
{
    private static string $table = 'featherpanel_billingreferrals_usage';

    /**
     * Check if a user has already been referred by someone.
     */
    public static function hasUserBeenReferred(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT id FROM ' . self::$table . ' WHERE referred_user_id = :user_id LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result !== false;
    }

    /**
     * Check if a user has already used a specific referral code.
     */
    public static function hasUserUsedCode(int $userId, int $codeId): bool
    {
        if ($userId <= 0 || $codeId <= 0) {
            return false;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT id FROM ' . self::$table . ' WHERE referred_user_id = :user_id AND code_id = :code_id LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'code_id' => $codeId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result !== false;
    }

    /**
     * Record that a user used a referral code.
     *
     * @param \PDO|null $pdo Optional PDO connection to use (for transactions)
     */
    public static function recordUsage(int $codeId, int $referredUserId, ?\PDO $pdo = null): bool
    {
        if ($codeId <= 0 || $referredUserId <= 0) {
            return false;
        }

        if (!self::assertUserExists($referredUserId)) {
            return false;
        }

        if ($pdo === null) {
            $pdo = Database::getPdoConnection();
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO ' . self::$table . ' (code_id, referred_user_id, created_at) 
                VALUES (:code_id, :referred_user_id, NOW())'
            );
            $stmt->execute([
                'code_id' => $codeId,
                'referred_user_id' => $referredUserId,
            ]);

            return true;
        } catch (\PDOException $e) {
            // Handle duplicate key (user already referred)
            if ($e->getCode() === '23000') {
                App::getInstance(true)->getLogger()->warning('Duplicate referral usage: ' . $e->getMessage());

                return false;
            }
            App::getInstance(true)->getLogger()->error('Failed to record referral usage: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Get usage record by user and code.
     */
    public static function getUsage(int $userId, int $codeId): ?array
    {
        if ($userId <= 0 || $codeId <= 0) {
            return null;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT * FROM ' . self::$table . ' WHERE referred_user_id = :user_id AND code_id = :code_id LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'code_id' => $codeId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }

    /**
     * Get all users referred by a specific code.
     */
    public static function getCodeUsage(int $codeId, int $limit = 50, int $offset = 0): array
    {
        if ($codeId <= 0) {
            return [];
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT u.*, usr.email, usr.username, usr.first_name, usr.last_name, usr.created_at as user_created_at
            FROM ' . self::$table . ' u
            INNER JOIN featherpanel_users usr ON u.referred_user_id = usr.id
            WHERE u.code_id = :code_id 
            ORDER BY u.created_at DESC 
            LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':code_id', $codeId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get total usage count for a code.
     */
    public static function getCodeUsageCount(int $codeId): int
    {
        if ($codeId <= 0) {
            return 0;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) as count FROM ' . self::$table . ' WHERE code_id = :code_id'
        );
        $stmt->execute(['code_id' => $codeId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ? (int) $result['count'] : 0;
    }

    /**
     * Get all referrals made by a user (their code was used).
     */
    public static function getUserReferrals(int $userId, int $limit = 50, int $offset = 0): array
    {
        if ($userId <= 0) {
            return [];
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT u.*, c.code, usr.email, usr.username, usr.first_name, usr.last_name
            FROM ' . self::$table . ' u
            INNER JOIN ' . ReferralCode::getTable() . ' c ON u.code_id = c.id
            INNER JOIN featherpanel_users usr ON u.referred_user_id = usr.id
            WHERE c.user_id = :user_id 
            ORDER BY u.created_at DESC 
            LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get total count of referrals made by a user.
     */
    public static function getUserReferralsCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) as count 
            FROM ' . self::$table . ' u
            INNER JOIN ' . ReferralCode::getTable() . ' c ON u.code_id = c.id
            WHERE c.user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ? (int) $result['count'] : 0;
    }

    /**
     * Get total count of all referrals.
     */
    public static function getTotalReferralsCount(): int
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM ' . self::$table);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ? (int) $result['count'] : 0;
    }

    /**
     * Delete usage records for a code (when code is deleted).
     */
    public static function deleteByCodeId(int $codeId): bool
    {
        if ($codeId <= 0) {
            return false;
        }

        $pdo = Database::getPdoConnection();

        try {
            $stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE code_id = :code_id');
            $stmt->execute(['code_id' => $codeId]);

            return true;
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to delete code usage: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Delete usage records for a user (when user is deleted).
     */
    public static function deleteByUserId(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $pdo = Database::getPdoConnection();

        try {
            $stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE referred_user_id = :user_id');
            $stmt->execute(['user_id' => $userId]);

            return true;
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to delete user usage: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Get table name (for use in JOINs).
     */
    public static function getTable(): string
    {
        return self::$table;
    }

    private static function assertUserExists(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $user = User::getUserById($userId);

        return $user !== null;
    }
}
