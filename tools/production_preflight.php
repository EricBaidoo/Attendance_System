<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden\n");
}

$root = dirname(__DIR__);

function check(bool $ok, string $label, string $hint = ''): void {
    $status = $ok ? 'PASS' : 'FAIL';
    echo '[' . $status . '] ' . $label;
    if (!$ok && $hint !== '') {
        echo ' -> ' . $hint;
    }
    echo PHP_EOL;
}

function fileContains(string $path, string $needle): bool {
    if (!is_file($path)) {
        return false;
    }
    $content = (string)file_get_contents($path);
    return strpos($content, $needle) !== false;
}

echo "Production Preflight" . PHP_EOL;
echo "====================" . PHP_EOL;

$required_env = [
    'BULKSMSGH_API_KEY',
    'SMS_PROVIDER',
    'SMS_UNIT_COST',
    'SMS_CURRENCY',
];

foreach ($required_env as $name) {
    $value = trim((string)getenv($name));
    check($value !== '', 'Env var set: ' . $name, 'Set this value in your server environment.');
}

$maintenance_flag = trim((string)getenv('APP_ENABLE_MAINTENANCE_TOOLS'));
check($maintenance_flag !== '1', 'Maintenance tools disabled', 'Unset APP_ENABLE_MAINTENANCE_TOOLS in production.');

$utility_files = [
    'check_sms_config.php',
    'check_tither_phone.php',
    'check_tither_phones.php',
    'run_db_updates.php',
    'test_bulksms.php',
    'test_phone_norm.php',
];

foreach ($utility_files as $file) {
    $path = $root . DIRECTORY_SEPARATOR . $file;
    check(
        fileContains($path, "APP_ENABLE_MAINTENANCE_TOOLS") && fileContains($path, 'PHP_SAPI !== \'cli\''),
        'Utility guard present: ' . $file,
        'Add CLI + APP_ENABLE_MAINTENANCE_TOOLS guard.'
    );
}

$password_reset_path = $root . DIRECTORY_SEPARATOR . 'password_reset.php';
check(
    fileContains($password_reset_path, 'This utility is disabled'),
    'password_reset.php disabled',
    'Disable this endpoint before go-live.'
);

$htaccess_path = $root . DIRECTORY_SEPARATOR . '.htaccess';
check(
    fileContains($htaccess_path, 'FilesMatch') && fileContains($htaccess_path, 'Require all denied'),
    'Apache deny rules present (.htaccess)',
    'Create .htaccess deny rules for utility scripts.'
);

echo PHP_EOL . 'Done.' . PHP_EOL;
