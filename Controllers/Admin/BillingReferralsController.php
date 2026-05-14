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

namespace App\Addons\billingreferrals\Controllers\Admin;

use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use App\Addons\billingreferrals\Chat\ReferralCode;
use Symfony\Component\HttpFoundation\Request;
use App\Addons\billingreferrals\Chat\ReferralUsage;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\billingcore\Helpers\CurrencyHelper;
use App\Addons\billingreferrals\Helpers\ReferralHelper;

#[OA\Tag(name: 'Admin - Billing Referrals', description: 'Referral system administration')]
class BillingReferralsController
{
    #[OA\Get(
        path: '/api/admin/billingreferrals/settings',
        summary: 'Get referral settings',
        description: 'Get all referral configuration settings',
        tags: ['Admin - Billing Referrals'],
        responses: [
            new OA\Response(response: 200, description: 'Settings retrieved successfully'),
        ]
    )]
    public function getSettings(Request $request): Response
    {
        $settings = ReferralHelper::getSettings();

        return ApiResponse::success($settings, 'Settings retrieved successfully', 200);
    }

    #[OA\Patch(
        path: '/api/admin/billingreferrals/settings',
        summary: 'Update referral settings',
        description: 'Update referral configuration settings',
        tags: ['Admin - Billing Referrals'],
        responses: [
            new OA\Response(response: 200, description: 'Settings updated successfully'),
            new OA\Response(response: 400, description: 'Invalid request data'),
        ]
    )]
    public function updateSettings(Request $request): Response
    {
        $data = json_decode($request->getContent() ?: '{}', true, 32);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::error('Invalid JSON in request body', 'INVALID_JSON', 400);
        }

        // Validate credits are non-negative
        if (isset($data['referrer_credits']) && (!is_numeric($data['referrer_credits']) || (int) $data['referrer_credits'] < 0)) {
            return ApiResponse::error('Referrer credits must be a non-negative integer', 'INVALID_REFERRER_CREDITS', 400);
        }
        if (isset($data['referee_credits']) && (!is_numeric($data['referee_credits']) || (int) $data['referee_credits'] < 0)) {
            return ApiResponse::error('Referee credits must be a non-negative integer', 'INVALID_REFEREE_CREDITS', 400);
        }
        if (isset($data['default_max_uses']) && (!is_numeric($data['default_max_uses']) || (int) $data['default_max_uses'] < 0)) {
            return ApiResponse::error('Default max uses must be a non-negative integer (0 for unlimited)', 'INVALID_MAX_USES', 400);
        }
        if (isset($data['cookie_lifetime_days']) && (!is_numeric($data['cookie_lifetime_days']) || (int) $data['cookie_lifetime_days'] < 1)) {
            return ApiResponse::error('Cookie lifetime must be at least 1 day', 'INVALID_COOKIE_LIFETIME', 400);
        }

        try {
            ReferralHelper::updateSettings($data);

            return ApiResponse::success(ReferralHelper::getSettings(), 'Settings updated successfully', 200);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update settings: ' . $e->getMessage(), 'UPDATE_FAILED', 500);
        }
    }

    #[OA\Get(
        path: '/api/admin/billingreferrals/codes',
        summary: 'Get all referral codes',
        description: 'Get all referral codes with pagination and user info',
        tags: ['Admin - Billing Referrals'],
        responses: [
            new OA\Response(response: 200, description: 'Codes retrieved successfully'),
        ]
    )]
    public function getCodes(Request $request): Response
    {
        $limit = (int) $request->query->get('limit', 50);
        $offset = (int) $request->query->get('offset', 0);

        if ($limit > 100) {
            $limit = 100;
        }
        if ($limit < 1) {
            $limit = 50;
        }

        $codes = ReferralCode::getAll($limit, $offset);
        $total = ReferralCode::getCount();

        // Add usage count for each code
        foreach ($codes as &$code) {
            $code['usage_count'] = ReferralUsage::getCodeUsageCount((int) $code['id']);
            $code['is_valid'] = ReferralCode::isValid($code);
        }

        return ApiResponse::success([
            'codes' => $codes,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ], 'Codes retrieved successfully', 200);
    }

    #[OA\Get(
        path: '/api/admin/billingreferrals/codes/{id}',
        summary: 'Get code by ID',
        description: 'Get a specific referral code by ID',
        tags: ['Admin - Billing Referrals'],
        responses: [
            new OA\Response(response: 200, description: 'Code retrieved successfully'),
            new OA\Response(response: 404, description: 'Code not found'),
        ]
    )]
    public function getCode(Request $request, int $id): Response
    {
        $code = ReferralCode::getById($id);
        if (!$code) {
            return ApiResponse::error('Code not found', 'CODE_NOT_FOUND', 404);
        }

        $code['usage_count'] = ReferralUsage::getCodeUsageCount($id);
        $code['is_valid'] = ReferralCode::isValid($code);

        return ApiResponse::success($code, 'Code retrieved successfully', 200);
    }

    #[OA\Post(
        path: '/api/admin/billingreferrals/codes',
        summary: 'Create a new referral code',
        description: 'Create a new referral code for a user',
        tags: ['Admin - Billing Referrals'],
        responses: [
            new OA\Response(response: 200, description: 'Code created successfully'),
            new OA\Response(response: 400, description: 'Invalid request data'),
        ]
    )]
    public function createCode(Request $request): Response
    {
        $data = json_decode($request->getContent() ?: '{}', true, 32);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::error('Invalid JSON in request body', 'INVALID_JSON', 400);
        }

        $userId = isset($data['user_id']) ? (int) $data['user_id'] : 0;
        $code = !empty($data['code']) ? trim($data['code']) : null;
        $maxUses = isset($data['max_uses']) ? (int) $data['max_uses'] : null;
        $expiresAt = !empty($data['expires_at']) ? $data['expires_at'] : null;

        if ($userId <= 0) {
            return ApiResponse::error('User ID is required', 'USER_ID_REQUIRED', 400);
        }

        // Validate user exists
        if (!\App\Chat\User::getUserById($userId)) {
            return ApiResponse::error('User not found', 'USER_NOT_FOUND', 404);
        }

        // Check if custom codes are allowed
        if ($code !== null && !ReferralHelper::allowCustomCodes()) {
            $code = null; // Ignore custom code, will auto-generate
        }

        // Check if code already exists
        if ($code !== null && ReferralCode::getByCode($code)) {
            return ApiResponse::error('Code already exists', 'CODE_EXISTS', 400);
        }

        $created = ReferralCode::create($userId, $code, $maxUses, $expiresAt);
        if (!$created) {
            return ApiResponse::error('Failed to create code', 'CREATE_FAILED', 500);
        }

        $created['usage_count'] = 0;
        $created['is_valid'] = ReferralCode::isValid($created);

        return ApiResponse::success($created, 'Code created successfully', 200);
    }

    #[OA\Patch(
        path: '/api/admin/billingreferrals/codes/{id}',
        summary: 'Update a referral code',
        description: 'Update an existing referral code',
        tags: ['Admin - Billing Referrals'],
        responses: [
            new OA\Response(response: 200, description: 'Code updated successfully'),
            new OA\Response(response: 404, description: 'Code not found'),
        ]
    )]
    public function updateCode(Request $request, int $id): Response
    {
        $code = ReferralCode::getById($id);
        if (!$code) {
            return ApiResponse::error('Code not found', 'CODE_NOT_FOUND', 404);
        }

        $data = json_decode($request->getContent() ?: '{}', true, 32);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::error('Invalid JSON in request body', 'INVALID_JSON', 400);
        }

        // Check if code is being changed and if it already exists
        if (isset($data['code']) && $data['code'] !== $code['code']) {
            $existing = ReferralCode::getByCode($data['code']);
            if ($existing) {
                return ApiResponse::error('Code already exists', 'CODE_EXISTS', 400);
            }
        }

        $updated = ReferralCode::update($id, $data);
        if (!$updated) {
            return ApiResponse::error('Failed to update code', 'UPDATE_FAILED', 500);
        }

        $updatedCode = ReferralCode::getById($id);
        $updatedCode['usage_count'] = ReferralUsage::getCodeUsageCount($id);
        $updatedCode['is_valid'] = ReferralCode::isValid($updatedCode);

        return ApiResponse::success($updatedCode, 'Code updated successfully', 200);
    }

    #[OA\Delete(
        path: '/api/admin/billingreferrals/codes/{id}',
        summary: 'Delete a referral code',
        description: 'Delete a referral code and its usage records',
        tags: ['Admin - Billing Referrals'],
        responses: [
            new OA\Response(response: 200, description: 'Code deleted successfully'),
            new OA\Response(response: 404, description: 'Code not found'),
        ]
    )]
    public function deleteCode(Request $request, int $id): Response
    {
        $code = ReferralCode::getById($id);
        if (!$code) {
            return ApiResponse::error('Code not found', 'CODE_NOT_FOUND', 404);
        }

        // Delete usage records first
        ReferralUsage::deleteByCodeId($id);

        $deleted = ReferralCode::delete($id);
        if (!$deleted) {
            return ApiResponse::error('Failed to delete code', 'DELETE_FAILED', 500);
        }

        return ApiResponse::success([], 'Code deleted successfully', 200);
    }

    #[OA\Get(
        path: '/api/admin/billingreferrals/codes/{id}/usage',
        summary: 'Get code usage',
        description: 'Get all users who used a specific referral code',
        tags: ['Admin - Billing Referrals'],
        responses: [
            new OA\Response(response: 200, description: 'Usage retrieved successfully'),
            new OA\Response(response: 404, description: 'Code not found'),
        ]
    )]
    public function getCodeUsage(Request $request, int $id): Response
    {
        $code = ReferralCode::getById($id);
        if (!$code) {
            return ApiResponse::error('Code not found', 'CODE_NOT_FOUND', 404);
        }

        $limit = (int) $request->query->get('limit', 50);
        $offset = (int) $request->query->get('offset', 0);

        if ($limit > 100) {
            $limit = 100;
        }
        if ($limit < 1) {
            $limit = 50;
        }

        $usage = ReferralUsage::getCodeUsage($id, $limit, $offset);
        $total = ReferralUsage::getCodeUsageCount($id);

        return ApiResponse::success([
            'usage' => $usage,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ], 'Usage retrieved successfully', 200);
    }

    #[OA\Get(
        path: '/api/admin/billingreferrals/stats',
        summary: 'Get referral statistics',
        description: 'Get overall referral system statistics',
        tags: ['Admin - Billing Referrals'],
        responses: [
            new OA\Response(response: 200, description: 'Statistics retrieved successfully'),
        ]
    )]
    public function getStats(Request $request): Response
    {
        $totalCodes = ReferralCode::getCount();
        $totalReferrals = ReferralUsage::getTotalReferralsCount();

        // Calculate credits awarded
        $settings = ReferralHelper::getSettings();
        $referrerCredits = $settings['referrer_credits'];
        $refereeCredits = $settings['referee_credits'];

        $totalReferrerCredits = $totalReferrals * $referrerCredits;
        $totalRefereeCredits = $totalReferrals * $refereeCredits;

        return ApiResponse::success([
            'total_codes' => $totalCodes,
            'total_referrals' => $totalReferrals,
            'total_referrer_credits' => $totalReferrerCredits,
            'total_referrer_credits_formatted' => CurrencyHelper::formatAmount($totalReferrerCredits),
            'total_referee_credits' => $totalRefereeCredits,
            'total_referee_credits_formatted' => CurrencyHelper::formatAmount($totalRefereeCredits),
            'settings' => $settings,
        ], 'Statistics retrieved successfully', 200);
    }
}
