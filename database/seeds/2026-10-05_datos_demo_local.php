<?php
declare(strict_types=1);

// Ejecutar por CLI después de base_demo_local.sql, catalogo_demo.sql e imagenes_catalogo.sql.
// Crea cuentas y operaciones ficticias, sin contraseñas fijas en el repositorio.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
require $root . '/app/utilities/vendor/autoload.php';
require $root . '/inc.core.php';
require APP_UTILITIES . 'Libs.php';

use Develoweb\App\Model\Conexion;

$credentialPath = $argv[1] ?? '';
$credentialDir = $credentialPath !== '' ? realpath(dirname($credentialPath)) : false;
if ($credentialDir === false || str_starts_with(strtolower($credentialDir), strtolower($root)) || file_exists($credentialPath)) {
    throw new RuntimeException('Indica una ruta nueva para las credenciales, fuera del directorio público.');
}

global $_config;
if (($_config['db']['name'] ?? '') !== 'kloset_bd'
    || !in_array($_config['db']['host'] ?? '', ['localhost', '127.0.0.1'], true)) {
    throw new RuntimeException('Este seed solo se permite en kloset_bd local.');
}

$db = Conexion::getInstance();
if ((int) $db->query('SELECT COUNT(*) FROM sistema_usuarios')->fetchColumn() !== 0
    || (int) $db->query('SELECT COUNT(*) FROM productos')->fetchColumn() !== 8
    || (int) $db->query('SELECT COUNT(*) FROM sistema_secciones')->fetchColumn() !== 11) {
    throw new RuntimeException('Se requiere la base demo preparada y sin usuarios.');
}

function demoPassword(): string
{
    return 'K!1a-' . bin2hex(random_bytes(16));
}

