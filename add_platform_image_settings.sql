-- Create social_network_image_settings table for platform-specific image requirements
CREATE TABLE IF NOT EXISTS `social_network_image_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `network_name` VARCHAR(50) NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Enable auto-crop for this platform',
  `aspect_ratio` VARCHAR(20) NOT NULL DEFAULT '1:1' COMMENT 'e.g., 1:1, 16:9, 4:5',
  `target_width` INT(11) NOT NULL DEFAULT 1080 COMMENT 'Target width in pixels',
  `target_height` INT(11) NOT NULL DEFAULT 1080 COMMENT 'Target height in pixels',
  `min_width` INT(11) DEFAULT 320 COMMENT 'Minimum width in pixels',
  `max_file_size_kb` INT(11) NOT NULL DEFAULT 5120 COMMENT 'Max file size in KB',
  PRIMARY KEY (`id`),
  UNIQUE KEY `network_name` (`network_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Platform-specific image crop and size requirements';

-- Insert default settings for major social platforms
-- These can be overridden via admin settings
INSERT INTO `social_network_image_settings` (`network_name`, `enabled`, `aspect_ratio`, `target_width`, `target_height`, `min_width`, `max_file_size_kb`) VALUES
('instagram', 1, '1:1', 1080, 1080, 320, 5120),
('facebook', 1, '1.91:1', 1200, 630, 200, 10240),
('linkedin', 1, '1.91:1', 1200, 627, 200, 10240),
('x', 1, '1:1', 1080, 1080, 200, 5120),
('threads', 1, '9:16', 1080, 1920, 320, 10240),
('twitter', 1, '1:1', 1080, 1080, 200, 5120),
('pinterest', 1, '2:3', 1000, 1500, 200, 20480)
ON DUPLICATE KEY UPDATE
  `aspect_ratio` = VALUES(`aspect_ratio`),
  `target_width` = VALUES(`target_width`),
  `target_height` = VALUES(`target_height`),
  `min_width` = VALUES(`min_width`),
  `max_file_size_kb` = VALUES(`max_file_size_kb`);
