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
            'SELECT pi.id_pedido_item,pi.id_variante,pi.cantidad_pedido_item,pi.precio_unitario_pedido_item,
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
        $incidencia = $con->prepare(
            'SELECT id_solicitud_postventa,tipo_solicitud,estado_solicitud,fecha_creacion
             FROM solicitudes_postventa WHERE id_pedido=? ORDER BY id_solicitud_postventa LIMIT 1'
        );
        $incidencia->execute([$id]);
        $incidencia = $incidencia->fetch() ?: null;
        $sth = $con->prepare(
            'SELECT h.estado_anterior_historial,h.estado_historial_estado_pedido,h.comentario_historial_estado_pedido,h.fecha_creacion,
                    u.nombre_usuario_sistema AS autor
             FROM historial_estados_pedidos h LEFT JOIN sistema_usuarios u ON u.id_usuario_sistema=h.id_usuario_sistema
             WHERE h.id_pedido=? ORDER BY h.fecha_creacion DESC,h.id_historial_estado_pedido DESC'
        );
        $sth->execute([$id]);
        return [
            'status'=>'success',
            'pedido'=>$pedido,
            'items'=>$items,
            'incidencia'=>$incidencia,
            'puede_registrar_incidencia'=>(string) Acl::usuario()->getRol() === 'Administrador',
            'historial'=>$sth->fetchAll(),
        ];
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
                if (!preg_match('/^\d{8}$/', $codigo)) throw new \DomainException('Introduce el código de cancelación de 8 dígitos del cliente');
                if ((int) $pedido['codigo_anulacion_intentos'] >= 5) throw new \DomainException('Código de cancelación bloqueado; contacta soporte');
                if (!$pedido['codigo_anulacion_hash']) throw new \DomainException('Este pedido no tiene código de cancelación; contacta soporte');
                if (!password_verify($codigo, $pedido['codigo_anulacion_hash'])) {
                    $con->prepare('UPDATE pedidos SET codigo_anulacion_intentos=codigo_anulacion_intentos+1 WHERE id_pedido=?')->execute([$id]);
                    $con->commit();
                    return ['status'=>'error','message'=>'El código de cancelación no coincide'];
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
                if ($actual === 'en_reparto') {
                    $con->prepare('INSERT INTO pedidos_retorno_fisico (id_pedido,estado_retorno,id_usuario_registro) VALUES (?,\'pendiente\',?)')
                        ->execute([$id, Acl::usuario()->getId()]);
                    $idRetorno = (int) $con->lastInsertId();
                    $con->prepare(
                        'INSERT INTO pedidos_retorno_fisico_items (id_retorno,id_pedido_item,id_variante,cantidad_esperada)
                         SELECT ?,pi.id_pedido_item,pi.id_variante,pi.cantidad_pedido_item
                         FROM pedidos_items pi WHERE pi.id_pedido=?'
                    )->execute([$idRetorno, $id]);
                } else {
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
                }
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
                    ? ($actual === 'en_reparto'
                        ? 'Pedido cancelado; retorno físico pendiente y reembolso simulado registrado.'
                        : 'Pedido cancelado; stock devuelto y reembolso simulado registrado.')
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

    public static function registrarRecepcionRetorno(): array
        {
            if ($error = Acl::guard(self::SECCION)) return $error;
            $id = (int) ($_POST['id_pedido'] ?? 0);
            $items = json_decode((string) ($_POST['items'] ?? '[]'), true);
            if ($id <= 0 || !is_array($items) || !$items) return ['status' => 'error', 'message' => 'Indica las unidades recibidas y aptas'];
            $con = Conexion::getInstance();
            try {
                $con->beginTransaction();
                $retorno = $con->prepare('SELECT id_retorno,estado_retorno FROM pedidos_retorno_fisico WHERE id_pedido=? FOR UPDATE');
                $retorno->execute([$id]);
                $cabecera = $retorno->fetch();
                if (!$cabecera) throw new \DomainException('El pedido no tiene un retorno físico pendiente');
                if ($cabecera['estado_retorno'] === 'completado') throw new \DomainException('El retorno físico ya fue registrado');
                $linea = $con->prepare('SELECT id_retorno_item,id_variante,cantidad_esperada,cantidad_apta,cantidad_no_apta FROM pedidos_retorno_fisico_items WHERE id_retorno=? AND id_pedido_item=? FOR UPDATE');
                $actualizar = $con->prepare('UPDATE pedidos_retorno_fisico_items SET cantidad_apta=?,cantidad_no_apta=?,fecha_verificacion=NOW() WHERE id_retorno_item=? AND cantidad_apta IS NULL');
                $sumarStock = $con->prepare('UPDATE productos_variantes SET stock_variante=stock_variante+? WHERE id_variante=?');
                $evento = $con->prepare("INSERT INTO inventario_eventos (id_variante,tipo_evento,id_usuario_sistema) VALUES (?,'devolucion_cancelacion_apta',?)");
                foreach ($items as $pedidoItemId => $cantidades) {
                    $pedidoItemId = (int) $pedidoItemId;
                    $apta = (int) ($cantidades['apta'] ?? -1);
                    $noApta = (int) ($cantidades['no_apta'] ?? -1);
                    $linea->execute([(int) $cabecera['id_retorno'], $pedidoItemId]);
                    $registro = $linea->fetch();
                    if (!$registro || $registro['cantidad_apta'] !== null || $apta < 0 || $noApta < 0 || $apta + $noApta !== (int) $registro['cantidad_esperada']) {
                        throw new \DomainException('La recepción física no coincide con las unidades esperadas');
                    }
                    $actualizar->execute([$apta, $noApta, $registro['id_retorno_item']]);
                    if ($actualizar->rowCount() !== 1) throw new \DomainException('La recepción ya fue registrada');
                    if ($apta > 0) {
                        $sumarStock->execute([$apta, $registro['id_variante']]);
                        $evento->execute([$registro['id_variante'], Acl::usuario()->getId()]);
                    }
                }
                $pendientes = $con->prepare('SELECT COUNT(*) FROM pedidos_retorno_fisico_items WHERE id_retorno=? AND cantidad_apta IS NULL');
                $pendientes->execute([(int) $cabecera['id_retorno']]);
                if ((int) $pendientes->fetchColumn() === 0) {
                    $con->prepare('UPDATE pedidos_retorno_fisico SET estado_retorno=\'completado\',fecha_completado=NOW(),id_usuario_verificacion=? WHERE id_retorno=?')
                        ->execute([Acl::usuario()->getId(), $cabecera['id_retorno']]);
                }
                $con->commit();
                return ['status' => 'success', 'message' => 'Recepción física registrada'];
            } catch (\DomainException $e) {
                if ($con->inTransaction()) $con->rollBack();
                return ['status' => 'error', 'message' => $e->getMessage()];
            } catch (\Throwable $e) {
                if ($con->inTransaction()) $con->rollBack();
                error_log('Kloset retorno físico: ' . $e->getMessage());
                return ['status' => 'error', 'message' => 'No se pudo registrar la recepción física'];
            }
    }
}
