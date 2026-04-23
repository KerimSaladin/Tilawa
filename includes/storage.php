<?php
/**
 * storage.php — Cloudflare R2 / local file abstraction
 *
 * When R2 env vars are set (USE_R2 = true):
 *   - upload_file()  → saves to R2, returns public URL stored in DB
 *   - file_url()     → returns R2 public URL
 *   - delete_file()  → deletes from R2
 *
 * When USE_R2 = false (local dev):
 *   - upload_file()  → saves to local uploads/ dir
 *   - file_url()     → returns serve_audio.php?file= URL
 *   - delete_file()  → unlinks local file
 */

require_once __DIR__ . '/config.php';

function _r2_client() {
    static $client = null;
    if ($client) return $client;
    require_once __DIR__ . '/../vendor/autoload.php';
    $client = new Aws\S3\S3Client([
        'version'     => 'latest',
        'region'      => 'auto',
        'endpoint'    => R2_ENDPOINT,
        'credentials' => ['key' => R2_KEY, 'secret' => R2_SECRET],
        'use_path_style_endpoint' => true,
    ]);
    return $client;
}

/**
 * Upload a file (from $_FILES entry or raw path) to R2 or local disk.
 *
 * @param array  $file      $_FILES['field'] array
 * @param string $prefix    filename prefix, e.g. 'recitation', 'certificate'
 * @param int    $user_id
 * @return array ['success'=>bool, 'path'=>string, 'url'=>string, 'message'=>string]
 */
function upload_file(array $file, string $prefix, int $user_id): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $err_map = [
            UPLOAD_ERR_INI_SIZE   => 'الملف أكبر من الحد المسموح في إعدادات الخادم',
            UPLOAD_ERR_FORM_SIZE  => 'الملف أكبر من الحد المسموح في النموذج',
            UPLOAD_ERR_PARTIAL    => 'تم رفع الملف جزئياً، حاول مجدداً',
            UPLOAD_ERR_NO_FILE    => 'لم يتم اختيار ملف',
            UPLOAD_ERR_NO_TMP_DIR => 'مجلد الملفات المؤقتة مفقود',
            UPLOAD_ERR_CANT_WRITE => 'فشل كتابة الملف على القرص',
        ];
        return ['success' => false, 'message' => $err_map[$file['error']] ?? 'خطأ في الرفع: ' . $file['error']];
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'حجم الملف أكبر من الحد المسموح (100MB)'];
    }
    if ($file['size'] === 0) {
        return ['success' => false, 'message' => 'الملف فارغ'];
    }

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'bin';
    $filename = $prefix . '_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    if (USE_R2) {
        try {
            $s3 = _r2_client();
            $s3->putObject([
                'Bucket'      => R2_BUCKET,
                'Key'         => $filename,
                'SourceFile'  => $file['tmp_name'],
                'ContentType' => $file['type'] ?: 'application/octet-stream',
            ]);
            $url = rtrim(R2_PUBLIC_URL, '/') . '/' . $filename;
            return ['success' => true, 'path' => $filename, 'url' => $url, 'message' => ''];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'R2 upload failed: ' . $e->getMessage()];
        }
    } else {
        // Local
        if (!is_dir(UPLOAD_DIR)) {
            if (!mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
                return ['success' => false, 'message' => 'فشل إنشاء مجلد الرفع. تأكد من صلاحيات الكتابة.'];
            }
        }
        $dest = UPLOAD_DIR . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['success' => false, 'message' => 'فشل حفظ الملف. تأكد من صلاحيات الكتابة على: ' . UPLOAD_DIR];
        }
        return ['success' => true, 'path' => $filename, 'url' => '', 'message' => ''];
    }
}

/**
 * Upload raw binary content (e.g. base64-decoded voice feedback blob).
 */
function upload_raw(string $data, string $prefix, int $user_id, string $ext = 'webm'): array {
    $filename = $prefix . '_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    if (USE_R2) {
        try {
            $s3 = _r2_client();
            $s3->putObject([
                'Bucket'      => R2_BUCKET,
                'Key'         => $filename,
                'Body'        => $data,
                'ContentType' => 'audio/' . $ext,
            ]);
            $url = rtrim(R2_PUBLIC_URL, '/') . '/' . $filename;
            return ['success' => true, 'path' => $filename, 'url' => $url, 'message' => ''];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'R2 upload failed: ' . $e->getMessage()];
        }
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

/**
 * Return the public URL for a stored file path.
 * If R2: returns R2_PUBLIC_URL/filename
 * If local: returns serve_audio.php?file=filename (for authenticated serving)
 */
function file_url(string $path, string $serve_script = 'serve_audio.php'): string {
    if (!$path) return '';
    $basename = basename($path);
    if (USE_R2) {
        // If path already looks like a full URL, return as-is
        if (str_starts_with($path, 'http')) return $path;
        return rtrim(R2_PUBLIC_URL, '/') . '/' . $basename;
    }
    return SITE_URL . '/' . $serve_script . '?file=' . urlencode($basename);
}

/**
 * Delete a file from R2 or local disk.
 */
function delete_file(string $path): void {
    if (!$path) return;
    if (USE_R2) {
        try {
            _r2_client()->deleteObject(['Bucket' => R2_BUCKET, 'Key' => basename($path)]);
        } catch (Exception $e) { /* silent */ }
    } else {
        $local = UPLOAD_DIR . basename($path);
        if (file_exists($local)) @unlink($local);
    }
}
