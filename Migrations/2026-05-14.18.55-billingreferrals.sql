-- Billing Referrals Plugin Migration
-- Creates tables for the referral system

-- Table for referral codes (each user can have one)
CREATE TABLE IF NOT EXISTS `featherpanel_billingreferrals_codes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `code` VARCHAR(20) NOT NULL,
    `uses` INT(11) NOT NULL DEFAULT 0,
    `max_uses` INT(11) NULL DEFAULT NULL,
    `expires_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `billingreferrals_codes_code_unique` (`code`),
    UNIQUE KEY `billingreferrals_codes_user_id_unique` (`user_id`),
    KEY `billingreferrals_codes_user_id_foreign` (`user_id`),
    KEY `billingreferrals_codes_expires_at_index` (`expires_at`),
    CONSTRAINT `billingreferrals_codes_user_id_foreign`
        FOREIGN KEY (`user_id`)
        REFERENCES `featherpanel_users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table for tracking who used which referral code
CREATE TABLE IF NOT EXISTS `featherpanel_billingreferrals_usage` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `code_id` INT(11) NOT NULL,
    `referred_user_id` INT(11) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `billingreferrals_usage_code_user_unique` (`code_id`, `referred_user_id`),
    UNIQUE KEY `billingreferrals_usage_referred_user_unique` (`referred_user_id`),
    KEY `billingreferrals_usage_code_id_foreign` (`code_id`),
    KEY `billingreferrals_usage_referred_user_id_foreign` (`referred_user_id`),
    CONSTRAINT `billingreferrals_usage_code_id_foreign`
        FOREIGN KEY (`code_id`)
        REFERENCES `featherpanel_billingreferrals_codes` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `billingreferrals_usage_referred_user_id_foreign`
        FOREIGN KEY (`referred_user_id`)
        REFERENCES `featherpanel_users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
