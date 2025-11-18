-- Plateful MySQL schema
-- Run this script in phpMyAdmin or the MySQL CLI to initialize the database.

CREATE DATABASE IF NOT EXISTS `plateful` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `plateful`;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(160) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('customer', 'caterer', 'admin') NOT NULL DEFAULT 'customer',
    `status` ENUM('active', 'pending', 'disabled') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ux_users_email` (`email`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `admins` (
    `user_id` INT UNSIGNED NOT NULL,
    `position_title` VARCHAR(120) DEFAULT NULL,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `fk_admins_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `caterer_profiles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `business_name` VARCHAR(180) NOT NULL,
    `description` TEXT,
    `location` VARCHAR(180) DEFAULT NULL,
    `service_area` VARCHAR(180) DEFAULT NULL,
    `cuisine_specialties` TEXT,
    `event_types` TEXT,
    `average_price` DECIMAL(10,2) DEFAULT NULL,
    `logo_path` VARCHAR(255) DEFAULT NULL,
    `cover_photo_path` VARCHAR(255) DEFAULT NULL,
    `approval_status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `availability_status` ENUM('available', 'unavailable') NOT NULL DEFAULT 'available',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ux_caterer_profiles_user_id` (`user_id`),
    CONSTRAINT `fk_caterer_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `customer_profiles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `phone` VARCHAR(32) DEFAULT NULL,
    `preferred_location` VARCHAR(160) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ux_customer_profiles_user_id` (`user_id`),
    CONSTRAINT `fk_customer_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `cuisine_types` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ux_cuisine_types_name` (`name`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `event_types` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ux_event_types_name` (`name`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `caterer_cuisine_types` (
    `caterer_profile_id` INT UNSIGNED NOT NULL,
    `cuisine_type_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`caterer_profile_id`, `cuisine_type_id`),
    CONSTRAINT `fk_caterer_cuisine_profile` FOREIGN KEY (`caterer_profile_id`) REFERENCES `caterer_profiles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_caterer_cuisine_type` FOREIGN KEY (`cuisine_type_id`) REFERENCES `cuisine_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `caterer_event_types` (
    `caterer_profile_id` INT UNSIGNED NOT NULL,
    `event_type_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`caterer_profile_id`, `event_type_id`),
    CONSTRAINT `fk_caterer_event_profile` FOREIGN KEY (`caterer_profile_id`) REFERENCES `caterer_profiles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_caterer_event_type` FOREIGN KEY (`event_type_id`) REFERENCES `event_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `packages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `caterer_profile_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(180) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `deposit_percentage` DECIMAL(5,2) DEFAULT NULL,
    `package_type` ENUM('food', 'services', 'full') NOT NULL DEFAULT 'full',
    `description` TEXT,
    `inclusions` TEXT,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_packages_caterer` (`caterer_profile_id`),
    CONSTRAINT `fk_packages_caterer_profile` FOREIGN KEY (`caterer_profile_id`) REFERENCES `caterer_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `package_photos` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `package_id` INT UNSIGNED NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_package_photos_package` (`package_id`),
    CONSTRAINT `fk_package_photos_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `package_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `package_id` INT UNSIGNED NOT NULL,
    `item_type` ENUM('maincourse', 'service', 'addon') NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `price` DECIMAL(10,2) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_package_items_package` (`package_id`),
    CONSTRAINT `fk_package_items_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `caterer_gallery_photos` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `caterer_profile_id` INT UNSIGNED NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_caterer_gallery_profile` (`caterer_profile_id`),
    CONSTRAINT `fk_caterer_gallery_profile` FOREIGN KEY (`caterer_profile_id`) REFERENCES `caterer_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `caterer_payment_channels` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `caterer_profile_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `details` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_caterer_payment_channels_profile` (`caterer_profile_id`),
    CONSTRAINT `fk_caterer_payment_channels_profile` FOREIGN KEY (`caterer_profile_id`) REFERENCES `caterer_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `caterer_menu_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `caterer_profile_id` INT UNSIGNED NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_caterer_menu_items_profile` (`caterer_profile_id`),
    CONSTRAINT `fk_caterer_menu_items_profile` FOREIGN KEY (`caterer_profile_id`) REFERENCES `caterer_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `caterer_subscriptions` (
    `user_id` INT UNSIGNED NOT NULL,
    `plan` ENUM('monthly', 'yearly') NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `started_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `renews_at` DATE NOT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    KEY `idx_caterer_subscriptions_plan` (`plan`),
    CONSTRAINT `fk_caterer_subscriptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `bookings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` INT UNSIGNED NOT NULL,
    `caterer_profile_id` INT UNSIGNED NOT NULL,
    `package_id` INT UNSIGNED DEFAULT NULL,
    `event_type_id` INT UNSIGNED DEFAULT NULL,
    `cuisine_type_id` INT UNSIGNED DEFAULT NULL,
    `guest_count` INT UNSIGNED NOT NULL,
    `event_date` DATE NOT NULL,
    `event_time` TIME DEFAULT NULL,
    `custom_request` TEXT,
    `status` ENUM('pending', 'awaiting_payment', 'confirmed', 'declined', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    `notes` TEXT,
    `responded_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_bookings_customer` (`customer_id`),
    KEY `idx_bookings_caterer` (`caterer_profile_id`),
    KEY `idx_bookings_package` (`package_id`),
    CONSTRAINT `fk_bookings_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bookings_caterer_profile` FOREIGN KEY (`caterer_profile_id`) REFERENCES `caterer_profiles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bookings_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_bookings_event_type` FOREIGN KEY (`event_type_id`) REFERENCES `event_types` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_bookings_cuisine_type` FOREIGN KEY (`cuisine_type_id`) REFERENCES `cuisine_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `booking_status_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id` INT UNSIGNED NOT NULL,
    `old_status` ENUM('pending', 'awaiting_payment', 'confirmed', 'declined', 'completed', 'cancelled') DEFAULT NULL,
    `new_status` ENUM('pending', 'awaiting_payment', 'confirmed', 'declined', 'completed', 'cancelled') NOT NULL,
    `changed_by` INT UNSIGNED DEFAULT NULL,
    `message` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_booking_status_logs_booking` (`booking_id`),
    CONSTRAINT `fk_booking_status_logs_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_booking_status_logs_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `booking_payments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id` INT UNSIGNED NOT NULL,
    `customer_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('full', 'deposit', 'cod') NOT NULL,
    `payment_channel` VARCHAR(80) DEFAULT NULL,
    `status` ENUM('pending', 'completed') NOT NULL DEFAULT 'completed',
    `reference` VARCHAR(120) DEFAULT NULL,
    `proof_path` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_booking_payments_booking` (`booking_id`),
    CONSTRAINT `fk_booking_payments_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_booking_payments_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `reviews` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id` INT UNSIGNED NOT NULL,
    `customer_id` INT UNSIGNED NOT NULL,
    `caterer_profile_id` INT UNSIGNED NOT NULL,
    `rating` TINYINT UNSIGNED NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
    `comment` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ux_reviews_booking` (`booking_id`),
    CONSTRAINT `fk_reviews_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reviews_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reviews_caterer_profile` FOREIGN KEY (`caterer_profile_id`) REFERENCES `caterer_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `type` VARCHAR(80) NOT NULL,
    `title` VARCHAR(160) NOT NULL,
    `message` TEXT,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notifications_user` (`user_id`),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `caterer_notification_preferences` (
    `user_id` INT UNSIGNED NOT NULL,
    `email_new_request` TINYINT(1) NOT NULL DEFAULT 1,
    `client_updates` TINYINT(1) NOT NULL DEFAULT 1,
    `weekly_review_digest` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `fk_caterer_notification_preferences_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Preload seed data for admins and categories (adjust as needed)
INSERT INTO `cuisine_types` (`name`) VALUES
    ('Filipino'),
    ('Italian'),
    ('Japanese'),
    ('Mediterranean'),
    ('Vegan')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `event_types` (`name`) VALUES
    ('Wedding'),
    ('Birthday'),
    ('Corporate'),
    ('Holiday Party'),
    ('Debut')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
