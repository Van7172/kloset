<?php
// CLI local: ejecuta la migración consolidada y conserva su huella en la bitácora.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/utilities/vendor/autoload.php';
require dirname(__DIR__) . '/inc.core.php';
require APP_UTILITIES . 'Libs.php';
$db = \Develoweb\App\Model\Conexion::getInstance();
$file = __DIR__ . '/migrations/2026-09-27_entrega_local_consolidada.sql';
$sql = file_get_contents($file);
// Este archivo no usa procedimientos, DELIMITER ni puntos y coma en cadenas.
$sql = preg_replace('/^--.*$/m', '', $sql);
foreach (explode(';', $sql) as $statement) if (trim($statement) !== '') $db->exec(trim($statement));
$db->prepare('UPDATE sistema_migraciones SET sha256_archivo = ? WHERE archivo_migracion = ?')->execute([hash_file('sha256',$file),basename($file)]);
echo 'Migración aplicada en ' . $db->query('SELECT DATABASE()')->fetchColumn() . ' · ' . $db->query('SELECT VERSION()')->fetchColumn() . "\n";
echo 'SQL: ' . $file . "\n";
echo 'SHA256: ' . hash_file('sha256',$file) . "\n";
