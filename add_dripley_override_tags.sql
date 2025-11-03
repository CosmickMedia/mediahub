-- Add dripley_override_tags column to stores table
-- This field allows each store to override the default Dripley contact tags

ALTER TABLE `stores`
ADD COLUMN `dripley_override_tags` VARCHAR(255) DEFAULT NULL AFTER `marketing_report_url`;
