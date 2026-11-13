<?php

namespace Develoweb\App\Model;

class Pagos
{
	private const SECCION = 'pagos';

	public static function reembolsarPedido(\PDO $con, int $idPedido): void
	{
		$sth = $con->prepare("UPDATE pagos SET estado_pago='reembolsado' WHERE id_pedido=? AND estado_pago='aprobado'");
		$sth->execute([$idPedido]);
		if ($sth->rowCount() !== 1) {
			throw new \DomainException('No se pudo registrar el reembolso del pago aprobado');
		}
	}

	public static function procesarReembolsoPostventa(\PDO $con, int $idSolicitud, float $importe): string
	{
		if ($importe <= 0) {
			throw new \DomainException('El importe del reembolso debe ser mayor que cero');
		}
		$existente = $con->prepare('SELECT importe_reembolso,referencia_reembolso,estado_reembolso FROM reembolsos_postventa WHERE id_solicitud_postventa=? FOR UPDATE');
		$existente->execute([$idSolicitud]);
		$reembolso = $existente->fetch();
		if (!$reembolso || (float) $reembolso['importe_reembolso'] !== $importe) {
			throw new \DomainException('El importe del reembolso no coincide con la solicitud aprobada');
		}
		if ($reembolso['estado_reembolso'] === 'completado') {
			throw new \DomainException('El reembolso ya fue completado');
		}
		if ($reembolso['referencia_reembolso'] && $reembolso['estado_reembolso'] === 'en_proceso') {
			return (string) $reembolso['referencia_reembolso'];
		}
		$referencia = 'RF-' . date('YmdHis') . '-' . $idSolicitud;
		$sth = $con->prepare(
			"UPDATE reembolsos_postventa
			 SET estado_reembolso='en_proceso', referencia_reembolso=COALESCE(referencia_reembolso, ?)
			 WHERE id_solicitud_postventa=? AND estado_reembolso IN ('aprobado','en_proceso')"
		);
		$sth->execute([$referencia, $idSolicitud]);
		if ($sth->rowCount() < 1) {
			$check = $con->prepare('SELECT referencia_reembolso FROM reembolsos_postventa WHERE id_solicitud_postventa=?');
			$check->execute([$idSolicitud]);
			$referenciaExistente = $check->fetchColumn();
			if (!$referenciaExistente) {
				throw new \DomainException('No se pudo registrar el reembolso simulado');
			}
			return (string) $referenciaExistente;
		}
		return $referencia;
	}

	public static function get(): array
	{
		$con = Conexion::getInstance();
		$sth = $con->query(
			"SELECT pg.id_pago, pg.id_pedido, p.codigo_pedido, pg.metodo_pago, pg.marca_pago, pg.ultimos_digitos_pago,
			        pg.monto_pago, pg.estado_pago, pg.id_transaccion_pasarela_pago, pg.fecha_creacion,
			        u.nombre_usuario_sistema AS cliente
			 FROM pagos pg
			 INNER JOIN pedidos p ON p.id_pedido = pg.id_pedido
			 INNER JOIN sistema_usuarios u ON u.id_usuario_sistema = p.id_usuario_sistema
			 ORDER BY pg.fecha_creacion DESC"
		);
		return $sth->fetchAll();
	}

	public static function getById(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$id = (int) ($_POST['id'] ?? 0);
		$con = Conexion::getInstance();
		$sth = $con->prepare(
			"SELECT pg.*, p.codigo_pedido, p.estado_pedido, p.total_pedido, u.nombre_usuario_sistema AS cliente, u.correo_usuario_sistema AS correo
			 FROM pagos pg
			 INNER JOIN pedidos p ON p.id_pedido = pg.id_pedido
			 INNER JOIN sistema_usuarios u ON u.id_usuario_sistema = p.id_usuario_sistema
			 WHERE pg.id_pago = :id"
		);
		$sth->execute([':id' => $id]);
		$row = $sth->fetch();
		if (!$row) {
			return ['status' => 'error', 'message' => 'Pago no encontrado'];
		}
		return ['status' => 'success', 'pago' => $row];
	}
}
