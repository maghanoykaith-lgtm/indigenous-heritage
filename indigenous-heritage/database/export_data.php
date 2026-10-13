<?php
// Usage: php database/export_data.php [output-file]
// Writes every application table's rows as INSERT statements so the data can be
// loaded into a fresh production database (automatically on first start, or manually).

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/includes/bootstrap.php';

$output = $argv[1] ?? dirname(__DIR__) . '/database/data/initial_data.sql';
$tables = ['admins', 'users', 'categories', 'heritage_records', 'record_media', 'messages',
    'crafts', 'traditions', 'materials', 'stories', 'gallery'];

$pdo = db();
if (!is_dir(dirname($output))) {
    mkdir(dirname($output), 0775, true);
}
$out = fopen($output, 'wb');
fwrite($out, "-- Indigenous Heritage Management System data export\n");
fwrite($out, '-- Generated ' . date('Y-m-d H:i:s T') . " from database '" . app_config('db')['name'] . "'\n");
fwrite($out, "-- Load into a database that already has the schema (php database/migrate.php creates it).\n\n");
fwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n");

foreach ($tables as $table) {
    $columns = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$table' ORDER BY ordinal_position")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!$columns) {
        continue;
    }
    $columnList = implode(', ', array_map(static fn($c) => "`$c`", array_keys($columns)));
    $rows = 0;
    fwrite($out, "\n-- Table: $table\n");
    $stmt = $pdo->query("SELECT * FROM `$table` ORDER BY 1");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $values = [];
        foreach ($row as $column => $value) {
            $type = strtolower($columns[$column] ?? '');
            if ($value === null) {
                $values[] = 'NULL';
            } elseif (str_contains($type, 'blob') || $type === 'binary' || $type === 'varbinary') {
                $values[] = $value === '' ? "''" : '0x' . bin2hex($value);
            } elseif (in_array($type, ['int', 'bigint', 'tinyint', 'smallint', 'mediumint', 'decimal'], true)) {
                $values[] = (string) $value;
            } else {
                $values[] = $pdo->quote((string) $value);
            }
        }
        fwrite($out, "INSERT IGNORE INTO `$table` ($columnList) VALUES (" . implode(', ', $values) . ");\n");
        $rows++;
    }
    echo "[export] $table: $rows rows" . PHP_EOL;
}

fwrite($out, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
fclose($out);
echo "[export] Wrote $output" . PHP_EOL;
