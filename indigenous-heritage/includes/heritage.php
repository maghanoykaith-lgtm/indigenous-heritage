<?php

const VISIBILITY_LEVELS = [
    'public' => 'Public',
    'restricted' => 'Restricted (approved community members)',
    'private' => 'Private (staff only)',
];

const RECORD_STATUSES = [
    'draft' => 'Draft',
    'pending' => 'Pending review',
    'published' => 'Published',
    'archived' => 'Archived',
];

const CONSENT_STATUSES = [
    'pending' => 'Consent pending',
    'granted' => 'Consent granted',
    'not_required' => 'Not required (already public / non-sensitive)',
    'withdrawn' => 'Consent withdrawn',
];

const IMAGE_MIME_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
const DOCUMENT_MIME_TYPES = [
    'application/pdf' => 'pdf',
    'text/plain' => 'txt',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
];
const MAX_IMAGE_PIXELS = 25000000;
const MAX_FILES_PER_UPLOAD = 6;

// ----- Access rules -----

// SQL condition limiting records to what the current visitor may see.
function record_access_sql(string $alias = 'r'): string
{
    if (is_staff()) {
        return '1=1';
    }
    $levels = is_active_member() ? "'public','restricted'" : "'public'";
    return "$alias.status = 'published' AND $alias.visibility IN ($levels) AND $alias.consent_status <> 'withdrawn'";
}

function can_view_record(array $record): bool
{
    if (is_staff()) {
        return true;
    }
    if ($record['status'] !== 'published' || $record['consent_status'] === 'withdrawn') {
        return false;
    }
    if ($record['visibility'] === 'public') {
        return true;
    }
    return $record['visibility'] === 'restricted' && is_active_member();
}

function can_view_media(array $media, array $record): bool
{
    if (is_staff()) {
        return true;
    }
    return !(int) $media['is_confidential'] && can_view_record($record);
}

function can_show_location(array $record): bool
{
    return is_staff() || !(int) $record['location_is_sensitive'];
}

function media_condition_for_viewer(string $alias = 'm'): string
{
    return is_staff() ? '1=1' : "$alias.is_confidential = 0";
}

// ----- Categories -----

function fetch_categories(bool $withCounts = false): array
{
    if (!$withCounts) {
        return db_all('SELECT id, name, slug, description, icon, sort_order FROM categories ORDER BY sort_order, name');
    }
    $access = record_access_sql('r');
    return db_all(
        "SELECT c.id, c.name, c.slug, c.description, c.icon, c.sort_order,
                COUNT(r.id) AS record_count
         FROM categories c
         LEFT JOIN heritage_records r ON r.category_id = c.id AND $access
         GROUP BY c.id, c.name, c.slug, c.description, c.icon, c.sort_order
         ORDER BY c.sort_order, c.name"
    );
}

function find_category_by_slug(string $slug): ?array
{
    return db_one('SELECT * FROM categories WHERE slug = ?', [$slug]);
}

// ----- Records -----

function find_record(int $id): ?array
{
    return db_one(
        'SELECT r.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon,
                cb.username AS created_by_name, ub.username AS updated_by_name, ab.username AS approved_by_name
         FROM heritage_records r
         LEFT JOIN categories c ON c.id = r.category_id
         LEFT JOIN admins cb ON cb.id = r.created_by
         LEFT JOIN admins ub ON ub.id = r.updated_by
         LEFT JOIN admins ab ON ab.id = r.approved_by
         WHERE r.id = ?',
        [$id]
    );
}

