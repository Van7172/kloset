<?php

namespace Develoweb\App\Model;

use PDO;
use PDOException;

class Configuracion
{
	private $data = [];
	private $_msgbox;
	private $_usuario;

	public function __construct(Usuario $user, Msgbox $msg = null)
	{
		$this->_msgbox = $msg;
		$this->_usuario = $user;
	}

	public static function update()
	{
		$valor = $_POST['value'] ?? '';
		if (($_POST['type'] ?? '') === 'file') {
			$ruta = PUBLIC_ROOT_HOST . 'imgs';
			$valor = uploadImgs($_FILES['update_file_index']['name'], $_FILES['update_file_index']['tmp_name'], $ruta);
		}

		try {
			$con = Conexion::getInstance();
			$stmt = $con->prepare(
				'UPDATE sistema_configuraciones SET valor_configuracion = :value WHERE id_configuracion = :id'
			);
			$stmt->bindParam(':value', $valor, PDO::PARAM_STR);
			$stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
			if (!$stmt->execute()) {
				throw new PDOException('No se pudo actualizar');
			}
			return true;
		} catch (PDOException $e) {
			return ['errors' => $e->getMessage()];
		}
	}

	public function getData()
	{
		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT llave_configuracion, valor_configuracion FROM sistema_configuraciones');
		$sth->execute();
		foreach ($sth->fetchAll() as $configuration) {
			$this->data[$configuration['llave_configuracion']] = $configuration['valor_configuracion'];
		}
		return $this->data;
	}

	public static function getConfiguration()
	{
		$con = Conexion::getInstance();
		$sth = $con->prepare(
			'SELECT id_configuracion, llave_configuracion, valor_configuracion, descripcion_configuracion
			 FROM sistema_configuraciones'
		);
		$sth->execute();
		return $sth->fetchAll();
	}

	/**
	 * Guarda en bloque las llaves editadas desde el panel.
	 */
	public static function saveAll()
	{
		if ($error = Acl::guard("configuracion")) {
			return $error;
		}

		$valores = $_POST["valores"] ?? [];
		if (!is_array($valores) || empty($valores)) {
			return ["status" => "error", "message" => "No hay cambios que guardar"];
		}

		$con = Conexion::getInstance();
		$llaves = array_column(self::getConfiguration(), 'llave_configuracion', 'id_configuracion');
		foreach ($valores as $id => $valor) {
			if (!isset($llaves[$id]) || !is_scalar($valor)) return ['status'=>'error', 'message'=>'Configuración no válida'];
			if ($llaves[$id] === 'inventario.alerta_stock' && (filter_var($valor, FILTER_VALIDATE_INT) === false || (int)$valor < 1)) return ['status'=>'error', 'message'=>'El umbral de stock debe ser un entero mayor a cero'];
		}
		$con->beginTransaction();
		try {
			$sth = $con->prepare("UPDATE sistema_configuraciones SET valor_configuracion = :valor WHERE id_configuracion = :id");
			$n = 0;
			foreach ($valores as $id => $valor) { $sth->execute([":valor" => trim((string)$valor), ":id" => (int)$id]); $n++; }
			$con->commit();
		} catch (\Throwable $e) { $con->rollBack(); throw $e; }

		return ["status" => "success", "message" => "sistema_configuraciones actualizada (" . $n . " llaves)"];
	}
}
