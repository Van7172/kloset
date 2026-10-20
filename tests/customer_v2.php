<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__).'/app/utilities/vendor/autoload.php';
require dirname(__DIR__).'/inc.core.php';
require APP_UTILITIES.'Libs.php';

use Develoweb\App\Model\Acl;
use Develoweb\App\Model\Carritos;
use Develoweb\App\Model\CodigoEntrega;
use Develoweb\App\Model\Colores;
use Develoweb\App\Model\Conexion;
use Develoweb\App\Model\Pedidos;
use Develoweb\App\Model\PedidosAdmin;
use Develoweb\App\Model\Productos;
use Develoweb\App\Model\Usuario;
use Develoweb\App\Model\Variantes;
use Develoweb\App\Utilities\JwtHelper;

function comprobar(bool $ok,string $mensaje): void { if (!$ok) throw new RuntimeException($mensaje); }
$db = Conexion::getInstance();
$rol = (int)$db->query("SELECT id_rol FROM sistema_roles WHERE nombre_rol='Cliente'")->fetchColumn();
$adminId = (int)$db->query("SELECT u.id_usuario_sistema FROM sistema_usuarios u JOIN sistema_roles r ON r.id_rol=u.id_rol WHERE r.nombre_rol='Administrador' AND u.estado_usuario_sistema='activo' LIMIT 1")->fetchColumn();
$categoriaId = (int)$db->query("SELECT id_categoria FROM categorias WHERE estado_categoria='activo' LIMIT 1")->fetchColumn();
comprobar($rol>0 && $adminId>0 && $categoriaId>0,'Faltan datos para probar');
$usuarioId = 0; $qaProducto = 0; $ordenes = []; $restaurar = [];
try {
    $admin=new Usuario($adminId); $admin->setLogeado(true); $_SESSION['usuario']=$admin;
    $_POST=['nombre'=>'QA SKU pedido '.bin2hex(random_bytes(6)),'id_categoria'=>$categoriaId,'precio'=>'99.90','estado'=>'activo'];
    $producto=Productos::store();
    comprobar($producto['status']==='success','No se creó producto temporal');
    $qaProducto=(int)$producto['id'];
    $_POST=['id_producto'=>$qaProducto,'nombre'=>'QA Azul'];
    $color=Colores::guardar();
    comprobar($color['status']==='success','No se creó color temporal');
    $_POST=['id_producto'=>$qaProducto,'id_color'=>$color['id_color'],'talla'=>'M','corte'=>'Regular','stock'=>'10'];
    $variante=Variantes::store();
    comprobar($variante['status']==='success','No se creó variante con SKU automático');
    $varianteId=(int)$variante['id'];
    $sku=$variante['sku'];
    $stockInicial=10;
    $correo = 'qa-pedido-'.bin2hex(random_bytes(6)).'@example.invalid';
    $db->prepare('INSERT INTO sistema_usuarios (id_rol,nombre_usuario_sistema,correo_usuario_sistema,contrasena_usuario_sistema) VALUES (?,?,?,?)')
        ->execute([$rol,'QA Pedidos',$correo,password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);
    $usuarioId = (int)$db->lastInsertId();
    $token = JwtHelper::encode(['sub'=>$usuarioId],$_config['jwt']['secret'],3600);
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
    $crear = static function() use ($varianteId,&$ordenes): int {
        $_POST=['id_variante'=>$varianteId,'cantidad'=>1];
        comprobar(Carritos::agregar()['status']==='success','No se añadió variante a bolsa');
        $_POST=['direccion'=>'Av. Prueba QA 123','ciudad'=>'Miraflores','numero_simulado'=>'4000000000000002'];
        comprobar(Pedidos::crear()['status']==='error','Se confirmó pago rechazado');
        $_POST['numero_simulado']='4242424242424242';
        $pedido=Pedidos::crear();
        comprobar($pedido['status']==='success','No se confirmó pago simulado: '.json_encode($pedido));
        $id=(int)$pedido['pedido']['id_pedido'];
        $ordenes[]=$id;
        return $id;
    };

    $primero=$crear();
    $sth=$db->prepare('SELECT sku_compra FROM pedidos_items WHERE id_pedido=?');
    $sth->execute([$primero]);
    comprobar($sth->fetchColumn()===$sku,'El pedido no conservó el SKU automático');
    $_POST=['id'=>$varianteId,'id_producto'=>$qaProducto,'id_color'=>$color['id_color'],'talla'=>'M','corte'=>'Regular','stock'=>'9','sku'=>'MANUAL123'];
    comprobar(Variantes::updateVariante()['status']==='success','No se pudo editar stock de una variante vendida');
    $_POST=['id'=>$varianteId];
    comprobar(Variantes::getById()['variante']['sku_variante']===$sku,'La edición cambió el SKU de una variante vendida');
    $sth=$db->prepare('SELECT estado_pedido,fecha_pago_aprobado,entrega_estimada_pedido FROM pedidos WHERE id_pedido=?');
    $sth->execute([$primero]); $fila=$sth->fetch();
    comprobar($fila['estado_pedido']==='pagado' && $fila['entrega_estimada_pedido']===(new DateTime($fila['fecha_pago_aprobado']))->modify('+5 days')->format('Y-m-d'),'Fecha o estado de pago incorrectos');
    $_POST=['id'=>$primero];
    comprobar(Pedidos::cancelar()['status']==='success','No se canceló pedido pagado');
    comprobar(Pedidos::cancelar()['status']==='success','Cancelación repetida no fue idempotente');
    $sth=$db->prepare('SELECT stock_variante FROM productos_variantes WHERE id_variante=?');
    $sth->execute([$varianteId]);
    comprobar((int)$sth->fetchColumn()===$stockInicial,'Stock no se restauró exactamente una vez');
    $sth=$db->prepare("SELECT COUNT(*) FROM historial_estados_pedidos WHERE id_pedido=? AND estado_historial_estado_pedido='cancelado'");
    $sth->execute([$primero]); comprobar((int)$sth->fetchColumn()===1,'Historial de cancelación duplicado');

    $segundo=$crear(); $restaurar[]=$segundo;
    $admin=new Usuario($adminId); $admin->setLogeado(true); $_SESSION['usuario']=$admin;
    $_POST=['id'=>$segundo,'estado'=>'cancelado']; comprobar(PedidosAdmin::cambiarEstado()['status']==='error','Administrador canceló pedido');
    $_POST=['id'=>$segundo,'estado'=>'enviado']; comprobar(PedidosAdmin::cambiarEstado()['status']==='error','Salto de estado permitido');
    foreach ([['en_preparacion',[]],['enviado',['transportista'=>'QA Courier','seguimiento'=>'']],['en_reparto',[]]] as [$estado,$extra]) {
        $_POST=['id'=>$segundo,'estado'=>$estado]+$extra;
        comprobar(PedidosAdmin::cambiarEstado()['status']==='success','Fallo en transición '.$estado);
    }
    $_SERVER['HTTP_AUTHORIZATION']='Bearer '.$token;
    $_GET['id']=$segundo;
    $codigo=Pedidos::codigoEntrega()['codigo'] ?? null;
    comprobar(is_string($codigo) && preg_match('/^\d{8}$/',$codigo),'Código privado ausente');
    $_SESSION['usuario']=$admin;
    $_POST=['id'=>$segundo,'estado'=>'entregado','codigo_entrega'=>'00000000'];
    if ($codigo==='00000000') $_POST['codigo_entrega']='11111111';
    comprobar(PedidosAdmin::cambiarEstado()['status']==='error','Código incorrecto aceptado');
    $_POST['codigo_entrega']=$codigo;
    comprobar(PedidosAdmin::cambiarEstado()['status']==='success','Código correcto rechazado');
    $_SERVER['HTTP_AUTHORIZATION']='Bearer '.$token;
    comprobar(Pedidos::codigoEntrega()['codigo']===null,'Código reutilizable tras entrega');
    echo "OK: SKU automático en pedido, pago, cancelación idempotente, transiciones y código de entrega\n";
} finally {
    foreach ($restaurar as $id) {
        $db->prepare('UPDATE productos_variantes v JOIN pedidos_items pi ON pi.id_variante=v.id_variante SET v.stock_variante=v.stock_variante+pi.cantidad_pedido_item WHERE pi.id_pedido=?')->execute([$id]);
    }
    foreach ($ordenes as $id) {
        $db->prepare('DELETE FROM pagos WHERE id_pedido=?')->execute([$id]);
        $db->prepare('DELETE FROM notificaciones WHERE id_pedido=?')->execute([$id]);
        $db->prepare('DELETE FROM pedidos WHERE id_pedido=?')->execute([$id]);
    }
    if ($usuarioId) $db->prepare('DELETE FROM sistema_usuarios WHERE id_usuario_sistema=?')->execute([$usuarioId]);
    if ($qaProducto) {
        $_SESSION['usuario']=$admin; $_POST=['id'=>$qaProducto];
        comprobar(Productos::deleteProducto()['status']==='success','No se retiró el catálogo temporal');
    }
}
