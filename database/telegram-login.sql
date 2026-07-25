-- Run once before enabling Telegram one-click login on an existing installation.
-- The first query must return no rows. Resolve any duplicate telegram_id values before continuing.
SELECT `telegram_id`, COUNT(*) AS `count`
FROM `v2_user`
WHERE `telegram_id` IS NOT NULL
GROUP BY `telegram_id`
HAVING COUNT(*) > 1;

SET @telegram_login_index = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v2_user' AND INDEX_NAME = 'telegram_id'
);
SET @telegram_login_sql = IF(
    @telegram_login_index = 0,
    'ALTER TABLE `v2_user` ADD UNIQUE KEY `telegram_id` (`telegram_id`)',
    'SELECT 1'
);
PREPARE telegram_login_statement FROM @telegram_login_sql;
EXECUTE telegram_login_statement;
DEALLOCATE PREPARE telegram_login_statement;
