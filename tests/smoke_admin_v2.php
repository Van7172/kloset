<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/utilities/vendor/autoload.php';
require dirname(__DIR__).'/inc.core.php';
require APP_UTILITIES.'Libs.php';

use Develoweb\App\Model\Acl;
use Develoweb\App\Model\Clientes;
use Develoweb\App\Model\Colores;
use Develoweb\App\Model\Dashboard;
use Develoweb\App\Model\Notificaciones;
use Develoweb\App\Model\Pagos;
use Develoweb\App\Model\PedidosAdmin;
use Develoweb\App\Model\Productos;
use Develoweb\App\Model\Usuario;
use Develoweb\App\Model\Variantes;
use Develoweb\App\Model\Sesion;

$db = Develoweb\App\Model\Conexion::getInstance();
$adminId = (int)$db->query("SELECT u.id_usuario_sistema FROM sistema_usuarios u JOIN sistema_roles r ON r.id_rol=u.id_rol WHERE r.nombre_rol='Administrador' AND u.estado_usuario_sistema='activo' LIMIT 1")->fetchColumn();
if (!$adminId) throw new RuntimeException('Se necesita un administrador activo para la prueba');
$admin = new Usuario($adminId);
$admin->setLogeado(true);
$_SESSION['usuario'] = $admin;
if (!Acl::allows('dashboard') || Acl::allows('roles') || !Acl::allows('usuarios')) throw new RuntimeException('ACL incorrecta');

