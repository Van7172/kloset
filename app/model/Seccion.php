<?php

namespace Develoweb\App\Model;

use PDO;

require_once __DIR__ . '/Modulo.php';

class Seccion
{
	private $_id;
	private $_modulo;
	private $_nombre;
	private $_url;
	private $_estado;

	public function __construct($id = 0)
	{
		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT * FROM sistema_secciones WHERE id_seccion = :id');
		$sth->bindParam(':id', $id, PDO::PARAM_INT);
		$sth->execute();

		if ($sth->rowCount() > 0) {
			$seccion = $sth->fetch();
			$this->_id = (int) $seccion['id_seccion'];
			$this->_modulo = new Modulo((int) $seccion['id_modulo']);
			$this->_nombre = $seccion['nombre_seccion'];
			$this->_url = $seccion['url_seccion'];
			$this->_estado = $seccion['estado_seccion'];
		}
	}

	public function getId()
	{
		return $this->_id;
	}

	public function getModulo()
	{
		return $this->_modulo;
	}

	public function getNombre()
	{
		return $this->_nombre;
	}

	public function getUrl()
	{
		return $this->_url;
	}

	public function getEstado()
	{
		return $this->_estado;
	}
}