function search_records(array $filters, int $page, int $perPage): array
{
    $where = [record_access_sql('r')];
    $params = [];

    if (!empty($filters['q'])) {
        $where[] = '(r.title LIKE ? OR r.summary LIKE ? OR r.description LIKE ? OR r.keywords LIKE ? OR r.community LIKE ? OR r.language LIKE ?)';
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']) . '%';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }
    if (!empty($filters['category_id'])) {
        $where[] = 'r.category_id = ?';
        $params[] = (int) $filters['category_id'];
    }
    foreach (['status' => RECORD_STATUSES, 'visibility' => VISIBILITY_LEVELS, 'consent_status' => CONSENT_STATUSES] as $field => $allowed) {
        if (!empty($filters[$field]) && isset($allowed[$filters[$field]])) {
            $where[] = "r.$field = ?";
            $params[] = $filters[$field];
        }
    }

    $orderBy = [
        'newest' => 'r.created_at DESC, r.id DESC',
        'updated' => 'r.updated_at DESC, r.id DESC',
        'title' => 'r.title ASC',
        'oldest' => 'r.created_at ASC, r.id ASC',
    ][$filters['sort'] ?? 'newest'] ?? 'r.created_at DESC, r.id DESC';

    $whereSql = implode(' AND ', $where);
    $total = (int) db_value("SELECT COUNT(*) FROM heritage_records r WHERE $whereSql", $params);
    $pager = paginate($total, $perPage, $page);
    $mediaRule = media_condition_for_viewer('m');

    $rows = db_all(
        "SELECT r.id, r.title, r.summary, r.description, r.community, r.language, r.keywords, r.period,
                r.date_recorded, r.visibility, r.status, r.consent_status, r.is_sacred, r.created_at, r.updated_at,
                r.published_at, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon,
                (SELECT m.id FROM record_media m WHERE m.record_id = r.id AND m.kind = 'image' AND $mediaRule
                 ORDER BY m.is_primary DESC, m.id ASC LIMIT 1) AS cover_media_id
         FROM heritage_records r
         LEFT JOIN categories c ON c.id = r.category_id
         WHERE $whereSql
         ORDER BY $orderBy
         LIMIT " . (int) $pager['per_page'] . ' OFFSET ' . (int) $pager['offset'],
        $params
    );

    return ['rows' => $rows, 'pager' => $pager];
}

function record_media_list(int $recordId): array
{
    $rule = media_condition_for_viewer('m');
    return db_all(
        "SELECT m.id, m.kind, m.original_name, m.mime_type, m.file_size, m.caption, m.is_confidential, m.is_primary, m.created_at
         FROM record_media m WHERE m.record_id = ? AND $rule ORDER BY m.is_primary DESC, m.id ASC",
        [$recordId]
    );
}

function media_url(int $mediaId, bool $thumbnail = false): string
{
    return url('media.php', ['id' => $mediaId, 'size' => $thumbnail ? 'thumb' : null]);
}

function keyword_list(?string $keywords): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string) $keywords))));
}

function visibility_badge(string $visibility): string
{
    $class = ['public' => 'badge-success', 'restricted' => 'badge-warning', 'private' => 'badge-danger'][$visibility] ?? 'badge-primary';
    $label = ['public' => 'Public', 'restricted' => 'Restricted', 'private' => 'Private'][$visibility] ?? $visibility;
    return '<span class="badge ' . $class . '">' . e($label) . '</span>';
}

function status_badge(string $status): string
{
    $class = ['published' => 'badge-success', 'pending' => 'badge-warning', 'draft' => 'badge-primary', 'archived' => 'badge-danger'][$status] ?? 'badge-primary';
    return '<span class="badge ' . $class . '">' . e(RECORD_STATUSES[$status] ?? $status) . '</span>';
}

function consent_badge(string $consent): string
{
    $class = ['granted' => 'badge-success', 'not_required' => 'badge-success', 'pending' => 'badge-warning', 'withdrawn' => 'badge-danger'][$consent] ?? 'badge-primary';
    $label = ['granted' => 'Consent granted', 'not_required' => 'Consent not required', 'pending' => 'Consent pending', 'withdrawn' => 'Consent withdrawn'][$consent] ?? $consent;
    return '<span class="badge ' . $class . '">' . e($label) . '</span>';
}

// Problems that block a record from being published.
function publication_problems(array $record): array
{
    $problems = [];
    if (!in_array($record['consent_status'], ['granted', 'not_required'], true)) {
        $problems[] = 'Community consent must be recorded as granted (or not required) before publishing.';
    }
    if ((int) $record['is_sacred'] && $record['visibility'] === 'public') {
        $problems[] = 'Sacred or confidential records cannot be published with Public visibility.';
    }
    if (empty($record['category_id'])) {
        $problems[] = 'A category must be assigned before publishing.';
    }
    return $problems;
}

