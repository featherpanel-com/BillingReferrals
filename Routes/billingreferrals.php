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

use App\App;
use App\Permissions;
use App\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;
use App\Addons\billingreferrals\Controllers\User\BillingReferralsController as UserController;
use App\Addons\billingreferrals\Controllers\Admin\BillingReferralsController as AdminController;

return function (RouteCollection $routes): void {
    // ==================== PUBLIC ROUTES (No Auth Required) ====================

    // Track referral visit (sets cookie)
    // This is a special route that doesn't require auth but can be accessed by anyone
    App::getInstance(true)->registerApiRoute(
        $routes,
        'billingreferrals-visit',
        '/api/billingreferrals/visit',
        function (Request $request) {
            return (new UserController())->trackVisit($request);
        },
        ['POST']
    );

    App::getInstance(true)->registerApiRoute(
        $routes,
        'billingreferrals-register-context',
        '/api/billingreferrals/register-context',
        function (Request $request) {
            return (new UserController())->getRegisterContext($request);
        },
        ['GET']
    );

    // ==================== USER ROUTES (Require Authentication) ====================

    // Get my referral code
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingreferrals-user-my-code',
        '/api/user/billingreferrals/my-code',
        function (Request $request) {
            return (new UserController())->getMyCode($request);
        },
        ['GET']
    );

    // Update my referral code
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingreferrals-user-my-code-update',
        '/api/user/billingreferrals/my-code',
        function (Request $request) {
            return (new UserController())->updateMyCode($request);
        },
        ['PATCH']
    );

    // Get my referrals (who signed up using my code)
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingreferrals-user-my-referrals',
        '/api/user/billingreferrals/my-referrals',
        function (Request $request) {
            return (new UserController())->getMyReferrals($request);
        },
        ['GET']
    );

    // Get my referral stats
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingreferrals-user-my-stats',
        '/api/user/billingreferrals/stats',
        function (Request $request) {
            return (new UserController())->getMyStats($request);
        },
        ['GET']
    );

    // ==================== ADMIN ROUTES (Require Admin Permission) ====================

    // Get settings
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingreferrals-admin-settings',
        '/api/admin/billingreferrals/settings',
        function (Request $request) {
            return (new AdminController())->getSettings($request);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Update settings
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingreferrals-admin-settings-update',
        '/api/admin/billingreferrals/settings',
        function (Request $request) {
            return (new AdminController())->updateSettings($request);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['PATCH', 'PUT']
    );

    // Get all referral codes
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingreferrals-admin-codes',
        '/api/admin/billingreferrals/codes',
        function (Request $request) {
            return (new AdminController())->getCodes($request);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Create a new referral code
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingreferrals-admin-codes-create',
        '/api/admin/billingreferrals/codes',
        function (Request $request) {
            return (new AdminController())->createCode($request);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['POST']
    );

    // Get code by ID
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingreferrals-admin-code',
        '/api/admin/billingreferrals/codes/{id}',
        function (Request $request, array $args) {
            $id = (int) ($args['id'] ?? 0);
            if (!$id) {
                return ApiResponse::error('Invalid code ID', 'INVALID_ID', 400);
            }

            return (new AdminController())->getCode($request, $id);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Update code
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingreferrals-admin-code-update',
        '/api/admin/billingreferrals/codes/{id}',
        function (Request $request, array $args) {
            $id = (int) ($args['id'] ?? 0);
            if (!$id) {
                return ApiResponse::error('Invalid code ID', 'INVALID_ID', 400);
            }

            return (new AdminController())->updateCode($request, $id);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['PATCH', 'PUT']
    );

    // Delete code
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingreferrals-admin-code-delete',
        '/api/admin/billingreferrals/codes/{id}',
        function (Request $request, array $args) {
            $id = (int) ($args['id'] ?? 0);
            if (!$id) {
                return ApiResponse::error('Invalid code ID', 'INVALID_ID', 400);
            }

            return (new AdminController())->deleteCode($request, $id);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['DELETE']
    );

    // Get code usage (who used this code)
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingreferrals-admin-code-usage',
        '/api/admin/billingreferrals/codes/{id}/usage',
        function (Request $request, array $args) {
            $id = (int) ($args['id'] ?? 0);
            if (!$id) {
                return ApiResponse::error('Invalid code ID', 'INVALID_ID', 400);
            }

            return (new AdminController())->getCodeUsage($request, $id);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Get referral statistics
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingreferrals-admin-stats',
        '/api/admin/billingreferrals/stats',
        function (Request $request) {
            return (new AdminController())->getStats($request);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );
};
