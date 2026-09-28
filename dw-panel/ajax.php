<?php
// Endpoint del panel KLOSET: acciones explícitas, sesión y ACL vigentes.
error_reporting(E_ALL);
ini_set('display_errors', '0');
require_once '../app/utilities/vendor/autoload.php';
require 'inc.app.top.php';
header('Content-Type: application/json; charset=utf-8');
function responderPanel(array $body, int $code): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (\Develoweb\App\Model\Acl::usuario() === null) responderPanel(['status'=>'error','message'=>'Sesión expirada'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responderPanel(['status'=>'error','message'=>'Usa POST para las acciones del panel'], 405);
if (!hash_equals($_SESSION['csrf_panel'], (string)($_POST['csrf'] ?? ''))) responderPanel(['status'=>'error','message'=>'Recarga el panel antes de continuar'], 403);
$acciones = [
    'Categorias' => ['categorias', ['getById','store','updateCategoria','deleteCategoria']],
    'Productos' => ['productos', ['getById','store','updateProducto','deleteProducto','uploadImagenes','deleteImagen']],
    'Variantes' => ['variantes', ['getById','store','updateVariante','deleteVariante']],
    'PedidosAdmin' => ['pedidos', ['getById','cambiarEstado']],
    'Pagos' => ['pagos', ['getById']],
    'Clientes' => ['clientes', ['getById']],
    'Notificaciones' => ['notificaciones', ['marcarLeida','marcarTodasLeidas']],
    'Usuarios' => ['usuarios', ['getById','store','updateSecciones']],
    'Roles' => ['roles', ['getById','store','updateRol','deleteRol']],
    'Configuracion' => ['configuracion', ['saveAll']],
];
$nombre = (string)($_POST['class'] ?? '');
$metodo = (string)($_POST['method'] ?? '');
if (!isset($acciones[$nombre]) || !in_array($metodo, $acciones[$nombre][1], true)) responderPanel(['status'=>'error','message'=>'Acción no habilitada en KLOSET'], 400);
if ($error = \Develoweb\App\Model\Acl::guard($acciones[$nombre][0])) responderPanel($error, 403);
try {
    $clase = MODEL_NAMESPACE . $nombre;
    $resultado = $clase::$metodo();
    responderPanel($resultado, ($resultado['status'] ?? '') === 'error' ? 422 : 200);
} catch (Throwable $e) {
    error_log('KLOSET panel ' . $nombre . '/' . $metodo . ': ' . $e->getMessage());
    responderPanel(['status'=>'error','message'=>'No se pudo completar la operación. Revisa los datos y vuelve a intentar.'], 500);
}
