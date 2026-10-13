<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require dirname(__DIR__).'/app/utilities/vendor/autoload.php';
require dirname(__DIR__).'/inc.core.php';
require APP_UTILITIES.'Libs.php';

$db = \Develoweb\App\Model\Conexion::getInstance();
$admin = $db->query("SELECT u.id_usuario_sistema,u.correo_usuario_sistema,u.contrasena_usuario_sistema
    FROM sistema_usuarios u JOIN sistema_roles r ON r.id_rol=u.id_rol
    WHERE r.nombre_rol='Administrador' AND u.estado_usuario_sistema='activo' LIMIT 1")->fetch();
$clienteRol = (int)$db->query("SELECT id_rol FROM sistema_roles WHERE nombre_rol='Cliente' AND estado_rol='activo' LIMIT 1")->fetchColumn();
if (!$admin || !$clienteRol) throw new RuntimeException('Falta el administrador o el rol Cliente');

$clienteId = 0;
$adminPassword = 'Qa!1'.bin2hex(random_bytes(12));
$clientePassword = 'Qa!1'.bin2hex(random_bytes(12));
$clienteEmail = 'qa-browser-'.bin2hex(random_bytes(6)).'@example.invalid';
$qaSlug = 'qa-browser-'.bin2hex(random_bytes(5));
$exitCode = 1;

try {
    $db->prepare('INSERT INTO sistema_usuarios (id_rol,nombre_usuario_sistema,correo_usuario_sistema,contrasena_usuario_sistema)
        VALUES (?,?,?,?)')->execute([$clienteRol,'QA Navegador',$clienteEmail,password_hash($clientePassword,PASSWORD_DEFAULT)]);
    $clienteId = (int)$db->lastInsertId();
    $db->prepare('UPDATE sistema_usuarios SET contrasena_usuario_sistema=? WHERE id_usuario_sistema=?')
        ->execute([password_hash($adminPassword,PASSWORD_DEFAULT),(int)$admin['id_usuario_sistema']]);

    $node = 'C:\\Users\\ADMIN\\.cache\\codex-runtimes\\codex-primary-runtime\\dependencies\\node\\bin\\node.exe';
    putenv('KLOSET_BROWSER_ADMIN_EMAIL='.$admin['correo_usuario_sistema']);
    putenv('KLOSET_BROWSER_ADMIN_PASSWORD='.$adminPassword);
    putenv('KLOSET_BROWSER_CLIENT_EMAIL='.$clienteEmail);
    putenv('KLOSET_BROWSER_CLIENT_PASSWORD='.$clientePassword);
    putenv('KLOSET_BROWSER_QA_SLUG='.$qaSlug);
    $process = proc_open([$node,__DIR__.'/browser_v2.cjs'], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, dirname(__DIR__));
    if (!is_resource($process)) throw new RuntimeException('No se pudo iniciar Playwright');
    fclose($pipes[0]);
    echo stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    if ($errors !== '') fwrite(STDERR,$errors);
    fclose($pipes[1]); fclose($pipes[2]);
    $exitCode = proc_close($process);
} finally {
    $db->prepare('UPDATE sistema_usuarios SET contrasena_usuario_sistema=? WHERE id_usuario_sistema=?')
        ->execute([$admin['contrasena_usuario_sistema'],(int)$admin['id_usuario_sistema']]);
    if ($clienteId) {
        $orders = $db->prepare('SELECT id_pedido,estado_pedido FROM pedidos WHERE id_usuario_sistema=?');
        $orders->execute([$clienteId]);
        foreach ($orders->fetchAll() as $order) {
            $id = (int)$order['id_pedido'];
            if ($order['estado_pedido'] !== 'cancelado') {
                $db->prepare('UPDATE productos_variantes v JOIN pedidos_items pi ON pi.id_variante=v.id_variante
                    SET v.stock_variante=v.stock_variante+pi.cantidad_pedido_item WHERE pi.id_pedido=?')->execute([$id]);
            }
            $db->prepare('DELETE FROM pagos WHERE id_pedido=?')->execute([$id]);
            $db->prepare('DELETE FROM notificaciones WHERE id_pedido=?')->execute([$id]);
            $db->prepare('DELETE FROM pedidos WHERE id_pedido=?')->execute([$id]);
        }
        $db->prepare('DELETE FROM sistema_usuarios WHERE id_usuario_sistema=?')->execute([$clienteId]);
    }
    $producto = $db->prepare('SELECT id_producto FROM productos WHERE url_producto=?');
    $producto->execute([$qaSlug]);
    if ($idProducto = (int)$producto->fetchColumn()) {
        $usuario = new \Develoweb\App\Model\Usuario((int)$admin['id_usuario_sistema']);
        $usuario->setLogeado(true);
        $_SESSION['usuario'] = $usuario;
        $_POST = ['id'=>$idProducto];
        $borrado = \Develoweb\App\Model\Productos::deleteProducto();
        if (($borrado['status'] ?? '') !== 'success') throw new RuntimeException('No se pudo limpiar el producto QA: '.json_encode($borrado));
    }
    $clave = $db->prepare('SELECT contrasena_usuario_sistema FROM sistema_usuarios WHERE id_usuario_sistema=?');
    $clave->execute([(int)$admin['id_usuario_sistema']]);
    if (!hash_equals($admin['contrasena_usuario_sistema'],(string)$clave->fetchColumn())) {
        throw new RuntimeException('No se restauró la clave administrativa original');
    }
}
exit($exitCode);
