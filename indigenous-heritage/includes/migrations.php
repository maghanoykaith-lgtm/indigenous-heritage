<?php
// Schema migrations. Each named step runs once and is recorded in
// schema_migrations. Nothing here drops tables or deletes existing data.

const HERITAGE_CATEGORIES = [
    ['Traditional Practices', 'traditional-practices', "\u{1F33E}", 'Customs, ceremonies, rites of passage, festivals, and everyday community practices.'],
    ['Oral Traditions', 'oral-traditions', "\u{1F4D6}", 'Stories, legends, epics, chants, and histories passed down by word of mouth.'],
    ['Indigenous Languages', 'indigenous-languages', "\u{1F5E3}\u{FE0F}", 'Vocabulary, dialects, place names, songs, and language documentation.'],
    ['Traditional Arts and Crafts', 'traditional-arts-and-crafts', "\u{1F3A8}", 'Weaving, carving, pottery, beadwork, and other artisan techniques.'],
    ['Music and Dance', 'music-and-dance', "\u{1F941}", 'Songs, instruments, dances, and performance traditions.'],
    ['Cultural Artifacts', 'cultural-artifacts', "\u{1F3FA}", 'Objects, tools, attire, and heirlooms of cultural importance.'],
    ['Traditional Knowledge', 'traditional-knowledge', "\u{1F33F}", 'Ecological knowledge, natural materials, healing, farming, and craftsmanship know-how.'],
    ['Historical Records', 'historical-records', "\u{1F4DC}", 'Documents, timelines, photographs, and accounts of community history.'],
];

const LEGACY_CONSENT_NOTE = 'Migrated from the earlier public portal, where it was already publicly displayed. Community consent has not been documented yet; please review and update.';

function m_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function m_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function m_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!m_column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

// Undo double-escaping left by the old sanitize_input() (htmlspecialchars + real_escape_string).
function legacy_clean(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    if (preg_match('/\\\\[\'"\\\\nr]/', $value)) {
        $value = str_replace(['\\r\\n', '\\n', '\\r'], ["\n", "\n", ''], $value);
        $value = stripslashes($value);
    }
    return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function migration_steps(): array
{
    return [
        '001_legacy_tables' => 'migrate_legacy_tables',
        '002_staff_member_columns' => 'migrate_staff_member_columns',
        '003_heritage_tables' => 'migrate_heritage_tables',
        '004_import_initial_data' => 'migrate_import_initial_data',
        '005_seed_categories' => 'migrate_seed_categories',
        '006_legacy_content_to_records' => 'migrate_legacy_content',
        '007_repair_category_icons' => 'migrate_repair_category_icons',
    ];
}

function run_migrations(PDO $pdo, callable $log): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
        name VARCHAR(100) NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $applied = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    foreach (migration_steps() as $name => $function) {
        if (in_array($name, $applied, true)) {
            continue;
        }
        $log("Applying $name ...");
        $function($pdo, $log);
        $pdo->prepare('INSERT INTO schema_migrations (name) VALUES (?)')->execute([$name]);
    }

    ensure_admin_account($pdo, $log);
    $log('Database is up to date.');
}

