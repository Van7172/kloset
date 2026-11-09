<?php

namespace Develoweb\App\Model;

class PedidosAdmin
{
    private const SECCION = 'pedidos';
    public const ESTADOS = ['pagado','en_preparacion','enviado','en_reparto','entregado','cancelado'];
    private const SIGUIENTE = [
        'pagado'=>['en_preparacion'],
        'en_preparacion'=>['enviado'],
        'enviado'=>['en_reparto'],
        'en_reparto'=>['entregado','cancelado'],
    ];
    private const MENSAJES = [
        'en_preparacion'=>'Estamos preparando tu pedido.',
        'enviado'=>'Tu pedido salió del almacén.',
        'en_reparto'=>'Tu pedido está en reparto.',
        'entregado'=>'Tu pedido fue entregado. ¡Gracias por tu compra!',
        'cancelado'=>'Tu pedido fue cancelado y el pago simulado fue reembolsado.',
    ];

    public static function get(): array
    {
        return Conexion::getInstance()->query(
            "SELECT p.id_pedido,p.codigo_pedido,p.estado_pedido,p.total_pedido,p.fecha_creacion,
                    u.nombre_usuario_sistema AS cliente,u.correo_usuario_sistema AS correo
             FROM pedidos p JOIN sistema_usuarios u ON u.id_usuario_sistema=p.id_usuario_sistema
             ORDER BY p.fecha_creacion DESC"
        )->fetchAll();
    }

    public static function getById(): array
    {
        if ($error = Acl::guard(self::SECCION)) return $error;
        $id = (int) ($_POST['id'] ?? 0);
        $con = Conexion::getInstance();
        $sth = $con->prepare(
            'SELECT p.id_pedido,p.codigo_pedido,p.estado_pedido,p.subtotal_pedido,p.total_pedido,p.fecha_creacion,
                    p.fecha_pago_aprobado,p.transportista_pedido,p.entrega_estimada_pedido,
                    u.nombre_usuario_sistema AS cliente,u.correo_usuario_sistema AS correo,
                    d.direccion_envio_cliente,d.ciudad_envio_cliente,d.referencia_envio_cliente,
                    pg.metodo_pago,pg.marca_pago,pg.estado_pago,pg.id_transaccion_pasarela_pago
             FROM pedidos p JOIN sistema_usuarios u ON u.id_usuario_sistema=p.id_usuario_sistema
             JOIN direcciones_envio_clientes d ON d.id_direccion_envio=p.id_direccion_envio
             LEFT JOIN pagos pg ON pg.id_pedido=p.id_pedido WHERE p.id_pedido=?'
        );
        $sth->execute([$id]);
        $pedido = $sth->fetch();
        if (!$pedido) return ['status'=>'error','message'=>'Pedido no encontrado'];
        $sth = $con->prepare(
            'SELECT pi.cantidad_pedido_item,pi.precio_unitario_pedido_item,
                    COALESCE(pi.nombre_producto_compra,p.nombre_producto) AS nombre_producto,
                    COALESCE(pi.nombre_color_compra,c.nombre_color) AS nombre_color,
                    COALESCE(pi.talla_compra,v.talla_variante) AS talla_variante,
                    COALESCE(pi.corte_compra,v.corte_variante) AS corte_variante,
                    COALESCE(pi.sku_compra,v.sku_variante) AS sku_variante
             FROM pedidos_items pi JOIN productos_variantes v ON v.id_variante=pi.id_variante
             JOIN productos p ON p.id_producto=v.id_producto JOIN productos_colores c ON c.id_color=v.id_color
             WHERE pi.id_pedido=?'
        );
        $sth->execute([$id]);
        $items = $sth->fetchAll();
        $sth = $con->prepare(
            'SELECT h.estado_anterior_historial,h.estado_historial_estado_pedido,h.comentario_historial_estado_pedido,h.fecha_creacion,
                    u.nombre_usuario_sistema AS autor
             FROM historial_estados_pedidos h LEFT JOIN sistema_usuarios u ON u.id_usuario_sistema=h.id_usuario_sistema
             WHERE h.id_pedido=? ORDER BY h.fecha_creacion DESC,h.id_historial_estado_pedido DESC'
        );
        $sth->execute([$id]);
        return ['status'=>'success','pedido'=>$pedido,'items'=>$items,'historial'=>$sth->fetchAll()];
    }

