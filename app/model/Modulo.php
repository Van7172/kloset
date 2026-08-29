<?php

namespace Develoweb\App\Model;

use PDO;

class Modulo
{
	private $_id;
	private $_nombre;

	public function __construct($id = 0)
	{
		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT * FROM sistema_modulos WHERE id_modulo = :id');
		$sth->bindParam(':id', $id, PDO::PARAM_INT);
		$sth->execute();

		if ($sth->rowCount() > 0) {
			$modulo = $sth->fetch();
			$this->_id = (int) $modulo['id_modulo'];
			$this->_nombre = $modulo['nombre_modulo'];
		}
	}

	public function getId()
	{
		return $this->_id;
	}

	public function getNombre()
	{
		return $this->_nombre;
	}

	public function __toString()
	{
		return (string) $this->_id;
	}
}
