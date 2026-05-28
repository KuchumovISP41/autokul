<?php
// includes/config.php — application configuration for local development and Railway.

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

if (class_exists(\Dotenv\Dotenv::class) && file_exists(__DIR__ . '/../.env')) {
    \Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();
}

/**
 * Read an environment value from $_ENV, $_SERVER or getenv().
 */
function envValue(string $key, $default = null) {
    if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }
    $value = getenv($key);
    return ($value !== false && $value !== '') ? $value : $default;
}

/**
 * Parse Railway DATABASE_URL / MYSQL_URL variables when they are available.
 */
function parseDatabaseUrl(): array {
    $url = envValue('DATABASE_URL') ?: envValue('MYSQL_URL');
    if (!$url) {
        return [];
    }

    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return [];
    }

    return [
        'host' => $parts['host'] ?? null,
        'port' => $parts['port'] ?? 3306,
        'name' => isset($parts['path']) ? ltrim($parts['path'], '/') : null,
        'user' => isset($parts['user']) ? urldecode($parts['user']) : null,
        'pass' => isset($parts['pass']) ? urldecode($parts['pass']) : null,
    ];
}

$dbUrl = parseDatabaseUrl();

define('APP_ENV', envValue('APP_ENV', 'production'));
define('APP_DEBUG', filter_var(envValue('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN));

define('DB_HOST', envValue('DB_HOST', $dbUrl['host'] ?? '127.0.0.1'));
define('DB_PORT', (int) envValue('DB_PORT', $dbUrl['port'] ?? 3306));
define('DB_NAME', envValue('DB_NAME', $dbUrl['name'] ?? 'autokul_sto'));
define('DB_USER', envValue('DB_USER', $dbUrl['user'] ?? 'root'));
define('DB_PASS', envValue('DB_PASS', $dbUrl['pass'] ?? ''));
define('DB_CHARSET', envValue('DB_CHARSET', 'utf8mb4'));

define('CLOUDINARY_CLOUD_NAME', envValue('CLOUDINARY_CLOUD_NAME', ''));
define('CLOUDINARY_API_KEY', envValue('CLOUDINARY_API_KEY', ''));
define('CLOUDINARY_API_SECRET', envValue('CLOUDINARY_API_SECRET', ''));
define('CLOUDINARY_FOLDER', trim(envValue('CLOUDINARY_FOLDER', 'autokul_sto'), '/'));
define('UPLOAD_MAX_SIZE', (int) envValue('UPLOAD_MAX_SIZE', 5 * 1024 * 1024));

/**
 * True when Cloudinary credentials are present and the SDK is installed.
 */
function isCloudinaryConfigured(): bool {
    return CLOUDINARY_CLOUD_NAME !== ''
        && CLOUDINARY_API_KEY !== ''
        && CLOUDINARY_API_SECRET !== ''
        && class_exists(\Cloudinary\Cloudinary::class);
}

/**
 * Cloudinary SDK client singleton.
 */
function getCloudinary() {
    static $cloudinary = null;

    if (!isCloudinaryConfigured()) {
        return null;
    }

    if ($cloudinary === null) {
        $cloudinary = new \Cloudinary\Cloudinary([
            'cloud' => [
                'cloud_name' => CLOUDINARY_CLOUD_NAME,
                'api_key' => CLOUDINARY_API_KEY,
                'api_secret' => CLOUDINARY_API_SECRET,
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
    }

    return $cloudinary;
}

/**
 * Upload a local temporary file to Cloudinary and return its public_id.
 */
function uploadImageToCloudinary(string $localFilePath, string $folder = 'uploads'): ?string {
    $cloudinary = getCloudinary();
    if (!$cloudinary) {
        return null;
    }

    $targetFolder = trim(CLOUDINARY_FOLDER . '/' . trim($folder, '/'), '/');
    $result = $cloudinary->uploadApi()->upload($localFilePath, [
        'folder' => $targetFolder,
        'resource_type' => 'image',
        'quality' => 'auto:good',
        'fetch_format' => 'auto',
    ]);

    return $result['public_id'] ?? null;
}

/**
 * Delete an image. Supports Cloudinary public_id and old local upload paths.
 */
function deleteStoredImage(?string $storedValue): bool {
    if (empty($storedValue)) {
        return false;
    }

    $normalized = ltrim($storedValue, '/');
    $localPath = __DIR__ . '/../' . $normalized;
    if (str_starts_with($normalized, 'uploads/') && file_exists($localPath)) {
        return @unlink($localPath);
    }

    if (isCloudinaryConfigured() && !preg_match('#^https?://#i', $storedValue)) {
        try {
            $result = getCloudinary()->uploadApi()->destroy($storedValue, ['resource_type' => 'image']);
            return in_array(($result['result'] ?? null), ['ok', 'not found'], true);
        } catch (Throwable $e) {
            error_log('Cloudinary delete error: ' . $e->getMessage());
        }
    }

    return false;
}

/**
 * Build an optimized Cloudinary image URL from public_id.
 */
function getCloudinaryImageUrl(?string $publicId, int $width = 800, ?int $height = null, string $crop = 'fill'): string {
    if (empty($publicId) || CLOUDINARY_CLOUD_NAME === '') {
        return '';
    }

    $transform = 'f_auto,q_auto,w_' . max(1, $width);
    if ($height !== null) {
        $transform .= ',h_' . max(1, $height) . ',c_' . $crop;
    } else {
        $transform .= ',c_limit';
    }

    $encodedPublicId = implode('/', array_map('rawurlencode', explode('/', ltrim($publicId, '/'))));
    return 'https://res.cloudinary.com/' . rawurlencode(CLOUDINARY_CLOUD_NAME) . '/image/upload/' . $transform . '/' . $encodedPublicId;
}

/**
 * Resolve an image value saved in DB: absolute URL, old local path, or Cloudinary public_id.
 */
function getStoredImageUrl(?string $storedValue, string $fallback, int $width = 800, ?int $height = null, string $crop = 'fill'): string {
    if (!empty($storedValue)) {
        if (preg_match('#^https?://#i', $storedValue)) {
            return $storedValue;
        }

        $normalized = ltrim($storedValue, '/');
        if (str_starts_with($normalized, 'uploads/') && file_exists(__DIR__ . '/../' . $normalized)) {
            return '/' . $normalized;
        }

        $cloudinaryUrl = getCloudinaryImageUrl($storedValue, $width, $height, $crop);
        if ($cloudinaryUrl !== '') {
            return $cloudinaryUrl;
        }
    }

    return $fallback;
}

/**
 * Shared PDO connection factory.
 */
function getDBConnection(): PDO {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        error_log('DB connection error: ' . $e->getMessage());
        if (APP_DEBUG) {
            die('Ошибка подключения к базе данных: ' . htmlspecialchars($e->getMessage()));
        }
        die('Ошибка подключения к базе данных');
    }
}