function record_form_defaults(): array
{
    return [
        'title' => '', 'category_id' => '', 'summary' => '', 'description' => '', 'cultural_significance' => '',
        'community' => '', 'language' => '', 'source' => '', 'period' => '', 'date_recorded' => '',
        'keywords' => '', 'location' => '', 'location_is_sensitive' => 1, 'visibility' => 'private',
        'status' => 'draft', 'is_sacred' => 0, 'consent_status' => 'pending', 'consent_given_by' => '',
        'consent_date' => '', 'consent_notes' => '',
    ];
}

function validate_record_input(array $staff, ?array $existing): array
{
    $data = [
        'title' => post_str('title', 200),
        'category_id' => post_int('category_id'),
        'summary' => post_str('summary', 500),
        'description' => post_str('description', 60000),
        'cultural_significance' => post_str('cultural_significance', 20000),
        'community' => post_str('community', 150),
        'language' => post_str('language', 100),
        'source' => post_str('source', 200),
        'period' => post_str('period', 100),
        'date_recorded' => post_str('date_recorded', 10),
        'keywords' => post_str('keywords', 500),
        'location' => post_str('location', 200),
        'location_is_sensitive' => isset($_POST['location_is_sensitive']) ? 1 : 0,
        'visibility' => post_str('visibility', 20),
        'status' => post_str('status', 20),
        'is_sacred' => isset($_POST['is_sacred']) ? 1 : 0,
        'consent_status' => post_str('consent_status', 20),
        'consent_given_by' => post_str('consent_given_by', 200),
        'consent_date' => post_str('consent_date', 10),
        'consent_notes' => post_str('consent_notes', 5000),
    ];
    $errors = [];

    if (mb_strlen($data['title']) < 3) {
        $errors[] = 'Title must be at least 3 characters.';
    }
    if ($data['description'] === '') {
        $errors[] = 'Description is required.';
    }
    if (!$data['category_id'] || !db_value('SELECT id FROM categories WHERE id = ?', [$data['category_id']])) {
        $errors[] = 'Please choose a valid category.';
    }
    if (!isset(VISIBILITY_LEVELS[$data['visibility']])) {
        $errors[] = 'Please choose a valid visibility level.';
    }
    if (!isset(CONSENT_STATUSES[$data['consent_status']])) {
        $errors[] = 'Please choose a valid consent status.';
    }
    if (!isset(RECORD_STATUSES[$data['status']])) {
        $errors[] = 'Please choose a valid publication status.';
    }
    foreach (['date_recorded' => 'Date recorded', 'consent_date' => 'Consent date'] as $field => $label) {
        if ($data[$field] !== '' && !valid_date($data[$field])) {
            $errors[] = "$label must be a valid date.";
        }
    }
    if ($data['consent_status'] === 'granted' && $data['consent_given_by'] === '') {
        $errors[] = 'Please record who granted consent (community, elder, or custodian).';
    }
    if ($data['is_sacred'] && $data['visibility'] === 'public') {
        $errors[] = 'Sacred or confidential records cannot use Public visibility. Choose Restricted or Private.';
    }

    // Editors submit work for review; only administrators publish or archive.
    if ($staff['role'] !== 'administrator' && in_array($data['status'], ['published', 'archived'], true)) {
        $data['status'] = 'pending';
    }
    if ($data['status'] === 'published' && !$errors) {
        foreach (publication_problems($data) as $problem) {
            $errors[] = $problem;
        }
    }

    return [$data, $errors];
}

