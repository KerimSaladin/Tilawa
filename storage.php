<?php
/**
 * storage.php — Supabase Storage abstraction (curl, no SDK required)
 *
 * Env vars used:
 *   R2_KEY        = Supabase service_role key (used as Bearer token)
 *   R2_BUCKET     = Supabase bucket name (e.g. tilawa-uploads)
 *   R2_ENDPOINT   = https://<project>.supabase.co/storage/v1
 *   R2_PUBLIC_URL = https://<project>.supabase.co/storage/v1/object/public/<bucket>
 *   USE_R2        = true when above vars are set (auto-set in config.php)
 */

require_once __DIR__ . '/config.php';

function _supabase_upload(string $tmp_path, string $filename, string $mime): bool {
    $url = rtrim(R2_ENDPOINT, '/') . '/object/' . R2_BUCKET . '/' . $filename;
    $fp  = fopen($tmp_path, 'rb');
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_PUT            => true,
        CURLOPT_INFILE         => $fp,
        CURLOPT_INFILESIZE     => filesize($tmp_path),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . R2_KEY,
            'Content-Type: ' . $mime,
            'x-upsert: true',
        ],
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    return $code >= 200 && $code < 300;
}

function _supabase_upload_raw(string $data, string $filename, string $mime): bool {
    $url = rtrim(R2_ENDPOINT, '/') . '/object/' . R2_BUCKET . '/' . $filename;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . R2_KEY,
            'Content-Type: ' . $mime,
            'x-upsert: true',
        ],
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}

function _supabase_delete(string $filename): void {
    $url = rtrim(R2_ENDPOINT, '/') . '/object/' . R2_BUCKET . '/' . $filename;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . R2_KEY,
        ],
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function upload_file(array $file, string $prefix, int $user_id): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'خطأ في الرفع: ' . $file['error']];
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'حجم الملف أكبر من الحد المسموح (100MB)'];
    }

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'bin';
    $filename = $prefix . '_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $mime     = $file['type'] ?: 'application/octet-stream';

    if (USE_R2) {
        $ok = _supabase_upload($file['tmp_name'], $filename, $mime);
        if (!$ok) return ['success' => false, 'message' => 'فشل رفع الملف إلى التخزين'];
        $url = rtrim(R2_PUBLIC_URL, '/') . '/' . $filename;
        return ['success' => true, 'path' => $filename, 'url' => $url, 'message' => ''];
    } else {
        $dest = UPLOAD_DIR . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['success' => false, 'message' => 'فشل حفظ الملف'];
        }
        return ['success' => true, 'path' => $filename, 'url' => '', 'message' => ''];
    }
}

function upload_raw(string $data, string $prefix, int $user_id, string $ext = 'webm'): array {
    $filename = $prefix . '_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $mime     = 'audio/' . $ext;

    if (USE_R2) {
        $ok = _supabase_upload_raw($data, $filename, $mime);
        if (!$ok) return ['success' => false, 'message' => 'فشل رفع الملف إلى التخزين'];
        $url = rtrim(R2_PUBLIC_URL, '/') . '/' . $filename;
        return ['success' => true, 'path' => $filename, 'url' => $url, 'message' => ''];
    } else {
        $dir = UPLOAD_DIR . 'voice_feedback/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $dest = $dir . $filename;
        if (file_put_contents($dest, $data) === false) {
            return ['success' => false, 'message' => 'فشل حفظ الملف'];
        }
        return ['success' => true, 'path' => 'uploads/voice_feedback/' . $filename, 'url' => '', 'message' => ''];
    }
}

function file_url(string $path, string $serve_script = 'serve_audio.php'): string {
    if (!$path) return '';
    $basename = basename($path);
    if (USE_R2) {
        if (str_starts_with($path, 'http')) return $path;
        return rtrim(R2_PUBLIC_URL, '/') . '/' . $basename;
    }
    return SITE_URL . '/' . $serve_script . '?file=' . urlencode($basename);
}

function delete_file(string $path): void {
    if (!$path) return;
    if (USE_R2) {
        _supabase_delete(basename($path));
    } else {
        $local = UPLOAD_DIR . basename($path);
        if (file_exists($local)) @unlink($local);
    }
}
