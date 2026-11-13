<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ob_start();

require dirname(__DIR__) . '/app/utilities/vendor/autoload.php';
require dirname(__DIR__) . '/inc.core.php';
require APP_UTILITIES . 'Libs.php';

use Develoweb\App\Model\Acl;
use Develoweb\App\Model\Conexion;
use Develoweb\App\Model\Sesion;
use Develoweb\App\Model\Usuario;
use Develoweb\App\Model\Usuarios;

if (DB_DATABASE !== 'kloset_bd' || !in_array(DB_HOST, ['localhost','127.0.0.1'], true)) throw new RuntimeException('Solo base local');
$db = Conexion::getInstance();
$adminId = (int) $db->query("SELECT u.id_usuario_sistema FROM sistema_usuarios u JOIN sistema_roles r ON r.id_rol=u.id_rol WHERE r.nombre_rol='Administrador' AND u.estado_usuario_sistema='activo'")->fetchColumn();
$roles = array_column(Usuarios::rolesDeAlta(), 'id_rol', 'nombre_rol');
$sec = array_column(Usuarios::seccionesAsesor(), 'id_seccion', 'url_seccion');
if (!$adminId || !isset($roles['Asesor'],$roles['Cliente'],$sec['pedidos'])) throw new RuntimeException('Faltan datos base');

session_start();
$_SESSION['sesion'] = new Sesion();
$admin = new Usuario($adminId);
$admin->setLogeado(true);
$_SESSION['usuario'] = $admin;
$ids = [];
$suffix = bin2hex(random_bytes(7));
$password = 'Qa!1' . bin2hex(random_bytes(8));
$advisorEmail = "qa-asesor-$suffix@example.invalid";
$clientEmail = "qa-cliente-$suffix@example.invalid";
function expect(bool $condition, string $label): void { if (!$condition) throw new RuntimeException($label); echo "OK $label\n"; }

try {
    $_POST = ['nombre'=>'Asesor QA','correo'=>$advisorEmail,'password'=>$password,'id_rol'=>$roles['Asesor'],'secciones'=>[$sec['pedidos']]];
    $advisor = Usuarios::store();
    expect(($advisor['status'] ?? '') === 'success', 'Crear asesor');
    $ids[] = (int) $advisor['id'];

    $_POST = ['nombre'=>'Cliente QA','correo'=>$clientEmail,'password'=>$password,'id_rol'=>$roles['Cliente']];
    $client = Usuarios::store();
    expect(($client['status'] ?? '') === 'success', 'Crear cliente');
    $ids[] = (int) $client['id'];

    $_POST = ['nombre'=>'Otro Admin','correo'=>"qa-admin-$suffix@example.invalid",'password'=>$password,'id_rol'=>1];
    expect((Usuarios::store()['status'] ?? '') === 'error', 'Rechazar segundo administrador');

    $_POST = ['nombre'=>'Asesor indebido','correo'=>"qa-indebido-$suffix@example.invalid",'password'=>$password,'id_rol'=>$roles['Asesor'],'secciones'=>[6]];
    expect((Usuarios::store()['status'] ?? '') === 'error', 'Rechazar permiso Usuarios al asesor');

    $_SESSION['usuario'] = new Usuario((int) $advisor['id']);
    $_SESSION['sesion'] = new Sesion();
    $_POST = ['username'=>$advisorEmail,'password'=>$password];
    expect(Sesion::signIn() === true, 'Login asesor');
    expect(Acl::allows('pedidos') && !Acl::allows('usuarios') && !Acl::allows('configuracion'), 'ACL asesor');

    $_SESSION['usuario'] = new Usuario((int) $client['id']);
    $_SESSION['sesion'] = new Sesion();
    $_POST = ['username'=>$clientEmail,'password'=>$password];
    expect(Sesion::signIn() !== true, 'Cliente sin acceso al panel');
} finally {
    foreach ($ids as $id) {
        $db->prepare('DELETE FROM sistema_usuarios WHERE id_usuario_sistema=?')->execute([$id]);
    }
    session_destroy();
    ob_end_flush();
}
