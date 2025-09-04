<?php
// bootstrap/build-preflight.php - Build-time checks only (no database required)

echo "🔍 Running build-time preflight checks...\n";

$errors = [];
$warnings = [];

// Check PHP version
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    $errors[] = 'PHP 8.2.0 or higher is required. Current version: ' . PHP_VERSION;
} else {
    echo "✅ PHP Version: " . PHP_VERSION . "\n";
}

// Check required extensions (build-time only)
$required_extensions = ['mbstring', 'openssl', 'tokenizer', 'xml', 'curl'];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $errors[] = "Required PHP extension '$ext' is not loaded";
    }
}

if (empty($errors)) {
    echo "✅ All required PHP extensions are loaded\n";
}

// Check build-time environment variables
$app_key = $_ENV['APP_KEY'] ?? $_SERVER['APP_KEY'] ?? getenv('APP_KEY');
if (empty($app_key)) {
    $errors[] = "Required environment variable 'APP_KEY' is not set";
} else {
    echo "✅ APP_KEY is configured\n";
}

// Check directory structure and create if needed
$required_dirs = [
    'storage/app',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache'
];

foreach ($required_dirs as $dir) {
    $full_path = __DIR__ . '/../' . $dir;
    if (!is_dir($full_path)) {
        if (mkdir($full_path, 0755, true)) {
            echo "✅ Created directory: $dir\n";
        } else {
            $errors[] = "Cannot create directory: $dir";
        }
    }
}

// Output results
if (!empty($errors)) {
    echo "❌ BUILD PREFLIGHT ERRORS:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    exit(1);
}

if (!empty($warnings)) {
    echo "⚠️  BUILD WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "  - $warning\n";
    }
}

echo "✅ Build preflight checks completed successfully\n";
echo "📦 Ready for build phase\n";

return true;