    public static function cambiarEstado(): array
    {
        if ($error = Acl::guard(self::SECCION)) return $error;
        $id = (int) ($_POST['id'] ?? 0);
        $estado = (string) ($_POST['estado'] ?? '');
        $comentario = trim((string) ($_POST['comentario'] ?? ''));
        $transportista = trim((string) ($_POST['transportista'] ?? ''));
        $codigo = trim((string) ($_POST['codigo_confirmacion'] ?? ''));
        if ($id <= 0 || !in_array($estado, self::ESTADOS, true)) {
            return ['status'=>'error','message'=>'Transición no permitida'];
        }
        if (mb_strlen($comentario) > 255 || mb_strlen($transportista) > 120) {
            return ['status'=>'error','message'=>'Revisa los datos del pedido'];
        }
        $con = Conexion::getInstance();
        try {
            $con->beginTransaction();
            $sth = $con->prepare('SELECT * FROM pedidos WHERE id_pedido=? FOR UPDATE');
            $sth->execute([$id]);
            $pedido = $sth->fetch();
            if (!$pedido) throw new \DomainException('Pedido no encontrado');
            $actual = $pedido['estado_pedido'];
            if (!in_array($estado, self::SIGUIENTE[$actual] ?? [], true)) {
                throw new \DomainException('Sigue el orden de estados del pedido');
            }
            if ($estado === 'enviado' && $transportista === '') throw new \DomainException('Indica el transportista');
            if ($estado === 'entregado') {
                if (!preg_match('/^\d{8}$/', $codigo)) throw new \DomainException('Introduce el código de 8 dígitos del cliente');
                if ((int) $pedido['codigo_entrega_intentos'] >= 5) throw new \DomainException('Código bloqueado; contacta soporte');
                if (!$pedido['codigo_entrega_hash']) throw new \DomainException('Este pedido no tiene código de entrega; contacta soporte');
                if (!password_verify($codigo, $pedido['codigo_entrega_hash'])) {
                    $con->prepare('UPDATE pedidos SET codigo_entrega_intentos=codigo_entrega_intentos+1 WHERE id_pedido=?')->execute([$id]);
                    $con->commit();
                    return ['status'=>'error','message'=>'El código de confirmación no coincide'];
                }
            }
            if ($estado === 'cancelado') {
                if (!preg_match('/^\d{8}$/', $codigo)) throw new \DomainException('Introduce el código de anulación de 8 dígitos del cliente');
                if ((int) $pedido['codigo_anulacion_intentos'] >= 5) throw new \DomainException('Código de anulación bloqueado; contacta soporte');
                if (!$pedido['codigo_anulacion_hash']) throw new \DomainException('Este pedido no tiene código de anulación; contacta soporte');
                if (!password_verify($codigo, $pedido['codigo_anulacion_hash'])) {
                    $con->prepare('UPDATE pedidos SET codigo_anulacion_intentos=codigo_anulacion_intentos+1 WHERE id_pedido=?')->execute([$id]);
                    $con->commit();
                    return ['status'=>'error','message'=>'El código de anulación no coincide'];
                }
            }
            if ($estado === 'en_reparto' && (!$pedido['codigo_entrega_hash'] || !$pedido['codigo_entrega_cifrado'])) {
                [$hash,$cifrado] = CodigoEntrega::crear();
                $con->prepare('UPDATE pedidos SET codigo_entrega_hash=?,codigo_entrega_cifrado=?,codigo_entrega_intentos=0 WHERE id_pedido=?')
                    ->execute([$hash,$cifrado,$id]);
            }
            $con->prepare(
                "UPDATE pedidos SET estado_pedido=?,transportista_pedido=?,
                 codigo_entrega_hash=IF(?='entregado',NULL,codigo_entrega_hash),
                 codigo_entrega_cifrado=IF(?='entregado',NULL,codigo_entrega_cifrado),
                 codigo_entrega_usado_en=IF(?='entregado',NOW(),codigo_entrega_usado_en),
                 codigo_anulacion_usado_en=IF(?='cancelado',NOW(),codigo_anulacion_usado_en)
                 WHERE id_pedido=?"
            )->execute([$estado,$estado === 'enviado' ? $transportista : $pedido['transportista_pedido'],
                $estado,$estado,$estado,$estado,$id]);
            if ($estado === 'cancelado') {
                $con->prepare(
                    'UPDATE productos_variantes v
                     INNER JOIN pedidos_items pi ON pi.id_variante=v.id_variante
                     SET v.stock_variante=v.stock_variante+pi.cantidad_pedido_item
                     WHERE pi.id_pedido=?'
                )->execute([$id]);
                $con->prepare(
                    "INSERT INTO inventario_eventos (id_variante,tipo_evento,id_usuario_sistema)
                     SELECT pi.id_variante,'devolucion_cancelacion',?
                     FROM pedidos_items pi WHERE pi.id_pedido=?"
                )->execute([Acl::usuario()->getId(),$id]);
                Pagos::reembolsarPedido($con, $id);
            }
            $con->prepare(
                'INSERT INTO historial_estados_pedidos (id_pedido,id_usuario_sistema,estado_anterior_historial,estado_historial_estado_pedido,comentario_historial_estado_pedido)
                 VALUES (?,?,?,?,?)'
            )->execute([$id,Acl::usuario()->getId(),$actual,$estado,$comentario ?: 'Cambio realizado por el administrador']);
            $sth = $con->prepare('SELECT avisos_pedidos_usuario FROM sistema_usuarios WHERE id_usuario_sistema=?');
            $sth->execute([(int) $pedido['id_usuario_sistema']]);
            if ($sth->fetchColumn()) {
                $con->prepare('INSERT INTO notificaciones (id_usuario_sistema,id_pedido,mensaje_notificacion) VALUES (?,?,?)')
                    ->execute([(int) $pedido['id_usuario_sistema'],$id,self::MENSAJES[$estado]]);
            }
            $con->commit();
            return [
                'status'=>'success',
                'message'=>$estado === 'cancelado'
                    ? 'Pedido cancelado; stock devuelto y reembolso simulado registrado.'
                    : 'Estado e historial guardados',
            ];
        } catch (\DomainException $e) {
            if ($con->inTransaction()) $con->rollBack();
            return ['status'=>'error','message'=>$e->getMessage()];
        } catch (\Throwable $e) {
            if ($con->inTransaction()) $con->rollBack();
            error_log('Kloset cambiar estado: '.$e->getMessage());
            return ['status'=>'error','message'=>'No se pudo actualizar el pedido'];
        }
    }
}
