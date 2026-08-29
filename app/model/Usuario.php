<?php

namespace Develoweb\App\Model;

use PDO;

require_once __DIR__ . '/Conexion.php';
require_once __DIR__ . '/Rol.php';
require_once __DIR__ . '/Seccion.php';

class Usuario
{
	private $_id;
	private $_rol;
	private $_nombre;
	private $_correo;
	private $_password;
	private $_estado;
	private $_fecha_creacion;
	private $_secciones;
	private $_logeado = false;

	public function __construct($id = 0)
	{
		$this->_id = $id;
		if ($this->_id <= 0) {
			return;
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT * FROM sistema_usuarios WHERE id_usuario_sistema = :id');
		$sth->bindParam(':id', $this->_id, PDO::PARAM_INT);
		$sth->execute();

		if ($sth->rowCount() === 0) {
			return;
		}

		$user = $sth->fetch();
		$this->_rol = new Rol((int) $user['id_rol']);
		$this->_nombre = $user['nombre_usuario_sistema'];
		$this->_correo = $user['correo_usuario_sistema'];
		$this->_password = $user['contrasena_usuario_sistema'];
		$this->_estado = $user['estado_usuario_sistema'];
		$this->_fecha_creacion = $user['fecha_creacion'];

		$sth = $con->prepare('SELECT id_seccion FROM usuarios_secciones WHERE id_usuario_sistema = :id');
		$sth->bindParam(':id', $this->_id, PDO::PARAM_INT);
		$sth->execute();
		foreach ($sth->fetchAll() as $section) {
			$this->_secciones[] = ['seccion' => new Seccion((int) $section['id_seccion'])];
		}
	}

	public function getModulos()
	{
		$modulos = [];
		if (!is_array($this->_secciones)) {
			return '';
		}
		foreach ($this->_secciones as $value) {
			if (!empty($value['seccion']) && is_object($value['seccion'])) {
				$modulos[] = $value['seccion']->getModulo();
			}
		}
		$modulos = array_unique($modulos);
		return implode(',', $modulos);
	}

	public function findSeccionByCurrentUrl($section_url)
	{
		$sections = $this->getSecciones();
		if (empty($sections)) {
			return false;
		}
		return array_filter($sections, function ($k) use ($section_url) {
			return $k['seccion']->getUrl() === $section_url;
		});
	}

	public function getSeccionesId()
	{
		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT id_seccion FROM usuarios_secciones WHERE id_usuario_sistema = :id');
		$sth->bindParam(':id', $this->_id, PDO::PARAM_INT);
		$sth->execute();
		return array_column($sth->fetchAll(), 'id_seccion');
	}

	public function getId()
	{
		return $this->_id;
	}

	public function getLogeado()
	{
		return $this->_logeado;
	}

	public function setLogeado($valor)
	{
		$this->_logeado = (bool) $valor;
	}

	public function getNombre()
	{
		return $this->_nombre;
	}

	public function getApellidos()
	{
		return '';
	}

	public function getCorreo()
	{
		return $this->_correo;
	}

	public function getSecciones()
	{
		return $this->_secciones ?? [];
	}

	public function getRol()
	{
		return $this->_rol;
	}

	public function getFechaCreacion()
	{
		return $this->_fecha_creacion;
	}

	public function getLogin()
	{
		return $this->_correo;
	}

	public function setLogin($valor)
	{
		$this->_correo = $valor;
	}

	public function __toString()
	{
		return (string) $this->_nombre;
	}
}
