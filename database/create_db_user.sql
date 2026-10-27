-- Run as MySQL root after importing schema.sql
-- Least-privilege user for the PHP API only

CREATE USER IF NOT EXISTS 'holyface_api'@'localhost' IDENTIFIED BY 'change_me_strong_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON holyface_amgs.* TO 'holyface_api'@'localhost';
FLUSH PRIVILEGES;