function migrate_legacy_tables(PDO $pdo, callable $log): void
{
    $tables = [
        'admins' => 'id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, email VARCHAR(100) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'crafts' => 'id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, description TEXT NOT NULL, materials TEXT NOT NULL, cultural_significance TEXT NOT NULL, image VARCHAR(255), category VARCHAR(50), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        'traditions' => 'id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, description TEXT NOT NULL, type VARCHAR(50) NOT NULL, origin VARCHAR(100), image VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        'materials' => 'id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, description TEXT NOT NULL, uses TEXT NOT NULL, cultural_importance TEXT NOT NULL, image VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        'stories' => 'id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL, content TEXT NOT NULL, author VARCHAR(100), category VARCHAR(50), origin VARCHAR(100), image VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        'gallery' => 'id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(100) NOT NULL, description TEXT, image VARCHAR(255) NOT NULL, category VARCHAR(50), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'messages' => 'id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, email VARCHAR(100) NOT NULL, subject VARCHAR(200) NOT NULL, message TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, is_read BOOLEAN DEFAULT FALSE',
        'users' => 'id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, email VARCHAR(100) NOT NULL UNIQUE, full_name VARCHAR(100), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    ];
    foreach ($tables as $table => $definition) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `$table` ($definition) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

function migrate_staff_member_columns(PDO $pdo, callable $log): void
{
    // WAMP creates MyISAM tables by default; InnoDB is required for foreign keys and transactions.
    $engines = $pdo->query("SELECT table_name, engine FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('admins','crafts','traditions','materials','stories','gallery','messages','users')")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($engines as $table => $engine) {
        if (strcasecmp((string) $engine, 'InnoDB') !== 0) {
            $pdo->exec("ALTER TABLE `$table` ENGINE=InnoDB");
            $log("  Converted $table from $engine to InnoDB.");
        }
    }

    m_add_column($pdo, 'admins', 'full_name', 'VARCHAR(100) NULL AFTER email');
    m_add_column($pdo, 'admins', 'role', "ENUM('administrator','editor') NOT NULL DEFAULT 'administrator' AFTER full_name");
    m_add_column($pdo, 'admins', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER role');
    m_add_column($pdo, 'admins', 'must_change_password', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active');
    m_add_column($pdo, 'admins', 'last_login_at', 'DATETIME NULL AFTER must_change_password');

    $hadStatus = m_column_exists($pdo, 'users', 'status');
    m_add_column($pdo, 'users', 'community', 'VARCHAR(150) NULL AFTER full_name');
    m_add_column($pdo, 'users', 'status', "ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending' AFTER community");
    m_add_column($pdo, 'users', 'last_login_at', 'DATETIME NULL AFTER status');
    if (!$hadStatus) {
        // Accounts registered before approval existed keep the access they already had.
        $count = $pdo->exec("UPDATE users SET status = 'active'");
        $log("  Existing member accounts kept active: $count");
    }
}

function migrate_heritage_tables(PDO $pdo, callable $log): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(120) NOT NULL,
        description TEXT NULL,
        icon VARCHAR(16) NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_categories_name (name),
        UNIQUE KEY uq_categories_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS heritage_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        category_id INT NULL,
        summary VARCHAR(500) NULL,
        description MEDIUMTEXT NOT NULL,
        cultural_significance TEXT NULL,
        community VARCHAR(150) NULL,
        language VARCHAR(100) NULL,
        source VARCHAR(200) NULL,
        period VARCHAR(100) NULL,
        date_recorded DATE NULL,
        keywords VARCHAR(500) NULL,
        location VARCHAR(200) NULL,
        location_is_sensitive TINYINT(1) NOT NULL DEFAULT 1,
        visibility ENUM('public','restricted','private') NOT NULL DEFAULT 'private',
        status ENUM('draft','pending','published','archived') NOT NULL DEFAULT 'draft',
        is_sacred TINYINT(1) NOT NULL DEFAULT 0,
        consent_status ENUM('pending','granted','not_required','withdrawn') NOT NULL DEFAULT 'pending',
        consent_given_by VARCHAR(200) NULL,
        consent_date DATE NULL,
        consent_notes TEXT NULL,
        created_by INT NULL,
        updated_by INT NULL,
        approved_by INT NULL,
        approved_at DATETIME NULL,
        published_at DATETIME NULL,
        legacy_source VARCHAR(30) NULL,
        legacy_id INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_records_public (status, visibility, consent_status),
        KEY idx_records_category (category_id),
        KEY idx_records_created (created_at),
        UNIQUE KEY uq_records_legacy (legacy_source, legacy_id),
        CONSTRAINT fk_records_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT,
        CONSTRAINT fk_records_created_by FOREIGN KEY (created_by) REFERENCES admins (id) ON DELETE SET NULL,
        CONSTRAINT fk_records_updated_by FOREIGN KEY (updated_by) REFERENCES admins (id) ON DELETE SET NULL,
        CONSTRAINT fk_records_approved_by FOREIGN KEY (approved_by) REFERENCES admins (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS record_media (
        id INT AUTO_INCREMENT PRIMARY KEY,
        record_id INT NOT NULL,
        kind ENUM('image','document') NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        file_size INT UNSIGNED NOT NULL,
        sha256 CHAR(64) NOT NULL,
        caption VARCHAR(255) NULL,
        is_confidential TINYINT(1) NOT NULL DEFAULT 0,
        is_primary TINYINT(1) NOT NULL DEFAULT 0,
        data LONGBLOB NOT NULL,
        thumbnail MEDIUMBLOB NULL,
        uploaded_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_media_record (record_id, kind),
        CONSTRAINT fk_media_record FOREIGN KEY (record_id) REFERENCES heritage_records (id) ON DELETE CASCADE,
        CONSTRAINT fk_media_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES admins (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        actor_type ENUM('staff','member','guest','system') NOT NULL,
        actor_id INT NULL,
        action VARCHAR(60) NOT NULL,
        entity VARCHAR(40) NULL,
        entity_id INT NULL,
        details VARCHAR(500) NULL,
        ip_address VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_audit_entity (entity, entity_id),
        KEY idx_audit_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        identifier VARCHAR(100) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_attempts_lookup (identifier, ip_address, attempted_at),
        KEY idx_attempts_ip (ip_address, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// On a brand-new database, load the data exported from the local WAMP install.
function migrate_import_initial_data(PDO $pdo, callable $log): void
{
    $file = APP_ROOT . '/database/data/initial_data.sql';
    $hasData = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0
        || (int) $pdo->query('SELECT COUNT(*) FROM heritage_records')->fetchColumn() > 0;
    if ($hasData) {
        $log('  Existing data found; initial data import skipped.');
        return;
    }
    if (!is_readable($file)) {
        $log('  No initial data file found; starting with an empty archive.');
        return;
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    try {
        $statements = 0;
        $buffer = '';
        $handle = fopen($file, 'rb');
        while (($line = fgets($handle)) !== false) {
            if ($buffer === '' && (trim($line) === '' || strncmp(ltrim($line), '--', 2) === 0)) {
                continue;
            }
            $buffer .= $line;
            if (substr(rtrim($line), -1) === ';') {
                $pdo->exec($buffer);
                $statements++;
                $buffer = '';
            }
        }
        fclose($handle);
        $log("  Imported initial data ($statements statements).");
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}

function migrate_seed_categories(PDO $pdo, callable $log): void
{
    $stmt = $pdo->prepare('INSERT INTO categories (name, slug, icon, description, sort_order) VALUES (?, ?, ?, ?, ?)');
    $exists = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE slug = ? OR name = ?');
    foreach (HERITAGE_CATEGORIES as $i => [$name, $slug, $icon, $description]) {
        $exists->execute([$slug, $name]);
        if ((int) $exists->fetchColumn() === 0) {
            $stmt->execute([$name, $slug, $icon, $description, ($i + 1) * 10]);
        }
    }
}

// Early installs stored the default icons double-encoded (UTF-8 bytes read as
// Windows-1252). Such values always begin with bytes C3 B0 ("ð").
function migrate_repair_category_icons(PDO $pdo, callable $log): void
{
    $stmt = $pdo->prepare("UPDATE categories SET icon = ? WHERE slug = ? AND LEFT(HEX(icon), 4) = 'C3B0'");
    $fixed = 0;
    foreach (HERITAGE_CATEGORIES as [, $slug, $icon]) {
        $stmt->execute([$icon, $slug]);
        $fixed += $stmt->rowCount();
    }
    $log("  Category icons repaired: $fixed");
}

function migrate_legacy_content(PDO $pdo, callable $log): void
{
    $categories = $pdo->query('SELECT slug, id FROM categories')->fetchAll(PDO::FETCH_KEY_PAIR);
    $insert = $pdo->prepare(
        "INSERT INTO heritage_records
            (title, category_id, summary, description, cultural_significance, community, source, keywords,
             visibility, status, consent_status, consent_notes, location_is_sensitive, published_at, created_at,
             legacy_source, legacy_id)
         VALUES (?, ?, NULL, ?, ?, ?, ?, ?, 'public', 'published', 'pending', ?, 1, ?, ?, ?, ?)"
    );
    $exists = $pdo->prepare('SELECT id FROM heritage_records WHERE legacy_source = ? AND legacy_id = ?');
    $migrated = 0;

    $add = static function (string $source, array $row, array $record) use ($insert, $exists, $pdo, &$migrated): void {
        $exists->execute([$source, $row['id']]);
        if ($exists->fetchColumn()) {
            return;
        }
        $insert->execute([
            mb_substr(legacy_clean($record['title']), 0, 200),
            $record['category_id'],
            legacy_clean($record['description']) ?: '(No description recorded.)',
            legacy_clean($record['significance'] ?? null),
            $record['community'] ?? null ? mb_substr(legacy_clean($record['community']), 0, 150) : null,
            $record['source'] ?? null ? mb_substr(legacy_clean($record['source']), 0, 200) : null,
            $record['keywords'] ?? null ? mb_substr(legacy_clean($record['keywords']), 0, 500) : null,
            LEGACY_CONSENT_NOTE,
            $row['created_at'],
            $row['created_at'],
            $source,
            $row['id'],
        ]);
        $migrated++;
        attach_legacy_image($pdo, (int) $pdo->lastInsertId(), $row['image'] ?? null);
    };

    foreach ($pdo->query('SELECT * FROM crafts')->fetchAll() as $row) {
        $add('crafts', $row, [
            'title' => $row['name'], 'category_id' => $categories['traditional-arts-and-crafts'] ?? null,
            'description' => $row['description'], 'significance' => $row['cultural_significance'],
            'keywords' => trim(($row['category'] ? $row['category'] . ', ' : '') . $row['materials']),
        ]);
    }
    foreach ($pdo->query('SELECT * FROM traditions')->fetchAll() as $row) {
        $isPerformance = preg_match('/dance|music|song/i', $row['type'] . ' ' . $row['name']);
        $add('traditions', $row, [
            'title' => $row['name'], 'category_id' => $categories[$isPerformance ? 'music-and-dance' : 'traditional-practices'] ?? null,
            'description' => $row['description'], 'community' => $row['origin'], 'keywords' => $row['type'],
        ]);
    }
    foreach ($pdo->query('SELECT * FROM materials')->fetchAll() as $row) {
        $add('materials', $row, [
            'title' => $row['name'], 'category_id' => $categories['traditional-knowledge'] ?? null,
            'description' => legacy_clean($row['description']) . "\n\nTraditional uses: " . legacy_clean($row['uses']),
            'significance' => $row['cultural_importance'], 'keywords' => 'Traditional material, ' . $row['uses'],
        ]);
    }
    foreach ($pdo->query('SELECT * FROM stories')->fetchAll() as $row) {
        $add('stories', $row, [
            'title' => $row['title'], 'category_id' => $categories['oral-traditions'] ?? null,
            'description' => $row['content'], 'community' => $row['origin'], 'source' => $row['author'], 'keywords' => $row['category'],
        ]);
    }
    foreach ($pdo->query('SELECT * FROM gallery')->fetchAll() as $row) {
        $slug = preg_match('/dance|music/i', $row['title'])
            ? 'music-and-dance'
            : (preg_match('/^(tradition|ceremon|ritual)/i', (string) $row['category']) ? 'traditional-practices' : 'traditional-arts-and-crafts');
        $add('gallery', $row, [
            'title' => $row['title'], 'category_id' => $categories[$slug] ?? null,
            'description' => $row['description'] ?: $row['title'], 'keywords' => $row['category'],
        ]);
    }
    $log("  Legacy items copied into heritage records: $migrated");
}

function attach_legacy_image(PDO $pdo, int $recordId, ?string $image): void
{
    if (!$image) {
        return;
    }
    $path = APP_ROOT . '/images/' . basename($image);
    if (!is_file($path) || filesize($path) > 15 * 1048576) {
        return;
    }
    $data = (string) file_get_contents($path);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($data) ?: '';
    if (!isset(IMAGE_MIME_TYPES[$mime])) {
        return;
    }
    [$data, $mime, $thumb] = prepare_image($data, $mime);
    $stmt = $pdo->prepare('INSERT INTO record_media (record_id, kind, original_name, mime_type, file_size, sha256, is_primary, data, thumbnail) VALUES (?, \'image\', ?, ?, ?, ?, 1, ?, ?)');
    $stmt->bindValue(1, $recordId, PDO::PARAM_INT);
    $stmt->bindValue(2, basename($image));
    $stmt->bindValue(3, $mime);
    $stmt->bindValue(4, strlen($data), PDO::PARAM_INT);
    $stmt->bindValue(5, hash('sha256', $data));
    $stmt->bindParam(6, $data, PDO::PARAM_LOB);
    $stmt->bindParam(7, $thumb, $thumb === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
    $stmt->execute();
}

// Makes sure an administrator exists and that the published default password cannot be used in production.
function ensure_admin_account(PDO $pdo, callable $log): void
{
    $initial = (string) env('ADMIN_INITIAL_PASSWORD', '');
    $count = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();

    if ($count === 0) {
        $password = $initial !== '' ? $initial : (is_production() ? '' : DEFAULT_LEGACY_PASSWORD);
        if ($password === '') {
            $log('  WARNING: no administrator exists. Set ADMIN_INITIAL_PASSWORD and restart to create one.');
            return;
        }
        $email = (string) env('ADMIN_INITIAL_EMAIL', 'admin@example.org');
        $pdo->prepare("INSERT INTO admins (username, password, email, role, must_change_password) VALUES ('admin', ?, ?, 'administrator', 1)")
            ->execute([password_hash($password, PASSWORD_DEFAULT), $email]);
        $log("  Created administrator 'admin' (password change required at first login).");
        return;
    }

    foreach ($pdo->query('SELECT id, username, password FROM admins')->fetchAll() as $admin) {
        if (!password_verify(DEFAULT_LEGACY_PASSWORD, $admin['password'])) {
            continue;
        }
        if ($initial !== '' && is_production()) {
            $pdo->prepare('UPDATE admins SET password = ?, must_change_password = 1 WHERE id = ?')
                ->execute([password_hash($initial, PASSWORD_DEFAULT), $admin['id']]);
            $log("  Replaced the default password for '{$admin['username']}' with ADMIN_INITIAL_PASSWORD.");
        } else {
            $pdo->prepare('UPDATE admins SET must_change_password = 1 WHERE id = ?')->execute([$admin['id']]);
            $log("  NOTE: '{$admin['username']}' still uses the default password and must change it at next login."
                . (is_production() ? ' Default-password logins are blocked in production; set ADMIN_INITIAL_PASSWORD.' : ''));
        }
    }
}
