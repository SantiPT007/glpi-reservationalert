-- Plugin config: one row only (id=1), stores the warning window in minutes
CREATE TABLE IF NOT EXISTS `glpi_plugin_reservationalert_configs` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `warning_minutes` INT          NOT NULL DEFAULT 60,
    `global_enabled`  TINYINT(1)   NOT NULL DEFAULT 1,
    `date_mod`        TIMESTAMP     DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default config row so admins don't need to save before it works
INSERT IGNORE INTO `glpi_plugin_reservationalert_configs` (`id`, `warning_minutes`)
VALUES (1, 60);

-- Migrate existing installs: add global_enabled if the table predates this column
ALTER TABLE `glpi_plugin_reservationalert_configs`
    ADD COLUMN IF NOT EXISTS `global_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `warning_minutes`;

-- Sent notification log — prevents duplicate alerts per reservation per user
CREATE TABLE IF NOT EXISTS `glpi_plugin_reservationalert_notifications` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reservations_id`  INT UNSIGNED NOT NULL,
    `users_id`         INT UNSIGNED NOT NULL,
    `is_read`          TINYINT(1)   NOT NULL DEFAULT 0,
    `date_creation`    TIMESTAMP     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_reservation_user` (`reservations_id`, `users_id`),
    KEY `users_id`     (`users_id`),
    KEY `is_read`      (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