function save_record(array $data, array $staff, ?array $existing): int
{
    $columns = ['title', 'category_id', 'summary', 'description', 'cultural_significance', 'community', 'language', 'source',
        'period', 'date_recorded', 'keywords', 'location', 'location_is_sensitive', 'visibility', 'status', 'is_sacred',
        'consent_status', 'consent_given_by', 'consent_date', 'consent_notes'];
    $values = [];
    foreach ($columns as $column) {
        $value = $data[$column];
        $values[$column] = ($value === '' && in_array($column, ['date_recorded', 'consent_date', 'summary', 'cultural_significance', 'community', 'language', 'source', 'period', 'keywords', 'location', 'consent_given_by', 'consent_notes'], true)) ? null : $value;
    }

    $becamePublished = $data['status'] === 'published' && (!$existing || $existing['status'] !== 'published');
    if ($existing) {
        $set = implode(', ', array_map(static fn($c) => "$c = ?", $columns));
        $params = array_values($values);
        $extra = ', updated_by = ?';
        $params[] = $staff['id'];
        if ($becamePublished) {
            $extra .= ', approved_by = ?, approved_at = NOW(), published_at = COALESCE(published_at, NOW())';
            $params[] = $staff['id'];
        }
        $params[] = $existing['id'];
        db_run("UPDATE heritage_records SET $set$extra WHERE id = ?", $params);
        return (int) $existing['id'];
    }

    $columnSql = implode(', ', $columns) . ', created_by, updated_by';
    $placeholders = implode(', ', array_fill(0, count($columns) + 2, '?'));
    $params = array_values($values);
    $params[] = $staff['id'];
    $params[] = $staff['id'];
    if ($becamePublished) {
        $columnSql .= ', approved_by, approved_at, published_at';
        $placeholders .= ', ?, NOW(), NOW()';
        $params[] = $staff['id'];
    }
    db_run("INSERT INTO heritage_records ($columnSql) VALUES ($placeholders)", $params);
    return (int) db()->lastInsertId();
}

// ----- Uploads -----

function normalize_uploaded_files(?array $files): array
{
    if (!$files || !isset($files['name'])) {
        return [];
    }
    if (!is_array($files['name'])) {
        return $files['error'] === UPLOAD_ERR_NO_FILE ? [] : [$files];
    }
    $list = [];
    foreach ($files['name'] as $i => $name) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $list[] = [
            'name' => $name,
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$i] ?? 0,
        ];
    }
    return $list;
}

function clean_file_name(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[^\pL\pN ._()-]+/u', '_', $name) ?? 'file';
    return mb_substr(trim($name, ' ._') ?: 'file', 0, 200);
}

function process_upload(array $file): array
{
    $maxBytes = (int) app_config('upload_max_bytes');
    $name = clean_file_name((string) $file['name']);
    $fail = static fn(string $message) => ['ok' => false, 'error' => $name . ': ' . $message];

    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return $fail('file is larger than the ' . format_bytes($maxBytes) . ' limit.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return $fail('upload failed. Please try again.');
    }
    if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
        return $fail('file must be between 1 byte and ' . format_bytes($maxBytes) . '.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: 'application/octet-stream';
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $data = file_get_contents($file['tmp_name']);
    if ($data === false) {
        return $fail('could not be read.');
    }

    if ($extension === 'docx' && in_array($mime, ['application/zip', 'application/octet-stream'], true) && strncmp($data, "PK\x03\x04", 4) === 0) {
        $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    if (isset(IMAGE_MIME_TYPES[$mime])) {
        $info = @getimagesizefromstring($data);
        if ($info === false || ($info['mime'] ?? '') !== $mime) {
            return $fail('is not a valid image.');
        }
        if ($info[0] * $info[1] > MAX_IMAGE_PIXELS) {
            return $fail('image dimensions are too large (max 25 megapixels).');
        }
        [$data, $mime, $thumb] = prepare_image($data, $mime);
        return ['ok' => true, 'kind' => 'image', 'mime' => $mime, 'name' => $name, 'data' => $data, 'thumb' => $thumb];
    }

    if (isset(DOCUMENT_MIME_TYPES[$mime]) && DOCUMENT_MIME_TYPES[$mime] === $extension) {
        return ['ok' => true, 'kind' => 'document', 'mime' => $mime, 'name' => $name, 'data' => $data, 'thumb' => null];
    }

    return $fail('unsupported file type. Allowed: JPG, PNG, GIF, WEBP images and PDF, DOCX, TXT documents.');
}

// Re-encodes photos to strip embedded metadata (including GPS locations) and builds a thumbnail.
function prepare_image(string $data, string $mime): array
{
    if (!function_exists('imagecreatefromstring') || $mime === 'image/gif') {
        return [$data, $mime, null];
    }
    $image = @imagecreatefromstring($data);
    if (!$image) {
        return [$data, $mime, null];
    }

    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($data));
        $rotation = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($rotation !== 0) {
            $rotated = imagerotate($image, $rotation, 0);
            if ($rotated) {
                imagedestroy($image);
                $image = $rotated;
            }
        }
    } elseif ($mime === 'image/jpeg') {
        // Without EXIF support the orientation cannot be preserved, so keep the original bytes.
        $thumb = image_to_jpeg(resize_image($image, 640));
        imagedestroy($image);
        return [$data, $mime, $thumb];
    }

    $full = resize_image($image, 2000);
    $outputMime = $mime;
    ob_start();
    if ($mime === 'image/png') {
        imagesavealpha($full, true);
        imagepng($full, null, 6);
    } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
        imagewebp($full, null, 85);
    } else {
        imagejpeg($full, null, 85);
        $outputMime = 'image/jpeg';
    }
    $encoded = (string) ob_get_clean();
    $thumb = image_to_jpeg(resize_image($image, 640));
    if ($full !== $image) {
        imagedestroy($full);
    }
    imagedestroy($image);

    return $encoded !== '' ? [$encoded, $outputMime, $thumb] : [$data, $mime, $thumb];
}

