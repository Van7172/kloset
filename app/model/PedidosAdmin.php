<?php

namespace Develoweb\App\Model;

class PedidosAdmin
{
    private const SECCION = 'pedidos';
    public const ESTADOS = ['pagado','en_preparacion','enviado','en_reparto','entregado','cancelado'];
    private const SIGUIENTE = ['pagado'=>'en_preparacion','en_preparacion'=>'enviado','enviado'=>'en_reparto','en_reparto'=>'entregado'];
    private const MENSAJES = [
        'en_preparacion'=>'Estamos preparando tu pedido.',
        'enviado'=>'Tu pedido salió del almacén.',
        'en_reparto'=>'Tu pedido está en reparto.',
        'entregado'=>'Tu pedido fue entregado. ¡Gracias por tu compra!',
    ];

    public static function get(): array
    {
        return Conexion::getInstance()->query(
            "SELECT p.id_pedido,p.estado_pedido,p.total_pedido,p.fecha_creacion,
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
            'SELECT p.id_pedido,p.estado_pedido,p.subtotal_pedido,p.total_pedido,p.fecha_creacion,
                    p.fecha_pago_aprobado,p.transportista_pedido,p.seguimiento_pedido,p.entrega_estimada_pedido,
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
        $seguimiento = trim((string) ($_POST['seguimiento'] ?? ''));
        $codigo = trim((string) ($_POST['codigo_entrega'] ?? ''));
        if ($id <= 0 || !in_array($estado, self::ESTADOS, true) || $estado === 'cancelado') {
            return ['status'=>'error','message'=>'Transición no permitida'];
        }
        if (mb_strlen($comentario) > 255 || mb_strlen($transportista) > 120 || mb_strlen($seguimiento) > 100) {
            return ['status'=>'error','message'=>'Revisa los datos de seguimiento'];
        }
        $con = Conexion::getInstance();
        try {
            $con->beginTransaction();
            $sth = $con->prepare('SELECT * FROM pedidos WHERE id_pedido=? FOR UPDATE');
            $sth->execute([$id]);
            $pedido = $sth->fetch();
            if (!$pedido) throw new \DomainException('Pedido no encontrado');
            $actual = $pedido['estado_pedido'];
            $soloSeguimiento = $actual === 'enviado' && $estado === 'enviado';
            if (!$soloSeguimiento && (self::SIGUIENTE[$actual] ?? null) !== $estado) {
                throw new \DomainException('Sigue el orden de estados del pedido');
            }
            if ($soloSeguimiento) {
                if ($transportista === (string) $pedido['transportista_pedido'] && $seguimiento === (string) $pedido['seguimiento_pedido']) {
                    $con->commit();
                    return ['status'=>'success','message'=>'El seguimiento ya está actualizado'];
                }
                $con->prepare('UPDATE pedidos SET transportista_pedido=?,seguimiento_pedido=? WHERE id_pedido=?')
                    ->execute([$transportista ?: null,$seguimiento ?: null,$id]);
                $con->commit();
                return ['status'=>'success','message'=>'Seguimiento actualizado'];
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
            if ($estado === 'en_reparto') {
                [$hash,$cifrado] = CodigoEntrega::crear();
                $con->prepare('UPDATE pedidos SET codigo_entrega_hash=?,codigo_entrega_cifrado=?,codigo_entrega_intentos=0 WHERE id_pedido=?')
                    ->execute([$hash,$cifrado,$id]);
            }
            $con->prepare(
                "UPDATE pedidos SET estado_pedido=?,transportista_pedido=?,seguimiento_pedido=?,
                 codigo_entrega_hash=IF(?='entregado',NULL,codigo_entrega_hash),
                 codigo_entrega_cifrado=IF(?='entregado',NULL,codigo_entrega_cifrado),
                 codigo_entrega_usado_en=IF(?='entregado',NOW(),codigo_entrega_usado_en)
                 WHERE id_pedido=?"
            )->execute([$estado,$estado === 'enviado' ? $transportista : $pedido['transportista_pedido'],
                $estado === 'enviado' ? ($seguimiento ?: null) : $pedido['seguimiento_pedido'],
                $estado,$estado,$estado,$id]);
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
            return ['status'=>'success','message'=>'Estado e historial guardados'];
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
