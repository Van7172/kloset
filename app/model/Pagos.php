<?php

namespace Develoweb\App\Model;

class Pagos
{
	private const SECCION = 'pagos';

	public static function get(): array
	{
		$con = Conexion::getInstance();
		$sth = $con->query(
			"SELECT pg.id_pago, pg.id_pedido, pg.metodo_pago, pg.marca_pago, pg.ultimos_digitos_pago,
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
			"SELECT pg.*, p.estado_pedido, p.total_pedido, u.nombre_usuario_sistema AS cliente, u.correo_usuario_sistema AS correo
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