Dashboard::kpis(); Dashboard::estadoStats(); Dashboard::stockCritico(); Dashboard::actividad();
Clientes::get(); Notificaciones::get(); Notificaciones::alertas(); Pagos::get();
$productos = Productos::get();
$variantes = Variantes::get();
if (!$productos || !$variantes) throw new RuntimeException('Falta el catálogo');
$colores = Colores::deProducto((int)$productos[0]['id_producto']);
if (!$colores) throw new RuntimeException('Faltan colores');
$_POST['id'] = (int)$db->query('SELECT id_pedido FROM pedidos LIMIT 1')->fetchColumn();
if ($_POST['id'] && PedidosAdmin::getById()['status'] !== 'success') throw new RuntimeException('Detalle de pedido incorrecto');
$_GET['url'] = $productos[0]['url_producto'];
if (Productos::getByUrlPublic()['status'] !== 'success') throw new RuntimeException('Detalle público incorrecto');
$sesion = new Sesion($_config);
$_SESSION['csrf_panel'] = bin2hex(random_bytes(32));
if (!defined('NOMBRE_SITIO')) define('NOMBRE_SITIO','Kloset');
foreach (['dashboard','categorias','productos','variantes','pedidos','pagos','notificaciones','clientes','usuarios','configuracion','mi-cuenta'] as $ruta) {
    $_GET['param1'] = $ruta;
    ob_start();
    include dirname(__DIR__).'/dw-panel/run.php';
    $html = ob_get_clean();
    if (!str_contains($html,'kl-shell')) throw new RuntimeException('No se renderizó '.$ruta);
}
$qaProducto = null;
try {
    $categoriaId = (int)$db->query('SELECT id_categoria FROM categorias LIMIT 1')->fetchColumn();
    $sufijo = bin2hex(random_bytes(5));
    $_POST = ['nombre'=>'QA prueba '.$sufijo,'id_categoria'=>$categoriaId,'precio'=>'99.90','estado'=>'activo'];
    $creado = Productos::store();
    if ($creado['status']!=='success') throw new RuntimeException('No se creó producto: '.json_encode($creado));
    $qaProducto = (int)$creado['id'];
    $sth = $db->prepare('SELECT COUNT(*) FROM productos_variantes WHERE id_producto=?');
    $sth->execute([$qaProducto]);
    if ((int)$sth->fetchColumn()!==0) throw new RuntimeException('El producto creó variante implícita');
    $_POST = ['id_producto'=>$qaProducto,'nombre'=>'  Icey   Blue  '];
    $color = Colores::guardar();
    if ($color['status']!=='success') throw new RuntimeException('No se creó color');
    $_POST['nombre'] = 'icey blue';
    if (Colores::guardar()['status']!=='error') throw new RuntimeException('Color duplicado aceptado');
    $colorId = (int)$color['id_color'];
    $insertImagen = $db->prepare('INSERT INTO productos_imagenes (id_producto,id_color,url_imagen,orden_imagen,principal_imagen) VALUES (?,?,?,?,?)');
    $insertImagen->execute([$qaProducto,$colorId,'qa-a-'.$sufijo.'.png',1,1]);
    $primeraImagen = (int)$db->lastInsertId();
    $insertImagen->execute([$qaProducto,$colorId,'qa-b-'.$sufijo.'.png',2,0]);
    $segundaImagen = (int)$db->lastInsertId();
    $_POST = ['id_imagen'=>$segundaImagen,'orden'=>1,'principal'=>1];
    if (Colores::ordenar()['status']!=='success') throw new RuntimeException('No se ordenó la galería');
    $sth = $db->prepare('SELECT orden_imagen,principal_imagen FROM productos_imagenes WHERE id_imagen=?');
    $sth->execute([$segundaImagen]);
    if ($sth->fetch() != ['orden_imagen'=>1,'principal_imagen'=>1]) throw new RuntimeException('Orden o principal incorrecto');
    $_POST = ['id_imagen'=>$segundaImagen];
    if (Colores::eliminarImagen()['status']!=='success') throw new RuntimeException('No se eliminó la imagen');
    $sth = $db->prepare('SELECT principal_imagen FROM productos_imagenes WHERE id_imagen=?');
    $sth->execute([$primeraImagen]);
    if ((int)$sth->fetchColumn()!==1) throw new RuntimeException('La galería quedó sin imagen principal');
    $_POST = ['id_color'=>$colorId,'nombre'=>'  Midnight   Navy  '];
    if (Colores::renombrar()['status']!=='success') throw new RuntimeException('No se renombró el color');
    $sth = $db->prepare('SELECT nombre_color,nombre_clave FROM productos_colores WHERE id_color=?');
    $sth->execute([$colorId]);
    if ($sth->fetch() != ['nombre_color'=>'Midnight Navy','nombre_clave'=>'midnight navy']) throw new RuntimeException('Nombre de color no normalizado');
    $_POST = ['id_producto'=>$qaProducto,'id_color'=>$colorId,'talla'=>'M','corte'=>'No aplica','sku'=>'QA'.$sufijo.'1','stock'=>'10'];
    if (Variantes::store()['status']!=='success') throw new RuntimeException('No se creó variante');
    $_POST['sku'] = 'QA'.$sufijo.'2';
    if (Variantes::store()['status']!=='error') throw new RuntimeException('Combinación duplicada aceptada');
    $_POST['talla']='L'; $_POST['stock']='15';
    if (Variantes::store()['status']!=='success') throw new RuntimeException('No se creó segunda variante');
    $_POST=['id'=>$qaProducto];
    $detalle = Productos::getById();
    if ((int)$detalle['producto']['stock_total']!==25) throw new RuntimeException('Stock total incorrecto');
    if ($_POST['id'] && ($pedidoId=(int)$db->query('SELECT id_pedido FROM pedidos LIMIT 1')->fetchColumn())) {
        $_POST=['id'=>$pedidoId,'estado'=>'cancelado'];
        if (PedidosAdmin::cambiarEstado()['status']!=='error') throw new RuntimeException('Cancelación administrativa aceptada');
        $_POST=['id'=>$pedidoId,'estado'=>'entregado'];
        if (PedidosAdmin::cambiarEstado()['status']!=='error') throw new RuntimeException('Salto de estado aceptado');
    }
} finally {
    if ($qaProducto) { $_POST=['id'=>$qaProducto]; $borrado=Productos::deleteProducto(); if ($borrado['status']!=='success') throw new RuntimeException('No se limpió producto QA'); }
}
$clienteId = (int)$db->query("SELECT u.id_usuario_sistema FROM sistema_usuarios u JOIN sistema_roles r ON r.id_rol=u.id_rol WHERE r.nombre_rol='Cliente' LIMIT 1")->fetchColumn();
if ($clienteId) {
    $cliente = new Usuario($clienteId);
    $cliente->setLogeado(true);
    $_SESSION['usuario'] = $cliente;
    if (Acl::allows('dashboard') || Acl::allows('productos')) throw new RuntimeException('Cliente con acceso administrativo');
}
echo "OK: consultas, catálogo, pedidos y ACL\n";
