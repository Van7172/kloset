<?php

namespace Develoweb\App\Model;

use Develoweb\App\Utilities\JwtHelper;
use PDO;

class PerfilesCorporales
{
	private const TALLAS = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

	/** Misma fórmula que `frontend/src/lib/fit.ts` (indiceTalla/tallaRecomendada). */
	private static function tallaRecomendada(float $pecho, string $corte): string
	{
		$c = $pecho;
		$config = Conexion::getInstance()->query("SELECT valor_configuracion FROM sistema_configuraciones WHERE llave_configuracion='tallas.tolerancia_cm'")->fetchColumn();
		$tolerancia = is_numeric($config) ? (float)$config : 2.5;
		// Talla más pequeña cuyo tope de pecho (88, 96, 104, 112, 120) cubre la medida; el resto es XXL.
		foreach ([88, 96, 104, 112, 120] as $i => $tope) {
			if ($c < $tope + $tolerancia) {
				return self::TALLAS[$i];
			}
		}
		return self::TALLAS[5];
	}

	public static function mio(): array
	{
		$userId = JwtHelper::bearerUserId();
		if (!$userId) {
			http_response_code(401);
			return ['status' => 'error', 'message' => 'No autorizado'];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare(
			'SELECT estatura_perfil_corporal, pecho_perfil_corporal, cintura_perfil_corporal,
			        cadera_perfil_corporal, talla_recomendada_perfil_corporal
			 FROM perfiles_corporales WHERE id_usuario_sistema = :id'
		);
		$sth->execute([':id' => $userId]);
		$perfil = $sth->fetch();

		return ['status' => 'success', 'perfil' => $perfil ?: null];
	}

	public static function guardar(): array
	{
		$userId = JwtHelper::bearerUserId();
		if (!$userId) {
			http_response_code(401);
			return ['status' => 'error', 'message' => 'No autorizado'];
		}

		$estatura = (float) ($_POST['estatura'] ?? 0);
		$pecho = (float) ($_POST['pecho'] ?? 0);
		$cintura = (float) ($_POST['cintura'] ?? 0);
		$cadera = (float) ($_POST['cadera'] ?? 0);
		$corte = in_array($_POST['corte'] ?? '', ['Slim', 'Regular', 'Oversize'], true) ? $_POST['corte'] : 'Regular';

		if ($estatura < 100 || $pecho < 50 || $cintura < 40 || $cadera < 50) {
			return ['status' => 'error', 'message' => 'Medidas fuera de rango'];
		}

		$talla = self::tallaRecomendada($pecho, $corte);

		$con = Conexion::getInstance();
		$sth = $con->prepare(
			'INSERT INTO perfiles_corporales
			   (id_usuario_sistema, estatura_perfil_corporal, pecho_perfil_corporal,
			    cintura_perfil_corporal, cadera_perfil_corporal, talla_recomendada_perfil_corporal)
			 VALUES (:id, :estatura, :pecho, :cintura, :cadera, :talla)
			 ON DUPLICATE KEY UPDATE
			   estatura_perfil_corporal = VALUES(estatura_perfil_corporal),
			   pecho_perfil_corporal = VALUES(pecho_perfil_corporal),
			   cintura_perfil_corporal = VALUES(cintura_perfil_corporal),
			   cadera_perfil_corporal = VALUES(cadera_perfil_corporal),
			   talla_recomendada_perfil_corporal = VALUES(talla_recomendada_perfil_corporal)'
		);
		$sth->execute([
			':id' => $userId,
			':estatura' => $estatura,
			':pecho' => $pecho,
			':cintura' => $cintura,
			':cadera' => $cadera,
			':talla' => $talla,
		]);

		return [
			'status' => 'success',
			'perfil' => [
				'estatura_perfil_corporal' => $estatura,
				'pecho_perfil_corporal' => $pecho,
				'cintura_perfil_corporal' => $cintura,
				'cadera_perfil_corporal' => $cadera,
				'talla_recomendada_perfil_corporal' => $talla,
			],
		];
	}

	public static function eliminar(): array
	{
		$userId = JwtHelper::bearerUserId();
		if (!$userId) {
			http_response_code(401);
			return ['status' => 'error', 'message' => 'No autorizado'];
		}
		$con = Conexion::getInstance();
		$con->prepare('DELETE FROM perfiles_corporales WHERE id_usuario_sistema = ?')->execute([$userId]);
		return ['status' => 'success'];
	}
}