function resize_image($image, int $maxSide)
{
    $width = imagesx($image);
    $height = imagesy($image);
    $scale = min(1, $maxSide / max($width, $height));
    if ($scale >= 1) {
        return $image;
    }
    $newWidth = max(1, (int) round($width * $scale));
    $newHeight = max(1, (int) round($height * $scale));
    $resized = imagecreatetruecolor($newWidth, $newHeight);
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    return $resized;
}

function image_to_jpeg($image): ?string
{
    $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
    ob_start();
    imagejpeg($canvas, null, 80);
    imagedestroy($canvas);
    $out = (string) ob_get_clean();
    return $out !== '' ? $out : null;
}

function store_uploads(int $recordId, array $files, bool $confidential, string $caption, int $uploaderId): array
{
    $files = normalize_uploaded_files($files);
    $saved = 0;
    $errors = [];
    if (count($files) > MAX_FILES_PER_UPLOAD) {
        return [0, ['Please upload at most ' . MAX_FILES_PER_UPLOAD . ' files at a time.']];
    }
    foreach ($files as $file) {
        $result = process_upload($file);
        if (!$result['ok']) {
            $errors[] = $result['error'];
            continue;
        }
        $hasPrimary = (bool) db_value("SELECT COUNT(*) FROM record_media WHERE record_id = ? AND kind = 'image' AND is_primary = 1", [$recordId]);
        $isPrimary = $result['kind'] === 'image' && !$hasPrimary && !$confidential ? 1 : 0;

        $stmt = db()->prepare(
            'INSERT INTO record_media (record_id, kind, original_name, mime_type, file_size, sha256, caption, is_confidential, is_primary, data, thumbnail, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $data = $result['data'];
        $thumb = $result['thumb'];
        $stmt->bindValue(1, $recordId, PDO::PARAM_INT);
        $stmt->bindValue(2, $result['kind']);
        $stmt->bindValue(3, $result['name']);
        $stmt->bindValue(4, $result['mime']);
        $stmt->bindValue(5, strlen($data), PDO::PARAM_INT);
        $stmt->bindValue(6, hash('sha256', $data));
        $stmt->bindValue(7, $caption !== '' ? $caption : null);
        $stmt->bindValue(8, $confidential ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(9, $isPrimary, PDO::PARAM_INT);
        $stmt->bindParam(10, $data, PDO::PARAM_LOB);
        $stmt->bindParam(11, $thumb, $thumb === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
        $stmt->bindValue(12, $uploaderId, PDO::PARAM_INT);
        $stmt->execute();
        $saved++;
    }
    return [$saved, $errors];
}
