<?php

function destination_ensure_photo_column(mysqli $conn): void
{
    $columnExists = false;
    $columns = $conn->query("SHOW COLUMNS FROM destinations LIKE 'photo_path'");

    if ($columns && $columns->num_rows > 0) {
        $columnExists = true;
    }

    if (!$columnExists) {
        $conn->query("ALTER TABLE destinations ADD COLUMN photo_path VARCHAR(255) NULL AFTER transport_tips");
    }
}

function destination_normalize_photo_path(?string $path): string
{
    return str_replace('\\', '/', trim((string) $path));
}

function destination_extract_path_candidate(string $value): string
{
    $candidate = trim($value);

    if ($candidate === '') {
        return '';
    }

    if (preg_match('/(?:src|href)=["\']([^"\']+)["\']/i', $candidate, $match) === 1) {
        return destination_normalize_photo_path($match[1]);
    }

    if (preg_match('/https?:\/\/[^\s"\']+/i', $candidate, $match) === 1) {
        return destination_normalize_photo_path($match[0]);
    }

    return destination_normalize_photo_path(strip_tags($candidate));
}

function destination_get_photo_public_path(?string $path): string
{
    $normalizedPath = destination_extract_path_candidate((string) $path);

    if ($normalizedPath === '') {
        return '';
    }

    if (filter_var($normalizedPath, FILTER_VALIDATE_URL)) {
        $urlPath = parse_url($normalizedPath, PHP_URL_PATH);

        if (is_string($urlPath) && preg_match('#/images/destination/[A-Za-z0-9._-]+$#', $urlPath) === 1) {
            return ltrim($urlPath, '/');
        }

        return $normalizedPath;
    }

    $normalizedPath = preg_replace('#^\./+#', '', $normalizedPath) ?? $normalizedPath;

    if (str_starts_with($normalizedPath, '/')) {
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if ($documentRoot !== '') {
            $absoluteFromRoot = rtrim($documentRoot, '/\\') . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath), '/\\');
            if (is_file($absoluteFromRoot)) {
                return $normalizedPath;
            }
        }
    }

    if (preg_match('#images/destination/[A-Za-z0-9._-]+#', $normalizedPath, $matches) === 1) {
        $normalizedPath = $matches[0];
    }

    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);

    return is_file($absolutePath) ? $normalizedPath : '';
}

function destination_is_external_photo_path(?string $path): bool
{
    return filter_var(destination_normalize_photo_path($path), FILTER_VALIDATE_URL) !== false;
}

function destination_store_uploaded_photo(array $file): array
{
    $uploadError = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        return [
            'status' => 'empty',
            'path' => '',
            'message' => '',
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'status' => 'error',
            'path' => '',
            'message' => 'Unable to upload the photo right now. Please try again.',
        ];
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return [
            'status' => 'error',
            'path' => '',
            'message' => 'Please upload an image smaller than 5 MB.',
        ];
    }

    $tmpName = $file['tmp_name'] ?? '';

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return [
            'status' => 'error',
            'path' => '',
            'message' => 'The selected photo could not be processed.',
        ];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpName);
    $allowedTypes = [
        'image/gif' => 'gif',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedTypes[$mimeType])) {
        return [
            'status' => 'error',
            'path' => '',
            'message' => 'Please upload a JPG, PNG, WEBP, or GIF image.',
        ];
    }

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'destination';

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        return [
            'status' => 'error',
            'path' => '',
            'message' => 'The destination image folder could not be created.',
        ];
    }

    $filename = 'destination_' . bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
    $relativePath = 'images/destination/' . $filename;
    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'destination' . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpName, $absolutePath)) {
        return [
            'status' => 'error',
            'path' => '',
            'message' => 'The uploaded photo could not be saved.',
        ];
    }

    return [
        'status' => 'uploaded',
        'path' => $relativePath,
        'message' => '',
    ];
}

function destination_delete_photo_file(?string $path): void
{
    $normalizedPath = destination_normalize_photo_path($path);

    if (filter_var($normalizedPath, FILTER_VALIDATE_URL)) {
        return;
    }

    if ($normalizedPath === '' || !preg_match('#^images/destination/[A-Za-z0-9._-]+$#', $normalizedPath)) {
        return;
    }

    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);

    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function destination_placeholder_initials(string $name): string
{
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';

    foreach ($words as $word) {
        if ($word === '') {
            continue;
        }

        $initials .= strtoupper(substr($word, 0, 1));

        if (strlen($initials) === 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'E';
}
