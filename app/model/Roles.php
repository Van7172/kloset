<?php

namespace Develoweb\App\Model;

use PDO;

class Roles
{
	public static function getAll()
	{
		$con = Conexion::getInstance();
		$sth = $con->prepare(
			"SELECT id_rol, nombre_rol FROM sistema_roles WHERE estado_rol = 'activo' ORDER BY id_rol"
		);
		$sth->execute();
		return $sth->fetchAll();
	}
}
