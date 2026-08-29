<?php

namespace Develoweb\App\Model;

use PDO;

class Rol
{
	private $_id;
	private $_nombre;

	public function __construct($id = 0)
	{
		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT * FROM sistema_roles WHERE id_rol = :id');
		$sth->bindParam(':id', $id, PDO::PARAM_INT);
		$sth->execute();

		if ($sth->rowCount() > 0) {
			$rol = $sth->fetch();
			$this->_id = (int) $rol['id_rol'];
			$this->_nombre = $rol['nombre_rol'];
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
		return (string) $this->_nombre;
	}
}
