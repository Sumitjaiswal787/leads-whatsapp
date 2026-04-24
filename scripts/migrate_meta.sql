-- Migration to add Meta Lead Ads support
ALTER TABLE users ADD COLUMN fb_page_id VARCHAR(100) NULL AFTER tenant_id;
ALTER TABLE users ADD COLUMN fb_access_token TEXT NULL AFTER fb_page_id;

ALTER TABLE leads ADD COLUMN project_name VARCHAR(100) NULL AFTER staff_id;
ALTER TABLE leads ADD COLUMN source ENUM('whatsapp', 'meta') DEFAULT 'whatsapp' AFTER project_name;
ALTER TABLE leads ADD COLUMN jid VARCHAR(100) NULL AFTER number;

-- Ensure assignment queue uses last_staff_id consistently (it was last_staff_index in schema.sql but callback.php uses last_staff_id)
-- Let's check schema.sql vs callback.php
-- schema.sql: last_staff_index
-- callback.php: last_staff_id
-- We should standardize to last_staff_id if that's what the code uses.
ALTER TABLE assign_queue CHANGE COLUMN last_staff_index last_staff_id INT DEFAULT 0;
