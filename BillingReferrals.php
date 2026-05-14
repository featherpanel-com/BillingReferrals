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

namespace App\Addons\billingreferrals;

use App\App;
use App\Plugins\AppPlugin;
use App\Plugins\Events\Events\AuthEvent;
use App\Addons\billingreferrals\Helpers\ReferralHelper;
use App\Addons\billingreferrals\Chat\ReferralCode;
use App\Addons\billingreferrals\Chat\ReferralUsage;
use App\Addons\billingcore\Helpers\CreditsHelper;

class BillingReferrals implements AppPlugin
{
    public static function processEvents(\App\Plugins\PluginEvents $event): void
    {
        // Listen for successful user registration
        $event->on(AuthEvent::onAuthRegisterSuccess(), function (array $data) {
            self::processNewUserRegistration($data);
        });

        // Listen for user creation (admin created users)
        $event->on(\App\Plugins\Events\Events\UserEvent::onUserCreated(), function (array $data) {
            self::processNewUserRegistration($data['user_data'] ?? $data);
        });
    }

    /**
     * Process a new user registration to check for referral codes.
     */
    private static function processNewUserRegistration(array $userData): void
    {
        // Check if referral system is enabled
        if (!ReferralHelper::isEnabled()) {
            return;
        }

        $userId = $userData['id'] ?? null;
        if (!$userId) {
            return;
        }

        // Check if there's a referral code in the session/cookie
        $referralCode = self::getReferralCodeFromSession();
        if (empty($referralCode)) {
            return;
        }

        // Get the referral code from database
        $code = ReferralCode::getByCode($referralCode);
        if (!$code) {
            return;
        }

        // Check if code is valid (not expired, not maxed out)
        if (!ReferralCode::isValid($code)) {
            App::getInstance(true)->getLogger()->warning('Referral code no longer valid: ' . $referralCode);

            return;
        }

        // Don't allow self-referral
        if ((int) $code['user_id'] === (int) $userId) {
            App::getInstance(true)->getLogger()->warning('User tried to use their own referral code: ' . $userId);

            return;
        }

        // Check if this user has already been referred by someone else
        if (ReferralUsage::hasUserBeenReferred($userId)) {
            App::getInstance(true)->getLogger()->warning('User already referred by someone else: ' . $userId);

            return;
        }

        // Use transaction to ensure atomicity
        $pdo = \App\Chat\Database::getPdoConnection();
        try {
            $pdo->beginTransaction();

            // Double-check code validity with lock
            $stmt = $pdo->prepare(
                'SELECT * FROM featherpanel_billingreferrals_codes WHERE id = :id FOR UPDATE'
            );
            $stmt->execute(['id' => $code['id']]);
            $lockedCode = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$lockedCode || !ReferralCode::isValid($lockedCode)) {
                $pdo->rollBack();

                return;
            }

            // Record the referral usage
            $usageRecorded = ReferralUsage::recordUsage((int) $code['id'], $userId, $pdo);
            if (!$usageRecorded) {
                $pdo->rollBack();

                return;
            }

            // Increment code uses
            $incremented = ReferralCode::incrementUses((int) $code['id'], $pdo);
            if (!$incremented) {
                $pdo->rollBack();

                return;
            }

            // Award credits to the referrer
            $referrerCredits = ReferralHelper::getReferrerCredits();
            if ($referrerCredits > 0) {
                $referrerId = (int) $code['user_id'];
                $added = CreditsHelper::addUserCredits($referrerId, $referrerCredits);
                if (!$added) {
                    App::getInstance(true)->getLogger()->error(
                        'Failed to award referrer credits for user ' . $referrerId . ' and referral ' . $code['id']
                    );
                }
            }

            // Award credits to the new user (referee)
            $refereeCredits = ReferralHelper::getRefereeCredits();
            if ($refereeCredits > 0) {
                $added = CreditsHelper::addUserCredits($userId, $refereeCredits);
                if (!$added) {
                    App::getInstance(true)->getLogger()->error(
                        'Failed to award referee credits for user ' . $userId
                    );
                }
            }

            $pdo->commit();

            // Clear the referral code from session
            self::clearReferralCodeFromSession();

            App::getInstance(true)->getLogger()->info(
                'Referral processed successfully. Code: ' . $referralCode . ', New user: ' . $userId . 
                ', Referrer: ' . $code['user_id']
            );
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            App::getInstance(true)->getLogger()->error('Failed to process referral: ' . $e->getMessage());
        }
    }

    /**
     * Get referral code from session/cookie.
     */
    private static function getReferralCodeFromSession(): ?string
    {
        // Check for referral code in cookie (set when user visits referral link)
        if (isset($_COOKIE['billingreferrals_code'])) {
            return $_COOKIE['billingreferrals_code'];
        }

        return null;
    }

    /**
     * Clear referral code from session/cookie.
     */
    private static function clearReferralCodeFromSession(): void
    {
        // Clear the cookie
        if (isset($_COOKIE['billingreferrals_code'])) {
            setcookie('billingreferrals_code', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    public static function pluginInstall(): void
    {
        // Plugin installation logic
        // Tables will be created via migrations
    }

    public static function pluginUpdate(?string $oldVersion, ?string $newVersion): void
    {
        // Plugin update logic
    }

    public static function pluginUninstall(): void
    {
        // Plugin uninstallation logic
        // Clean up is handled by the migration system
    }
}
