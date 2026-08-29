<?php

namespace Develoweb\App\Model;

use PDO;

class Promociones
{

	private $_msgbox;

	public function __construct ($msg = null)
	{
		$this->_msgbox = $msg;
	}

	public static function get ()
	{
		$con = Conexion::getInstance();
        $sth = $con->prepare(
			"SELECT * FROM promociones"
		);
		$sth->execute();
		$users = $sth->fetchAll();
		return $users;
	}
}