function demoUser(PDO $db, int $role, string $name, string $email, string $password): int
{
    $stmt = $db->prepare('INSERT INTO sistema_usuarios (id_rol, nombre_usuario_sistema, correo_usuario_sistema, contrasena_usuario_sistema) VALUES (?, ?, ?, ?)');
    $stmt->execute([$role, $name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    return (int) $db->lastInsertId();
}

function demoAddress(PDO $db, int $user, string $name, string $street, string $district): int
{
    $stmt = $db->prepare("INSERT INTO direcciones_envio_clientes
        (id_usuario_sistema, nombre_direccion_cliente, direccion_envio_cliente, ciudad_envio_cliente, departamento_envio_cliente, predeterminada_envio_cliente)
        VALUES (?, ?, ?, ?, 'Lima Metropolitana', 1)");
    $stmt->execute([$user, $name, $street, $district]);
    return (int) $db->lastInsertId();
}

function demoVariant(PDO $db, string $slug, string $size = 'M', string $fit = 'Regular'): array
{
    $stmt = $db->prepare('SELECT v.id_variante, p.precio_producto FROM productos_variantes v
        JOIN productos p ON p.id_producto = v.id_producto
        WHERE p.url_producto = ? AND v.talla_variante = ? AND v.corte_variante = ?');
    $stmt->execute([$slug, $size, $fit]);
    $variant = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$variant) throw new RuntimeException("Falta la variante demo de $slug.");
    return $variant;
}

function demoOrder(PDO $db, int $user, int $address, array $lines, string $state, ?string $tracking): int
{
    $total = 0.0;
    $items = [];
    foreach ($lines as [$slug, $qty]) {
        $variant = demoVariant($db, $slug);
        $price = (float) $variant['precio_producto'];
        $items[] = [(int) $variant['id_variante'], (int) $qty, $price];
        $total += $price * $qty;
    }
    $money = number_format($total, 2, '.', '');
    $stmt = $db->prepare('INSERT INTO pedidos
        (id_usuario_sistema, id_direccion_envio, estado_pedido, subtotal_pedido, total_pedido, transportista_pedido, seguimiento_pedido, entrega_estimada_pedido)
        VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 3 DAY))');
    $stmt->execute([$user, $address, $state, $money, $money, $tracking ? 'Reparto Demo Kloset' : null, $tracking]);
    $order = (int) $db->lastInsertId();

    $insertItem = $db->prepare('INSERT INTO pedidos_items (id_pedido, id_variante, cantidad_pedido_item, precio_unitario_pedido_item) VALUES (?, ?, ?, ?)');
    $takeStock = $db->prepare('UPDATE productos_variantes SET stock_variante = stock_variante - ? WHERE id_variante = ? AND stock_variante >= ?');
    foreach ($items as [$variantId, $qty, $price]) {
        $insertItem->execute([$order, $variantId, $qty, number_format($price, 2, '.', '')]);
        $takeStock->execute([$qty, $variantId, $qty]);
        if ($takeStock->rowCount() !== 1) throw new RuntimeException('Stock insuficiente para el pedido demo.');
    }

    $db->prepare('INSERT INTO pagos (id_pedido, metodo_pago, marca_pago, ultimos_digitos_pago, monto_pago, estado_pago, id_transaccion_pasarela_pago)
        VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$order, 'tarjeta_demo', 'Visa', '4242', $money, 'aprobado', 'DEMO-' . strtoupper(bin2hex(random_bytes(6)))]);
    $history = $db->prepare('INSERT INTO historial_estados_pedidos (id_pedido, id_usuario_sistema, estado_historial_estado_pedido, comentario_historial_estado_pedido) VALUES (?, NULL, ?, ?)');
    foreach ($state === 'enviado' ? ['pagado', 'en_preparacion', 'enviado'] : ['pagado', 'en_preparacion'] as $step) {
        $history->execute([$order, $step, 'Movimiento ficticio de demostración']);
    }
    $db->prepare('INSERT INTO notificaciones (id_usuario_sistema, id_pedido, mensaje_notificacion) VALUES (?, ?, ?)')
        ->execute([$user, $order, $state === 'enviado' ? 'Tu pedido demo está en camino.' : 'Estamos preparando tu pedido demo.']);
    return $order;
}

$accounts = [
    'admin' => ['email' => 'admin.demo@kloset.test', 'password' => demoPassword()],
    'ana' => ['email' => 'ana.demo@kloset.test', 'password' => demoPassword()],
    'luis' => ['email' => 'luis.demo@kloset.test', 'password' => demoPassword()],
];

$db->beginTransaction();
try {
    $admin = demoUser($db, 1, 'Administración Demo Kloset', $accounts['admin']['email'], $accounts['admin']['password']);
    $ana = demoUser($db, 2, 'Ana Demo', $accounts['ana']['email'], $accounts['ana']['password']);
    $luis = demoUser($db, 2, 'Luis Demo', $accounts['luis']['email'], $accounts['luis']['password']);
    $db->prepare("INSERT INTO usuarios_secciones (id_usuario_sistema, id_seccion)
        SELECT ?, id_seccion FROM sistema_secciones WHERE estado_seccion = 'activo'")->execute([$admin]);

    $anaAddress = demoAddress($db, $ana, 'Casa Demo', 'Av. Javier Prado 1234', 'San Isidro');
    $luisAddress = demoAddress($db, $luis, 'Trabajo Demo', 'Av. Brasil 450', 'Pueblo Libre');
    $db->prepare("INSERT INTO perfiles_corporales
        (id_usuario_sistema, estatura_perfil_corporal, pecho_perfil_corporal, cintura_perfil_corporal, cadera_perfil_corporal, talla_recomendada_perfil_corporal)
        VALUES (?, 174, 98, 84, 99, 'M')")->execute([$ana]);
    $db->prepare('INSERT INTO favoritos_clientes (id_usuario_sistema, id_producto)
        SELECT ?, id_producto FROM productos WHERE url_producto = ?')->execute([$ana, 'malla-vector-long']);
    $db->prepare('INSERT INTO favoritos_clientes (id_usuario_sistema, id_producto)
        SELECT ?, id_producto FROM productos WHERE url_producto = ?')->execute([$luis, 'top-anchor-medium']);

    $db->prepare("INSERT INTO carritos (id_usuario_sistema, estado_carrito) VALUES (?, 'activo')")->execute([$luis]);
    $cart = (int) $db->lastInsertId();
    $cartVariant = demoVariant($db, 'cortavientos-draft-02');
    $db->prepare('INSERT INTO carritos_items (id_carrito, id_variante, cantidad_carrito_item) VALUES (?, ?, 1)')
        ->execute([$cart, (int) $cartVariant['id_variante']]);

    demoOrder($db, $ana, $anaAddress, [['malla-vector-long', 1], ['camiseta-tempo-dry', 1]], 'en_preparacion', null);
    demoOrder($db, $luis, $luisAddress, [['short-split-5', 2]], 'enviado', 'DEMO-ENV-0002');
    $db->prepare("UPDATE productos_variantes SET stock_variante = 4 WHERE id_producto =
        (SELECT id_producto FROM productos WHERE url_producto = 'malla-vector-long')
        AND talla_variante = 'XS' AND corte_variante = 'Slim'")->execute();

    $db->prepare("INSERT INTO contactos (tema_contacto, nombre_contacto, correo_contacto, mensaje_contacto)
        VALUES ('talla', 'Luis Demo', 'luis.demo@kloset.test', 'Consulta ficticia: necesito orientación para elegir talla.')")->execute();
    $contact = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO notificaciones (id_usuario_sistema, id_contacto, mensaje_notificacion) VALUES (?, ?, ?)')
        ->execute([$admin, $contact, 'Nueva consulta demo de Luis Demo']);

    $credentials = "KLOSET · CUENTAS FICTICIAS PARA LA INSTALACIÓN LOCAL\r\n"
        . "Panel: http://127.0.0.1/kloset/dw-panel/sign-in\r\n"
        . "Tienda: http://127.0.0.1/kloset/entrar\r\n\r\n"
        . "Administrador: {$accounts['admin']['email']} / {$accounts['admin']['password']}\r\n"
        . "Cliente Ana: {$accounts['ana']['email']} / {$accounts['ana']['password']}\r\n"
        . "Cliente Luis: {$accounts['luis']['email']} / {$accounts['luis']['password']}\r\n\r\n"
        . "Solo para la base local kloset_bd. El dominio .test no recibe correo.\r\n";
    if (file_put_contents($credentialPath, $credentials, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo guardar el archivo de credenciales.');
    }
    $db->commit();
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    if (is_file($credentialPath)) unlink($credentialPath);
    throw $error;
}

echo "Datos demo cargados: 3 usuarios, 2 pedidos, 2 pagos y 1 consulta.\n";
