<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Integración HTTP + PDO en la instalación local. Solo modifica sus registros QA.
require dirname(__DIR__) . '/app/utilities/vendor/autoload.php';
require dirname(__DIR__) . '/inc.core.php';
require APP_UTILITIES . 'Libs.php';
$db = \Develoweb\App\Model\Conexion::getInstance();
$suffix = bin2hex(random_bytes(5));
$password = 'Aa1!' . bin2hex(random_bytes(12));
$email = 'qa-panel-' . $suffix . '@example.invalid';
$ids = []; $csrf = ''; $token = null;
$unreadBefore = (int)$db->query('SELECT COUNT(*) FROM notificaciones WHERE leido_notificacion=0')->fetchColumn();
$http = curl_init();
curl_setopt_array($http, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_COOKIEFILE=>'', CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>15]);
function requestLocal(string $path, ?array $body = null, bool $json = false): array {
    global $http;
    curl_setopt($http, CURLOPT_URL, 'http://127.0.0.1/kloset/' . $path);
    curl_setopt($http, CURLOPT_HTTPHEADER, $json ? ['Content-Type: application/json'] : []);
    if ($body === null) { curl_setopt($http, CURLOPT_HTTPGET, true); }
    else { curl_setopt($http, CURLOPT_POST, true); curl_setopt($http, CURLOPT_POSTFIELDS, $json ? json_encode($body) : $body); }
    $raw = curl_exec($http);
    if ($raw === false) throw new RuntimeException(curl_error($http));
    return [(int)curl_getinfo($http, CURLINFO_HTTP_CODE), $raw];
}
function checkPanel(bool $condition, string $label, $detail = null): void {
    if (!$condition) throw new RuntimeException($label . ': ' . json_encode($detail));
    echo "ok $label\n";
}
function adminCall(string $class, string $method, array $body = [], bool $withCsrf = true): array {
    global $csrf;
    [$code,$raw] = requestLocal('dw-panel/ajax.php', array_merge($body,['class'=>$class,'method'=>$method,'csrf'=>$withCsrf?$csrf:'']));
    $res = json_decode($raw, true);
    checkPanel(is_array($res), "JSON $class/$method", substr($raw,0,200));
    return [$code,$res];
}
function successPanel(string $class, string $method, array $body = []): array {
    [$code,$res] = adminCall($class,$method,$body);
    checkPanel($code===200 && ($res['status']??'')==='success', "$class/$method", $res);
    return $res;
}
function loginPanel(): void {
    global $email,$password,$csrf;
    [$code,$html] = requestLocal('dw-panel/sign-in',['username'=>$email,'password'=>$password]);
    checkPanel($code===200 && str_contains($html,'Panel general'), 'login y consulta dashboard', substr($html,0,200));
    checkPanel((bool)preg_match('/const CSRF_PANEL = "([a-f0-9]+)"/', $html,$m), 'token CSRF generado');
    $csrf=$m[1];
}
try {
    $db->prepare('INSERT INTO sistema_roles (nombre_rol) VALUES (?)')->execute(['QA Panel ' . $suffix]);
    $ids['admin_role']=(int)$db->lastInsertId();
    $db->prepare('INSERT INTO sistema_usuarios (id_rol,nombre_usuario_sistema,correo_usuario_sistema,contrasena_usuario_sistema) VALUES (?,?,?,?)')->execute([$ids['admin_role'],'QA Panel temporal',$email,password_hash($password,PASSWORD_DEFAULT)]);
    $ids['admin']=(int)$db->lastInsertId();
    $db->prepare("INSERT INTO usuarios_secciones (id_usuario_sistema,id_seccion) SELECT ?,id_seccion FROM sistema_secciones WHERE estado_seccion='activo'")->execute([$ids['admin']]);
    [$code] = adminCall('Categorias','store'); checkPanel($code===401,'sin sesión rechazado');
    loginPanel();
    foreach (['dashboard','categorias','productos','variantes','pedidos','pagos','notificaciones','clientes','usuarios','roles','configuracion'] as $page) {
        [$code,$html] = requestLocal('dw-panel/' . $page);
        checkPanel($code===200 && !preg_match('/Fatal error|Warning:|SQLSTATE|No encontrado/i',$html) && str_contains($html,'Kloset · Panel administrativo'), "página $page consulta DB");
    }
    [$code] = adminCall('Categorias','store',[],false); checkPanel($code===403,'CSRF ausente rechazado');
    [$code] = adminCall('Configuracion','update'); checkPanel($code===400,'método legacy no permitido');
    [$code] = requestLocal('dw-panel/ajax.php?class=Categorias&method=deleteCategoria&id=1'); checkPanel($code===405,'mutación GET rechazada');
    $cat=['nombre'=>'QA Categoría ' . $suffix,'url'=>'qa-cat-' . $suffix,'descripcion'=>'Prueba local','estado'=>'activo'];
    $r=successPanel('Categorias','store',$cat); $ids['cat']=$r['id'];
    successPanel('Categorias','getById',['id'=>$ids['cat']]);
    [, $r]=adminCall('Categorias','store',$cat); checkPanel($r['status']==='error','categoría duplicada rechazada');
    successPanel('Categorias','updateCategoria',$cat+['id'=>$ids['cat']]);
    $prod=['nombre'=>'QA Producto ' . $suffix,'url'=>'qa-prod-' . $suffix,'id_categoria'=>$ids['cat'],'descripcion'=>'Prueba local','precio'=>'90.50','estado'=>'activo','talla'=>'S','corte'=>'Slim','stock'=>'7','sku'=>'QAP1'.$suffix];
    [, $r]=adminCall('Productos','store',array_merge($prod,['sku'=>'SINCODIGO'])); checkPanel($r['status']==='error','primera variante exige letras y números');
    $r=successPanel('Productos','store',$prod); $ids['prod']=$r['id'];
    $firstSku=$db->query('SELECT sku_variante FROM productos_variantes WHERE id_producto='.$ids['prod'].' LIMIT 1')->fetchColumn();
    checkPanel($firstSku===strtoupper($prod['sku']),'código personalizado de primera variante persistido');
    successPanel('Productos','updateProducto',$prod+['id'=>$ids['prod'],'stock_total'=>'16']);
    checkPanel((int)$db->query('SELECT COALESCE(SUM(stock_variante),0) FROM productos_variantes WHERE id_producto='.$ids['prod'])->fetchColumn()===16,'stock total de producto persistido');
    [, $r]=adminCall('Productos','store',array_merge($prod,['url'=>'otro-'.$suffix,'id_categoria'=>99999999])); checkPanel($r['status']==='error','categoría inexistente rechazada');
    $image = dirname(__DIR__) . '/app/public_root/imgs/kloset-icon-acento.png';
    $r=successPanel('Productos','uploadImagenes',['id'=>$ids['prod'],'imagenes[0]'=>new CURLFile($image,'image/png','qa.png')]);
    checkPanel(count($r['imagenes'])===1,'imagen subida en DB');
    $ids['image']=$r['imagenes'][0]['id_imagen']; $imagePath=dirname(__DIR__).'/app/public_root/imgs/productos/'.$r['imagenes'][0]['archivo_imagen'];
    checkPanel(is_file($imagePath),'archivo de imagen existente');
    [$code,$r]=adminCall('Productos','uploadImagenes',['id'=>$ids['prod'],'imagenes[0]'=>new CURLFile(__FILE__,'image/png','falso.png')]);
    checkPanel($code===422,'imagen falsa rechazada');
    $var=['id_producto'=>$ids['prod'],'talla'=>'M','corte'=>'Regular','sku'=>'QAV1'.$suffix,'stock'=>'10'];
    $r=successPanel('Variantes','store',$var); $ids['var']=$r['id'];
    [, $r]=adminCall('Variantes','store',array_merge($var,['sku'=>'SOLOLETRAS'])); checkPanel($r['status']==='error','código sin números rechazado');
    [, $r]=adminCall('Variantes','store',array_merge($var,['sku'=>'123456'])); checkPanel($r['status']==='error','código sin letras rechazado');
    [, $r]=adminCall('Variantes','store',array_merge($var,['sku'=>'QA-123'])); checkPanel($r['status']==='error','código con separadores rechazado');
    successPanel('Variantes','getById',['id'=>$ids['var']]);
    successPanel('Variantes','updateVariante',$var+['id'=>$ids['var']]);
    [, $r]=adminCall('Variantes','store',$var); checkPanel($r['status']==='error','SKU duplicado rechazado');
    [, $r]=adminCall('Variantes','updateVariante',array_merge($var,['id'=>$ids['var'],'stock'=>'-1'])); checkPanel($r['status']==='error','stock negativo rechazado');
    $clientEmail='qa-cliente-'.$suffix.'@example.invalid';
    $db->prepare('INSERT INTO sistema_usuarios (id_rol,nombre_usuario_sistema,correo_usuario_sistema,contrasena_usuario_sistema) VALUES (2,?,?,?)')->execute(['QA Cliente <prueba>',$clientEmail,password_hash($password,PASSWORD_DEFAULT)]);
    $ids['client']=(int)$db->lastInsertId();
    [, $raw]=requestLocal('api/v1/auth/login',['correo'=>$clientEmail,'password'=>$password],true);
    $r=json_decode($raw,true); checkPanel($r['status']==='success','sesión cliente QA'); $token=$r['token'];
    $apiCurl = curl_init();
    function clientApi(string $path,array $body): array {
        global $apiCurl,$token;
        curl_setopt_array($apiCurl,[CURLOPT_URL=>'http://127.0.0.1/kloset/api/v1/'.$path,CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$token]]);
        return json_decode(curl_exec($apiCurl),true);
    }
    $r=clientApi('carrito/agregar',['id_producto'=>$ids['prod'],'talla'=>'M','corte'=>'Regular','cantidad'=>2]); checkPanel($r['status']==='success','bolsa cliente');
    [, $r]=adminCall('Productos','deleteProducto',['id'=>$ids['prod']]); checkPanel($r['status']==='error' && is_file($imagePath),'borrado con bolsa conserva imagen');
    $r=clientApi('pedidos',['direccion'=>'Av. QA 123','ciudad'=>'Lince','marca_tarjeta'=>'Visa','ultimos_digitos'=>'4242']); checkPanel($r['status']==='success','pedido API creado'); $ids['order']=(int)$r['pedido']['id_pedido'];
    successPanel('Clientes','getById',['id'=>$ids['client']]);
    $r=successPanel('PedidosAdmin','getById',['id'=>$ids['order']]); checkPanel(count($r['items'])===1,'detalle pedido contiene prenda');
    $payId=(int)$db->query('SELECT id_pago FROM pagos WHERE id_pedido='.$ids['order'])->fetchColumn(); successPanel('Pagos','getById',['id'=>$payId]);
    foreach (['en_preparacion','enviado','en_reparto','entregado'] as $state) successPanel('PedidosAdmin','cambiarEstado',['id'=>$ids['order'],'estado'=>$state,'transportista'=>'QA Courier','seguimiento'=>'QA-'.$suffix,'entrega_estimada'=>'2026-10-01']);
    $historyBefore=(int)$db->query('SELECT COUNT(*) FROM historial_estados_pedidos WHERE id_pedido='.$ids['order'])->fetchColumn();
    successPanel('PedidosAdmin','cambiarEstado',['id'=>$ids['order'],'estado'=>'entregado']);
    checkPanel((int)$db->query('SELECT COUNT(*) FROM historial_estados_pedidos WHERE id_pedido='.$ids['order'])->fetchColumn()===$historyBefore,'repetición no duplica historial');
    checkPanel($db->query('SELECT seguimiento_pedido FROM pedidos WHERE id_pedido='.$ids['order'])->fetchColumn()==='QA-'.$suffix,'cambio sin metadata conserva seguimiento');
    [, $r]=adminCall('PedidosAdmin','cambiarEstado',['id'=>$ids['order'],'estado'=>'pagado']); checkPanel($r['status']==='error','pedido entregado no retrocede');
    [, $r]=adminCall('Variantes','updateVariante',array_merge($var,['id'=>$ids['var'],'talla'=>'L'])); checkPanel($r['status']==='error','identidad de variante vendida protegida');
    [, $r]=adminCall('Productos','deleteProducto',['id'=>$ids['prod']]); checkPanel($r['status']==='error' && is_file($imagePath),'borrado con venta conserva imagen');
    [, $r]=adminCall('Variantes','deleteVariante',['id'=>$ids['var']]); checkPanel($r['status']==='error','variante vendida no se elimina');
    $notId=(int)$db->query('SELECT id_notificacion FROM notificaciones WHERE id_pedido='.$ids['order'].' LIMIT 1')->fetchColumn(); successPanel('Notificaciones','marcarLeida',['id'=>$notId]);
    checkPanel((int)$db->query('SELECT leido_notificacion FROM notificaciones WHERE id_notificacion='.$notId)->fetchColumn()===1,'lectura notificación persistida');
    if ($unreadBefore===0) {
        successPanel('Notificaciones','marcarTodasLeidas');
        checkPanel((int)$db->query('SELECT COUNT(*) FROM notificaciones WHERE id_pedido='.$ids['order'].' AND leido_notificacion=0')->fetchColumn()===0,'marcar todas persistido');
    }
    $r=successPanel('Roles','store',['nombre'=>'QA Rol '.$suffix]); $ids['role']=$r['id'];
    successPanel('Roles','getById',['id'=>$ids['role']]); successPanel('Roles','updateRol',['id'=>$ids['role'],'nombre'=>'QA Rol editado '.$suffix,'estado'=>'activo']);
    $r=successPanel('Usuarios','store',['id_rol'=>$ids['role'],'nombre'=>'QA Operador','correo'=>'qa-operador-'.$suffix.'@example.invalid','password'=>$password]); $ids['operator']=$r['id'];
    $r=successPanel('Usuarios','getById',['id'=>$ids['operator']]);
    $section=(int)$db->query("SELECT id_seccion FROM sistema_secciones WHERE url_seccion='categorias'")->fetchColumn();
    successPanel('Usuarios','updateSecciones',['id'=>$ids['operator'],'secciones[0]'=>$section]);
    [, $r]=adminCall('Roles','deleteRol',['id'=>$ids['role']]); checkPanel($r['status']==='error','rol en uso protegido');
    $db->prepare('DELETE FROM sistema_usuarios WHERE id_usuario_sistema=?')->execute([$ids['operator']]); unset($ids['operator']); successPanel('Roles','deleteRol',['id'=>$ids['role']]); unset($ids['role']);
    $db->prepare('INSERT INTO sistema_configuraciones (llave_configuracion,valor_configuracion) VALUES (?,?)')->execute(['qa.'.$suffix,'original']); $ids['config']=(int)$db->lastInsertId();
    successPanel('Configuracion','saveAll',['valores['.$ids['config'].']'=>'editado']); checkPanel($db->query('SELECT valor_configuracion FROM sistema_configuraciones WHERE id_configuracion='.$ids['config'])->fetchColumn()==='editado','configuración persistida');
    [$code,$r]=adminCall('Configuracion','saveAll',['valores[99999999]'=>'x']); checkPanel($code===422,'configuración inexistente rechazada');
    $db->prepare('DELETE FROM usuarios_secciones WHERE id_usuario_sistema=? AND id_seccion=?')->execute([$ids['admin'],$section]);
    [$code]=adminCall('Categorias','getById',['id'=>$ids['cat']]); checkPanel($code===403,'revocación ACL inmediata');
    $db->prepare('INSERT INTO usuarios_secciones (id_usuario_sistema,id_seccion) VALUES (?,?)')->execute([$ids['admin'],$section]);
    successPanel('Categorias','getById',['id'=>$ids['cat']]);
    successPanel('Productos','deleteImagen',['id_imagen'=>$ids['image']]); clearstatcache(true,$imagePath); checkPanel(!is_file($imagePath),'imagen retirada del disco');
    $r=clientApi('carrito/agregar',['id_producto'=>$ids['prod'],'talla'=>'M','corte'=>'Regular','cantidad'=>1]);
    $r=clientApi('pedidos',['direccion'=>'Av. QA Cancelación 123','ciudad'=>'Lince']); $ids['cancel_order']=(int)$r['pedido']['id_pedido'];
    successPanel('PedidosAdmin','cambiarEstado',['id'=>$ids['cancel_order'],'estado'=>'cancelado']);
    checkPanel((int)$db->query('SELECT stock_variante FROM productos_variantes WHERE id_variante='.$ids['var'])->fetchColumn()===8,'cancelación admin devuelve stock');
    adminCall('PedidosAdmin','cambiarEstado',['id'=>$ids['cancel_order'],'estado'=>'cancelado']);
    checkPanel((int)$db->query('SELECT stock_variante FROM productos_variantes WHERE id_variante='.$ids['var'])->fetchColumn()===8,'segunda cancelación no duplica stock');
    foreach (['order','cancel_order'] as $key) {
        foreach (['notificaciones','pagos','pedidos_items','historial_estados_pedidos'] as $table) $db->prepare("DELETE FROM $table WHERE id_pedido=?")->execute([$ids[$key]]);
        $db->prepare('DELETE FROM pedidos WHERE id_pedido=?')->execute([$ids[$key]]); unset($ids[$key]);
    }
    successPanel('Variantes','deleteVariante',['id'=>$ids['var']]);
    successPanel('Productos','deleteProducto',['id'=>$ids['prod']]); unset($ids['prod']);
    successPanel('Categorias','deleteCategoria',['id'=>$ids['cat']]); unset($ids['cat']);
    $r=clientApi('contacto',['nombre'=>'QA Contacto','correo'=>'qa-contacto-'.$suffix.'@example.invalid','tema'=>'otro','mensaje'=>'Consulta de integración administrativa local']);
    checkPanel($r['status']==='success','contacto genera notificación de panel');
    $q=$db->prepare('SELECT id_contacto FROM contactos WHERE correo_contacto=?'); $q->execute(['qa-contacto-'.$suffix.'@example.invalid']); $ids['contact']=(int)$q->fetchColumn();
    checkPanel((int)$db->query('SELECT COUNT(*) FROM notificaciones WHERE id_contacto='.$ids['contact'])->fetchColumn()>0,'contacto visible en notificaciones DB');
    $db->prepare("UPDATE sistema_usuarios SET estado_usuario_sistema='inactivo' WHERE id_usuario_sistema=?")->execute([$ids['admin']]);
    [$code]=adminCall('Productos','getById',['id'=>99999999]); checkPanel($code===401,'usuario desactivado pierde sesión');
} finally {
    // Ningún dato del catálogo real ni acceso del administrador original se modifica.
    foreach (['order','cancel_order'] as $key) if (isset($ids[$key])) {
        foreach (['notificaciones','pagos','pedidos_items','historial_estados_pedidos'] as $table) $db->prepare("DELETE FROM $table WHERE id_pedido=?")->execute([$ids[$key]]);
        $db->prepare('DELETE FROM pedidos WHERE id_pedido=?')->execute([$ids[$key]]);
    }
    if(isset($ids['contact'])) { $db->prepare('DELETE FROM notificaciones WHERE id_contacto=?')->execute([$ids['contact']]); $db->prepare('DELETE FROM contactos WHERE id_contacto=?')->execute([$ids['contact']]); }
    if (isset($ids['client'])) {
        $db->prepare('DELETE ci FROM carritos_items ci JOIN carritos c ON c.id_carrito=ci.id_carrito WHERE c.id_usuario_sistema=?')->execute([$ids['client']]);
        foreach (['carritos','direcciones_envio_clientes','sistema_usuarios'] as $table) $db->prepare("DELETE FROM $table WHERE id_usuario_sistema=?")->execute([$ids['client']]);
    }
    if (isset($ids['prod'])) {
        $q=$db->prepare('SELECT url_imagen FROM productos_imagenes WHERE id_producto=?'); $q->execute([$ids['prod']]);
        foreach ($q->fetchAll() as $i) { $path=dirname(__DIR__).'/app/public_root/imgs/productos/'.basename($i['url_imagen']); if(is_file($path)) unlink($path); }
        $db->prepare('DELETE FROM productos WHERE id_producto=?')->execute([$ids['prod']]);
    }
    foreach (['operator','admin'] as $key) if(isset($ids[$key])) $db->prepare('DELETE FROM sistema_usuarios WHERE id_usuario_sistema=?')->execute([$ids[$key]]);
    foreach (['role','admin_role'] as $key) if(isset($ids[$key])) $db->prepare('DELETE FROM sistema_roles WHERE id_rol=?')->execute([$ids[$key]]);
    if(isset($ids['config'])) $db->prepare('DELETE FROM sistema_configuraciones WHERE id_configuracion=?')->execute([$ids['config']]);
    if(isset($ids['cat'])) $db->prepare('DELETE FROM categorias WHERE id_categoria=?')->execute([$ids['cat']]);
    curl_close($http);
    echo "registros QA del panel retirados\n";
}
