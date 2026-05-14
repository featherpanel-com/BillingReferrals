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

namespace App\Addons\billingreferrals\Controllers\User;

use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\billingcore\Helpers\CreditsHelper;
use App\Addons\billingcore\Helpers\CurrencyHelper;
use App\Addons\billingreferrals\Chat\ReferralCode;
use App\Addons\billingreferrals\Chat\ReferralUsage;
use App\Addons\billingreferrals\Helpers\ReferralHelper;

#[OA\Tag(name: 'User - Billing Referrals', description: 'Referral system for users')]
class BillingReferralsController
{
    #[OA\Get(
        path: '/api/user/billingreferrals/my-code',
        summary: 'Get my referral code',
        description: 'Get or create the current user\'s referral code',
        tags: ['User - Billing Referrals'],
        responses: [
            new OA\Response(response: 200, description: 'Code retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Referral system disabled'),
        ]
    )]
    public function getMyCode(Request $request): Response
    {
        $user = $request->attributes->get('user') ?? $request->get('user');
        if (!$user || !isset($user['id'])) {
            return ApiResponse::error('User not authenticated', 'UNAUTHORIZED', 401);
        }

        if (!ReferralHelper::isEnabled()) {
            return ApiResponse::error('Referral system is currently disabled', 'REFERRALS_DISABLED', 403);
        }

        // Get or create referral code for this user
        $code = ReferralCode::getOrCreateForUser($user['id']);
        if (!$code) {
            return ApiResponse::error('Failed to get or create referral code', 'CODE_FAILED', 500);
        }

        $code['usage_count'] = ReferralUsage::getCodeUsageCount((int) $code['id']);
        $code['is_valid'] = ReferralCode::isValid($code);

        // Generate referral link
        $appUrl = rtrim(\App\App::getInstance(true)->getConfig()->getSetting(
            \App\Config\ConfigInterface::APP_URL,
            ''
        ), '/');
        $code['referral_link'] = $appUrl . '/auth/register?ref=' . urlencode($code['code']);

        return ApiResponse::success($code, 'Code retrieved successfully', 200);
    }

    #[OA\Patch(
        path: '/api/user/billingreferrals/my-code',
        summary: 'Update my referral code',
        description: 'Update the current user\'s referral code settings',
        tags: ['User - Billing Referrals'],
        responses: [
            new OA\Response(response: 200, description: 'Code updated successfully'),
            new OA\Response(response: 400, description: 'Invalid request data'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Referral system disabled or custom codes not allowed'),
        ]
    )]
    public function updateMyCode(Request $request): Response
    {
        $user = $request->attributes->get('user') ?? $request->get('user');
        if (!$user || !isset($user['id'])) {
            return ApiResponse::error('User not authenticated', 'UNAUTHORIZED', 401);
        }

        if (!ReferralHelper::isEnabled()) {
            return ApiResponse::error('Referral system is currently disabled', 'REFERRALS_DISABLED', 403);
        }

        $data = json_decode($request->getContent() ?: '{}', true, 32);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::error('Invalid JSON in request body', 'INVALID_JSON', 400);
        }

        // Get user's existing code
        $code = ReferralCode::getByUserId($user['id']);
        if (!$code) {
            // Create one if doesn't exist
            $code = ReferralCode::getOrCreateForUser($user['id']);
            if (!$code) {
                return ApiResponse::error('Failed to get or create referral code', 'CODE_FAILED', 500);
            }
        }

        $updateData = [];

        // Handle custom code
        if (isset($data['code'])) {
            if (!ReferralHelper::allowCustomCodes()) {
                return ApiResponse::error('Custom referral codes are not allowed', 'CUSTOM_CODES_DISABLED', 403);
            }

            $newCode = trim($data['code']);
            if (strlen($newCode) < 4) {
                return ApiResponse::error('Code must be at least 4 characters', 'CODE_TOO_SHORT', 400);
            }
            if (strlen($newCode) > 20) {
                return ApiResponse::error('Code must be at most 20 characters', 'CODE_TOO_LONG', 400);
            }
            if (!preg_match('/^[A-Z0-9]+$/i', $newCode)) {
                return ApiResponse::error('Code can only contain letters and numbers', 'CODE_INVALID_CHARS', 400);
            }

            $updateData['code'] = strtoupper($newCode);
        }

        // Handle max_uses
        if (isset($data['max_uses'])) {
            $maxUses = $data['max_uses'] === null ? null : (int) $data['max_uses'];
            if ($maxUses !== null && $maxUses < 0) {
                return ApiResponse::error('Max uses must be 0 or greater (null for unlimited)', 'INVALID_MAX_USES', 400);
            }
            $updateData['max_uses'] = $maxUses;
        }

        // Handle expires_at
        if (array_key_exists('expires_at', $data)) {
            $updateData['expires_at'] = empty($data['expires_at']) ? null : $data['expires_at'];
        }

        if (empty($updateData)) {
            return ApiResponse::error('No valid fields to update', 'NO_UPDATES', 400);
        }

        $updated = ReferralCode::update((int) $code['id'], $updateData);
        if (!$updated) {
            return ApiResponse::error('Failed to update code (code may already exist)', 'UPDATE_FAILED', 500);
        }

        $updatedCode = ReferralCode::getById((int) $code['id']);
        $updatedCode['usage_count'] = ReferralUsage::getCodeUsageCount((int) $updatedCode['id']);
        $updatedCode['is_valid'] = ReferralCode::isValid($updatedCode);

        // Generate referral link
        $appUrl = rtrim(\App\App::getInstance(true)->getConfig()->getSetting(
            \App\Config\ConfigInterface::APP_URL,
            ''
        ), '/');
        $updatedCode['referral_link'] = $appUrl . '/auth/register?ref=' . urlencode($updatedCode['code']);

        return ApiResponse::success($updatedCode, 'Code updated successfully', 200);
    }

    #[OA\Get(
        path: '/api/user/billingreferrals/my-referrals',
        summary: 'Get my referrals',
        description: 'Get list of users who signed up using the current user\'s referral code',
        tags: ['User - Billing Referrals'],
        responses: [
            new OA\Response(response: 200, description: 'Referrals retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Referral system disabled'),
        ]
    )]
    public function getMyReferrals(Request $request): Response
    {
        $user = $request->attributes->get('user') ?? $request->get('user');
        if (!$user || !isset($user['id'])) {
            return ApiResponse::error('User not authenticated', 'UNAUTHORIZED', 401);
        }

        if (!ReferralHelper::isEnabled()) {
            return ApiResponse::error('Referral system is currently disabled', 'REFERRALS_DISABLED', 403);
        }

        $limit = (int) $request->query->get('limit', 50);
        $offset = (int) $request->query->get('offset', 0);

        if ($limit > 100) {
            $limit = 100;
        }
        if ($limit < 1) {
            $limit = 50;
        }

        $referrals = ReferralUsage::getUserReferrals($user['id'], $limit, $offset);
        $total = ReferralUsage::getUserReferralsCount($user['id']);

        // Calculate total credits earned
        $referrerCredits = ReferralHelper::getReferrerCredits();
        $totalCreditsEarned = $total * $referrerCredits;

        return ApiResponse::success([
            'referrals' => $referrals,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'credits_per_referral' => $referrerCredits,
            'credits_per_referral_formatted' => CurrencyHelper::formatAmount($referrerCredits),
            'total_credits_earned' => $totalCreditsEarned,
            'total_credits_earned_formatted' => CurrencyHelper::formatAmount($totalCreditsEarned),
        ], 'Referrals retrieved successfully', 200);
    }

    #[OA\Get(
        path: '/api/user/billingreferrals/stats',
        summary: 'Get my referral stats',
        description: 'Get statistics about the current user\'s referral activity',
        tags: ['User - Billing Referrals'],
        responses: [
            new OA\Response(response: 200, description: 'Stats retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Referral system disabled'),
        ]
    )]
    public function getMyStats(Request $request): Response
    {
        $user = $request->attributes->get('user') ?? $request->get('user');
        if (!$user || !isset($user['id'])) {
            return ApiResponse::error('User not authenticated', 'UNAUTHORIZED', 401);
        }

        if (!ReferralHelper::isEnabled()) {
            return ApiResponse::error('Referral system is currently disabled', 'REFERRALS_DISABLED', 403);
        }

        // Get or create referral code
        $code = ReferralCode::getOrCreateForUser($user['id']);
        if (!$code) {
            return ApiResponse::error('Failed to get referral code', 'CODE_FAILED', 500);
        }

        $referralCount = ReferralUsage::getUserReferralsCount($user['id']);
        $currentCredits = CreditsHelper::getUserCredits($user['id']);

        $settings = ReferralHelper::getSettings();

        // Calculate potential earnings
        $referrerCredits = $settings['referrer_credits'];
        $refereeCredits = $settings['referee_credits'];
        $totalEarned = $referralCount * $referrerCredits;

        // Generate referral link
        $appUrl = rtrim(\App\App::getInstance(true)->getConfig()->getSetting(
            \App\Config\ConfigInterface::APP_URL,
            ''
        ), '/');

        return ApiResponse::success([
            'code' => $code['code'],
            'referral_link' => $appUrl . '/auth/register?ref=' . urlencode($code['code']),
            'is_valid' => ReferralCode::isValid($code),
            'uses' => (int) $code['uses'],
            'max_uses' => $code['max_uses'] ? (int) $code['max_uses'] : null,
            'referral_count' => $referralCount,
            'referrer_credits' => $referrerCredits,
            'referrer_credits_formatted' => CurrencyHelper::formatAmount($referrerCredits),
            'referee_credits' => $refereeCredits,
            'referee_credits_formatted' => CurrencyHelper::formatAmount($refereeCredits),
            'total_credits_earned' => $totalEarned,
            'total_credits_earned_formatted' => CurrencyHelper::formatAmount($totalEarned),
            'current_credits' => $currentCredits,
            'current_credits_formatted' => CurrencyHelper::formatAmount($currentCredits),
            'allow_custom_codes' => $settings['allow_custom_codes'],
        ], 'Stats retrieved successfully', 200);
    }

    #[OA\Post(
        path: '/api/user/billingreferrals/visit',
        summary: 'Track referral visit',
        description: 'Track when a visitor clicks on a referral link (sets cookie)',
        tags: ['User - Billing Referrals'],
        responses: [
            new OA\Response(response: 200, description: 'Referral visit tracked'),
            new OA\Response(response: 400, description: 'Invalid referral code'),
        ]
    )]
    public function trackVisit(Request $request): Response
    {
        $data = json_decode($request->getContent() ?: '{}', true, 32);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::error('Invalid JSON in request body', 'INVALID_JSON', 400);
        }

        $code = trim($data['code'] ?? '');
        if (empty($code)) {
            return ApiResponse::error('Code is required', 'CODE_REQUIRED', 400);
        }

        // Verify code exists and is valid
        $referralCode = ReferralCode::getByCode($code);
        if (!$referralCode) {
            return ApiResponse::error('Invalid referral code', 'CODE_INVALID', 400);
        }

        if (!ReferralCode::isValid($referralCode)) {
            return ApiResponse::error('This referral code is no longer valid', 'CODE_INVALID', 400);
        }

        // Set cookie to track this referral
        $lifetimeDays = ReferralHelper::getCookieLifetimeDays();
        setcookie('billingreferrals_code', $code, [
            'expires' => time() + ($lifetimeDays * 86400),
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $refereeCredits = ReferralHelper::getRefereeCredits();

        return ApiResponse::success([
            'code' => $code,
            'cookie_set' => true,
            'expires_days' => $lifetimeDays,
            'referee_credits' => $refereeCredits,
        ], 'Referral visit tracked', 200);
    }

    #[OA\Get(
        path: '/api/billingreferrals/register-context',
        summary: 'Register page referral context',
        description: 'Public: whether referrals are enabled, if a referral cookie is already set, and the referee bonus amount for display.',
        tags: ['User - Billing Referrals'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ]
    )]
    public function getRegisterContext(Request $request): Response
    {
        if (!ReferralHelper::isEnabled()) {
            return ApiResponse::success([
                'referrals_enabled' => false,
                'has_referral_cookie' => false,
                'referee_credits' => 0,
            ], 'Referral program disabled', 200);
        }

        $cookieCode = trim((string) ($request->cookies->get('billingreferrals_code') ?? ''));

        return ApiResponse::success([
            'referrals_enabled' => true,
            'has_referral_cookie' => $cookieCode !== '',
            'referee_credits' => ReferralHelper::getRefereeCredits(),
        ], 'OK', 200);
    }
}
