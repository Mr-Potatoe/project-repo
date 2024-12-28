ALTER TABLE `projects` 
ADD COLUMN `database_name` varchar(64) DEFAULT NULL 
AFTER `description`;
