<?php

/**
 * Standalone helper (run LOCALLY, never uploaded) that prints the SQL needed to
 * bootstrap the first manager account on a host without SSH or Artisan.
 *
 * Usage:
 *   php generate-manager-sql.php "اسم المدير" manager  manager@example.com  "StrongPassword123"
 *
 * The produced hash uses PASSWORD_BCRYPT (cost 12), which Laravel's Hash facade
 * verifies without any extra configuration.
 */
$arguments = array_slice($argv, 1);
if (count($arguments) < 4) {
    fwrite(STDERR, "الاستخدام: php generate-manager-sql.php \"الاسم\" username email \"كلمة المرور\"\n");
    exit(1);
}

[$name, $username, $email, $password] = $arguments;
$username = strtolower(trim($username));
$email = strtolower(trim($email));

if (! preg_match('/^[a-z0-9._-]{3,80}$/', $username)) {
    fwrite(STDERR, "اسم المستخدم يجب أن يكون 3-80 حرفاً إنجليزياً أو رقماً أو . _ -\n");
    exit(1);
}
if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "البريد الإلكتروني غير صالح.\n");
    exit(1);
}
if (strlen($password) < 8
    || preg_match('/[A-Z]/', $password) !== 1
    || preg_match('/[a-z]/', $password) !== 1
    || preg_match('/[0-9]/', $password) !== 1
    || preg_match('/[^A-Za-z0-9]/', $password) !== 1) {
    fwrite(STDERR, "كلمة المرور يجب أن تكون 8 أحرف على الأقل وتشمل حرفاً كبيراً وصغيراً ورقماً ورمزاً.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$quote = static function (string $value): string {
    return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
};

$name = $quote($name);
$username = $quote($username);
$emailSql = $email === '' ? 'NULL' : $quote($email);
$hashSql = $quote($hash);

echo <<<SQL
-- ---------------------------------------------------------------------------
-- Bootstrap the first manager account (one time only).
-- Run against the asmaa-workshop database after importing 01-schema.sql and
-- 02-seed.sql. Change these credentials immediately after first login.
-- ---------------------------------------------------------------------------
SET NAMES utf8mb4;

INSERT INTO `users` (`name`, `username`, `email`, `password`, `is_active`, `created_at`, `updated_at`)
VALUES ({$name}, {$username}, {$emailSql}, {$hashSql}, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());

SET @new_manager_id = LAST_INSERT_ID();

INSERT INTO `user_roles` (`user_id`, `role_id`, `created_at`, `updated_at`)
SELECT @new_manager_id, `id`, UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM `roles` WHERE `name` = 'manager';

INSERT INTO `audit_logs` (`user_id`, `action`, `subject_type`, `subject_id`, `before_values`, `after_values`, `reason`, `request_id`, `occurred_at`)
VALUES (NULL, 'bootstrap_manager_created', 'App\\\\Models\\\\User', @new_manager_id, NULL, JSON_OBJECT('username', {$username}), 'إنشاء حساب المدير الأول عبر SQL', NULL, UTC_TIMESTAMP());
SQL;

echo "\n";
