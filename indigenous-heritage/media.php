<?php
require_once __DIR__ . '/includes/bootstrap.php';

$id = get_int('id');
$want_thumb = get_str('size', 10) === 'thumb';

$media = db_one(
    'SELECT m.id, m.record_id, m.kind, m.original_name, m.mime_type, m.sha256, m.is_confidential,
            (m.thumbnail IS NOT NULL) AS has_thumbnail
     FROM record_media m WHERE m.id = ?',
    [$id]
);
$record = $media ? db_one('SELECT id, status, visibility, consent_status FROM heritage_records WHERE id = ?', [(int) $media['record_id']]) : null;

if (!$media || !$record || !can_view_media($media, $record)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo 'Not found';
    exit;
}

$use_thumb = $want_thumb && (int) $media['has_thumbnail'];
$mime = $use_thumb ? 'image/jpeg' : $media['mime_type'];
if (!isset(IMAGE_MIME_TYPES[$mime]) && !isset(DOCUMENT_MIME_TYPES[$mime])) {
    $mime = 'application/octet-stream';
}

$is_public = !(int) $media['is_confidential'] && $record['visibility'] === 'public' && $record['status'] === 'published';
$etag = '"' . substr($media['sha256'], 0, 32) . ($use_thumb ? '-t' : '') . '"';

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
header_remove('Pragma');
header_remove('Expires');
header('Cache-Control: ' . ($is_public ? 'public, max-age=86400' : 'private, no-store'));
header('ETag: ' . $etag);
if ($is_public && trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}

$column = $use_thumb ? 'thumbnail' : 'data';
$stmt = db()->prepare("SELECT $column FROM record_media WHERE id = ?");
$stmt->execute([$id]);
$stmt->bindColumn(1, $content, PDO::PARAM_LOB);
$stmt->fetch(PDO::FETCH_BOUND);
if (is_resource($content)) {
    $content = stream_get_contents($content);
}

$disposition = (isset(IMAGE_MIME_TYPES[$mime]) || $mime === 'application/pdf' || $mime === 'text/plain') ? 'inline' : 'attachment';
$ascii_name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $media['original_name']);

header('Content-Type: ' . $mime . ($mime === 'text/plain' ? '; charset=UTF-8' : ''));
header('Content-Length: ' . strlen((string) $content));
header('Content-Disposition: ' . $disposition . '; filename="' . $ascii_name . '"; filename*=UTF-8\'\'' . rawurlencode($media['original_name']));
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
header('X-Robots-Tag: ' . ($is_public ? 'all' : 'noindex, nofollow'));
echo $content;
