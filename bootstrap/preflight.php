<?php
// bootstrap/preflight.php - Pre-flight checks for DigitalOcean deployment

$errors = [];
$warnings = [];

// Check PHP version
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    $errors[] = 'PHP 8.2.0 or higher is required. Current version: ' . PHP_VERSION;
}

// Check required extensions
$required_extensions = ['pdo', 'pdo_pgsql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'curl'];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $errors[] = "Required PHP extension '$ext' is not loaded";
    }
}

// Check environment variables
$required_env = ['APP_KEY', 'DATABASE_URL'];
foreach ($required_env as $env) {
    if (empty($_ENV[$env] ?? $_SERVER[$env] ?? getenv($env))) {
        $errors[] = "Required environment variable '$env' is not set";
    }
}

// Check directory permissions
$required_dirs = [
    'storage/app',
    'storage/framework',
    'storage/logs',
    'bootstrap/cache'
];

foreach ($required_dirs as $dir) {
    $full_path = __DIR__ . '/../' . $dir;
    if (!is_dir($full_path)) {
        if (!mkdir($full_path, 0755, true)) {
            $errors[] = "Cannot create directory: $dir";
        }
    } elseif (!is_writable($full_path)) {
        $warnings[] = "Directory is not writable: $dir";
    }
}

// Check database connection
if (!empty($_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? getenv('DATABASE_URL'))) {
    try {
        $db_url = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? getenv('DATABASE_URL');
        $parsed = parse_url($db_url);

        $host = $parsed['host'] ?? 'localhost';
        $port = $parsed['port'] ?? 5432;
        $database = ltrim($parsed['path'] ?? '', '/');
        $username = $parsed['user'] ?? '';
        $password = $parsed['pass'] ?? '';

        $dsn = "pgsql:host=$host;port=$port;dbname=$database;sslmode=require";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_TIMEOUT => 10,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Test query
        $pdo->query('SELECT 1');

    } catch (PDOException $e) {
        $warnings[] = 'Database connection test failed: ' . $e->getMessage();
    } catch (Exception $e) {
        $warnings[] = 'Database configuration error: ' . $e->getMessage();
    }
}

// Output results
if (!empty($errors)) {
    echo "❌ PREFLIGHT ERRORS:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    exit(1);
}

if (!empty($warnings)) {
    echo "⚠️  PREFLIGHT WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "  - $warning\n";
    }
}

echo "✅ Preflight checks completed\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . "s\n";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "\n";
echo "Post Max Size: " . ini_get('post_max_size') . "\n";

return true;
