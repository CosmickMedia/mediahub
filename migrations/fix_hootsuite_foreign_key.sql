-- Migration: Fix Hootsuite Foreign Key Constraint
-- Date: 2025-11-03
-- Issue: Foreign key constraint prevents profile refresh when posts reference profiles
-- Solution: Add CASCADE DELETE and CASCADE UPDATE to allow safe profile management

USE cmuploader;

-- Step 1: Drop the existing foreign key constraint
ALTER TABLE `hootsuite_posts`
  DROP FOREIGN KEY `fk_hootsuite_profile`;

-- Step 2: Recreate the constraint with CASCADE behavior
-- ON DELETE CASCADE: When a profile is deleted, automatically delete related posts
-- ON UPDATE CASCADE: When a profile ID changes, automatically update related posts
ALTER TABLE `hootsuite_posts`
  ADD CONSTRAINT `fk_hootsuite_profile`
  FOREIGN KEY (`social_profile_id`)
  REFERENCES `hootsuite_profiles` (`id`)
  ON DELETE CASCADE
  ON UPDATE CASCADE;

-- Verification: Check the new constraint
SELECT
    rc.CONSTRAINT_NAME,
    rc.DELETE_RULE,
    rc.UPDATE_RULE,
    kcu.TABLE_NAME,
    kcu.COLUMN_NAME,
    kcu.REFERENCED_TABLE_NAME,
    kcu.REFERENCED_COLUMN_NAME
FROM information_schema.REFERENTIAL_CONSTRAINTS rc
JOIN information_schema.KEY_COLUMN_USAGE kcu
    ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
    AND rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
WHERE rc.CONSTRAINT_NAME = 'fk_hootsuite_profile'
  AND rc.CONSTRAINT_SCHEMA = 'cmuploader';

-- Expected output:
-- fk_hootsuite_profile | CASCADE | CASCADE | hootsuite_posts | social_profile_id | hootsuite_profiles | id

SELECT 'Migration completed successfully!' as Status;
