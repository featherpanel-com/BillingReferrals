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

namespace App\Addons\billingreferrals\Helpers;

use App\Plugins\PluginSettings;

/**
 * Helper for working with Referral settings using PluginSettings.
 */
class ReferralHelper
{
    private const PLUGIN_IDENTIFIER = 'billingreferrals';

    /**
     * Get all referral settings.
     */
    public static function getSettings(): array
    {
        return [
            'is_enabled' => self::getSetting('is_enabled') === '1' || self::getSetting('is_enabled') === 'true',
            'referrer_credits' => self::getIntSetting('referrer_credits', 100),
            'referee_credits' => self::getIntSetting('referee_credits', 50),
            'default_max_uses' => self::getIntSetting('default_max_uses', 0),
            'cookie_lifetime_days' => self::getIntSetting('cookie_lifetime_days', 30),
            'allow_custom_codes' => self::getSetting('allow_custom_codes') === '1' || self::getSetting('allow_custom_codes') === 'true',
        ];
    }

    /**
     * Update referral settings.
     */
    public static function updateSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            if ($value === null) {
                continue;
            }

            // Convert boolean to string
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            } else {
                $value = (string) $value;
            }

            self::setSetting($key, $value);
        }
    }

    /**
     * Check if referral system is enabled.
     */
    public static function isEnabled(): bool
    {
        return self::getSetting('is_enabled') === '1' || self::getSetting('is_enabled') === 'true';
    }

    /**
     * Get credits awarded to the referrer (the one who invited).
     */
    public static function getReferrerCredits(): int
    {
        return self::getIntSetting('referrer_credits', 100);
    }

    /**
     * Get credits awarded to the referee (the new user who signed up).
     */
    public static function getRefereeCredits(): int
    {
        return self::getIntSetting('referee_credits', 50);
    }

    /**
     * Get default max uses for referral codes (0 = unlimited).
     */
    public static function getDefaultMaxUses(): int
    {
        return self::getIntSetting('default_max_uses', 0);
    }

    /**
     * Get cookie lifetime in days.
     */
    public static function getCookieLifetimeDays(): int
    {
        return self::getIntSetting('cookie_lifetime_days', 30);
    }

    /**
     * Check if users can set custom referral codes.
     */
    public static function allowCustomCodes(): bool
    {
        return self::getSetting('allow_custom_codes') === '1' || self::getSetting('allow_custom_codes') === 'true';
    }

    /**
     * Get a setting value as integer.
     */
    private static function getIntSetting(string $key, int $default): int
    {
        $value = self::getSetting($key);
        if ($value === null || $value === '') {
            return $default;
        }

        return (int) $value;
    }

    /**
     * Get a setting value.
     */
    private static function getSetting(string $key): ?string
    {
        return PluginSettings::getSetting(self::PLUGIN_IDENTIFIER, $key);
    }

    /**
     * Set a setting value.
     */
    private static function setSetting(string $key, string $value): void
    {
        PluginSettings::setSetting(self::PLUGIN_IDENTIFIER, $key, $value);
    }
}
