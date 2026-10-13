<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/utilities/vendor/autoload.php';
require dirname(__DIR__) . '/inc.core.php';
require APP_UTILITIES . 'Libs.php';
if (DB_DATABASE !== 'kloset_bd' || !in_array(DB_HOST, ['localhost','127.0.0.1'], true)) throw new RuntimeException('Solo base local');
$db = \Develoweb\App\Model\Conexion::getInstance();
$admin = $db->query("SELECT u.id_usuario_sistema,u.correo_usuario_sistema,u.contrasena_usuario_sistema FROM sistema_usuarios u JOIN sistema_roles r ON r.id_rol=u.id_rol WHERE r.nombre_rol='Administrador' AND u.estado_usuario_sistema='activo' LIMIT 1")->fetch();
if (!$admin) throw new RuntimeException('Falta administrador local');
$suffix = bin2hex(random_bytes(6));
$adminPass = 'Qa!1' . bin2hex(random_bytes(12));
$userPass = 'Qa!1' . bin2hex(random_bytes(12));
$advisorEmail = "qa-browser-asesor-$suffix@example.invalid";
$clientEmail = "qa-browser-cliente-$suffix@example.invalid";
$exitCode = 1;
try {
    $db->prepare('UPDATE sistema_usuarios SET contrasena_usuario_sistema=? WHERE id_usuario_sistema=?')->execute([password_hash($adminPass,PASSWORD_DEFAULT),(int)$admin['id_usuario_sistema']]);
    putenv('QA_ADMIN_EMAIL=' . $admin['correo_usuario_sistema']);
    putenv('QA_ADMIN_PASS=' . $adminPass);
    putenv('QA_USER_PASS=' . $userPass);
    putenv('QA_ADVISOR_EMAIL=' . $advisorEmail);
    putenv('QA_CLIENT_EMAIL=' . $clientEmail);
    $node = 'C:\\Users\\ADMIN\\.cache\\codex-runtimes\\codex-primary-runtime\\dependencies\\node\\bin\\node.exe';
    $process = proc_open([$node,__DIR__ . '/usuarios_panel_browser.cjs'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
    if (!is_resource($process)) throw new RuntimeException('No se pudo iniciar Playwright');
    fclose($pipes[0]);
    echo stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    if ($errors !== '') fwrite(STDERR,$errors);
    fclose($pipes[1]); fclose($pipes[2]);
    $exitCode = proc_close($process);
} finally {
    $db->prepare('UPDATE sistema_usuarios SET contrasena_usuario_sistema=? WHERE id_usuario_sistema=?')->execute([$admin['contrasena_usuario_sistema'],(int)$admin['id_usuario_sistema']]);
    $delete = $db->prepare('DELETE FROM sistema_usuarios WHERE correo_usuario_sistema IN (?,?)');
    $delete->execute([$advisorEmail,$clientEmail]);
}
exit($exitCode);
