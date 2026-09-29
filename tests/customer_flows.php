<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Solo la entrega local: crea cuentas QA, prueba los flujos y elimina sus datos.
require dirname(__DIR__) . '/app/utilities/vendor/autoload.php';
require dirname(__DIR__) . '/inc.core.php';
require APP_UTILITIES . 'Libs.php';
$db = \Develoweb\App\Model\Conexion::getInstance();

function callApi(string $path, ?array $data = null, ?string $token = null): array {
    $curl = curl_init('http://127.0.0.1/kloset/api/v1/' . $path);
    $headers = ['Accept: application/json'];
    if ($data !== null) $headers[] = 'Content-Type: application/json';
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 15]);
    if ($data !== null) { curl_setopt($curl, CURLOPT_POST, true); curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data)); }
    $raw = curl_exec($curl); if ($raw === false) throw new RuntimeException(curl_error($curl));
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl);
    $body = json_decode($raw, true); if (!is_array($body)) throw new RuntimeException("Invalid JSON $path ($code): $raw");
    return [$code, $body];
}
function expect(bool $condition, string $label, ?array $response = null): void {
    if (!$condition) throw new RuntimeException($label . ': ' . json_encode($response));
    echo "ok $label\n";
}
$userId = null; $otherUserId = null; $orderId = null; $variantId = null; $stockBefore = null; $cancelado = false;
try {
    [, $short] = callApi('producto?url=short-split-5');
    expect(count($short['variantes']) === 15 && count(array_filter($short['variantes'], fn($x) => (int)$x['stock_variante'] <= 0)) === 0, 'catálogo demo con todas las variantes comprables', $short);
    [, $detail] = callApi('producto?url=malla-vector-long');
    $p = $detail['producto']; $v = array_values(array_filter($detail['variantes'], fn($x) => (int)$x['stock_variante'] > 5))[0];
    $variantId = (int)$v['id_variante']; $stockBefore = (int)$v['stock_variante'];
    $email = 'qa-' . bin2hex(random_bytes(5)) . '@example.invalid'; $oldPass = bin2hex(random_bytes(8)); $newPass = bin2hex(random_bytes(9));
    [$code, $r] = callApi('auth/register', ['nombre' => 'Prueba Integral', 'correo' => $email, 'password' => $oldPass]);
    expect($code === 200 && $r['status'] === 'success', 'registro', $r); $userId = (int)$r['usuario']['id']; $token = $r['token'];
    [$code, $r] = callApi('pedidos', ['direccion' => 'Av. Prueba 123', 'ciudad' => 'Arequipa'], $token);
    expect($code === 422, 'checkout manual fuera de cobertura rechazado', $r);
    [, $other] = callApi('auth/register', ['nombre' => 'Prueba aislamiento', 'correo' => 'otra-' . $email, 'password' => $oldPass]);
    expect($other['status'] === 'success', 'segunda cuenta QA', $other);
    $otherUserId = (int)$other['usuario']['id']; $otherToken = $other['token'];
    [, $r] = callApi('cuenta/datos', ['nombre' => 'Prueba Actualizada', 'telefono' => '999000111', 'avisos_pedidos' => true, 'avisos_novedades' => false], $token);
    expect($r['status'] === 'success' && $r['datos']['telefono'] === '999000111', 'datos cuenta', $r);
    [, $r] = callApi('direcciones', ['nombre' => 'Casa', 'tipo' => 'Casa', 'direccion' => 'Av. Prueba 123', 'ciudad' => 'Miraflores', 'departamento' => 'Lima Metropolitana', 'principal' => true], $token);
    expect($r['status'] === 'success' && count($r['direcciones']) === 1 && $r['direcciones'][0]['tipo'] === 'Casa' && $r['direcciones'][0]['departamento'] === 'Lima Metropolitana', 'primera dirección y cobertura', $r); $addr1 = (int)$r['id'];
    [$code, $r] = callApi('direcciones', ['nombre' => 'Fuera de cobertura', 'tipo' => 'Casa', 'direccion' => 'Av. Prueba 999', 'ciudad' => 'Arequipa', 'departamento' => 'Lima Metropolitana'], $token);
    expect($code === 422, 'distrito fuera de cobertura rechazado', $r);
    [$code, $r] = callApi('direcciones', ['id' => $addr1, 'nombre' => 'Intento ajeno', 'direccion' => 'Av. Prueba 999', 'ciudad' => 'Lince'], $otherToken);
    expect($code === 404, 'dirección ajena protegida', $r);
    [, $r] = callApi('direcciones', ['nombre' => 'Oficina', 'tipo' => 'Trabajo', 'direccion' => 'Jr. Integración 456', 'ciudad' => 'Lince', 'departamento' => 'Lima Metropolitana', 'principal' => false], $token);
    expect($r['status'] === 'success' && count($r['direcciones']) === 2, 'segunda dirección', $r); $addr2 = (int)$r['id'];
    [, $r] = callApi('direcciones/principal', ['id' => $addr2], $token);
    expect($r['status'] === 'success' && (int)$r['direcciones'][0]['id'] === $addr2, 'dirección principal', $r);
    [, $r] = callApi('favoritos/alternar', ['id_producto' => (int)$p['id_producto']], $token);
    expect($r['status'] === 'success' && $r['favorito'] === true, 'guardar favorito', $r);
    [, $r] = callApi('favoritos', null, $token);
    expect($r['status'] === 'success' && count($r['productos']) === 1, 'listar favoritos', $r);
    [, $r] = callApi('carrito/agregar', ['id_producto' => (int)$p['id_producto'], 'talla' => $v['talla_variante'], 'corte' => $v['corte_variante'], 'cantidad' => 1], $token);
    expect($r['status'] === 'success' && count($r['items']) === 1, 'agregar bolsa', $r); $itemId = (int)$r['items'][0]['id_carrito_item'];
    [$code, $r] = callApi('carrito/cantidad', ['id_carrito_item' => $itemId, 'cantidad' => 3], $otherToken);
    expect($code === 404, 'bolsa ajena protegida', $r);
    [, $r] = callApi('carrito/guardar', ['id_carrito_item' => $itemId], $token);
    expect($r['status'] === 'success' && count($r['items']) === 0, 'guardar para después', $r);
    [, $r] = callApi('carrito/guardados', null, $token);
    expect($r['status'] === 'success' && count($r['items']) === 1, 'listar guardados', $r);
    [, $r] = callApi('carrito/restaurar', ['id_variante' => $variantId], $token);
    expect($r['status'] === 'success' && count($r['items']) === 1, 'recuperar guardado', $r);
    $itemId = (int)$r['items'][0]['id_carrito_item'];
    [, $r] = callApi('carrito/cantidad', ['id_carrito_item' => $itemId, 'cantidad' => 2], $token);
    expect($r['status'] === 'success' && (int)$r['items'][0]['cantidad_carrito_item'] === 2, 'cambiar cantidad', $r);
    [, $r] = callApi('pedidos', ['id_direccion' => $addr2, 'marca_tarjeta' => 'Visa', 'ultimos_digitos' => '4242'], $token);
    expect($r['status'] === 'success', 'checkout dirección guardada', $r); $orderId = (int)$r['pedido']['id_pedido'];
    [$code, $r] = callApi('pedido?id=' . $orderId, null, $otherToken);
    expect($code === 404, 'pedido ajeno protegido', $r);
    [, $r] = callApi('pedido?id=' . $orderId, null, $token);
    expect($r['status'] === 'success' && $r['pedido']['direccion_envio_cliente'] === 'Jr. Integración 456' && count($r['pedido']['historial']) === 1, 'detalle e historial', $r);
    [, $r] = callApi('direcciones', ['id' => $addr2, 'nombre' => 'Oficina actualizada', 'tipo' => 'Trabajo', 'direccion' => 'Jr. Nueva 789', 'ciudad' => 'Lince', 'departamento' => 'Lima Metropolitana', 'principal' => true], $token);
    expect($r['status'] === 'success' && (int)$r['id'] !== $addr2 && $r['direcciones'][0]['tipo'] === 'Trabajo', 'editar preserva dirección histórica', $r);
    [, $r] = callApi('pedido?id=' . $orderId, null, $token);
    expect($r['pedido']['direccion_envio_cliente'] === 'Jr. Integración 456', 'snapshot de entrega', $r);
    [, $r] = callApi('direcciones/eliminar', ['id' => $addr1], $token);
    expect($r['status'] === 'success' && count($r['direcciones']) === 1, 'quitar dirección', $r);
    [, $r] = callApi('medidas', ['estatura' => 168, 'pecho' => 98, 'cintura' => 74, 'cadera' => 98, 'corte' => 'Regular'], $token);
    expect($r['status'] === 'success', 'guardar medidas', $r);
    [, $r] = callApi('medidas/eliminar', [], $token);
    expect($r['status'] === 'success', 'borrar medidas', $r);
    [, $r] = callApi('cuenta/clave', ['actual' => $oldPass, 'nueva' => $newPass], $token);
    expect($r['status'] === 'success', 'cambiar contraseña', $r);
    [, $r] = callApi('auth/login', ['correo' => $email, 'password' => $newPass]);
    expect($r['status'] === 'success', 'entrar con nueva contraseña', $r);
    [$code, $r] = callApi('pedido?id=' . $orderId);
    expect($code === 401, 'detalle privado sin sesión', $r);
    $currentStock = (int)$db->query('SELECT stock_variante FROM productos_variantes WHERE id_variante = ' . $variantId)->fetchColumn();
    expect($currentStock === $stockBefore - 2, 'stock descontado');
    [, $r] = callApi('pedidos/cancelar', ['id' => $orderId], $token);
    expect($r['status'] === 'success', 'anulación del cliente', $r);
    $cancelado = true;
    $currentStock = (int)$db->query('SELECT stock_variante FROM productos_variantes WHERE id_variante = ' . $variantId)->fetchColumn();
    expect($currentStock === $stockBefore, 'stock restituido al cancelar');
    [$code, $r] = callApi('pedidos/cancelar', ['id' => $orderId], $token);
    expect($code === 422 && $r['status'] === 'error', 'doble anulación rechazada', $r);
} finally {
    if ($otherUserId) $db->prepare('DELETE FROM sistema_usuarios WHERE id_usuario_sistema = ?')->execute([$otherUserId]);
    if ($userId) {
        $db->beginTransaction();
        try {
            if ($orderId) {
                foreach (['pagos','pedidos_items','historial_estados_pedidos'] as $table) $db->prepare("DELETE FROM $table WHERE id_pedido = ?")->execute([$orderId]);
                $db->prepare('DELETE FROM notificaciones WHERE id_pedido = ?')->execute([$orderId]);
                $db->prepare('DELETE FROM pedidos WHERE id_pedido = ?')->execute([$orderId]);
            }
            $db->prepare('DELETE FROM favoritos_clientes WHERE id_usuario_sistema = ?')->execute([$userId]);
            $db->prepare('DELETE FROM prendas_guardadas_clientes WHERE id_usuario_sistema = ?')->execute([$userId]);
            $db->prepare('DELETE FROM perfiles_corporales WHERE id_usuario_sistema = ?')->execute([$userId]);
            $db->prepare('DELETE ci FROM carritos_items ci INNER JOIN carritos c ON c.id_carrito = ci.id_carrito WHERE c.id_usuario_sistema = ?')->execute([$userId]);
            $db->prepare('DELETE FROM carritos WHERE id_usuario_sistema = ?')->execute([$userId]);
            $db->prepare('DELETE FROM direcciones_envio_clientes WHERE id_usuario_sistema = ?')->execute([$userId]);
            $db->prepare('DELETE FROM notificaciones WHERE id_usuario_sistema = ?')->execute([$userId]);
            $db->prepare('DELETE FROM sistema_usuarios WHERE id_usuario_sistema = ?')->execute([$userId]);
            if ($orderId && $variantId && !$cancelado) $db->prepare('UPDATE productos_variantes SET stock_variante = stock_variante + 2 WHERE id_variante = ?')->execute([$variantId]);
            $db->commit(); echo "datos de prueba retirados\n";
        } catch (Throwable $e) { $db->rollBack(); throw $e; }
    }
}
