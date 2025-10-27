-- Add enabled column to social_networks table
ALTER TABLE `social_networks`
ADD COLUMN `enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `color`;

-- Set default values - enable all existing networks
UPDATE `social_networks` SET `enabled` = 1;
