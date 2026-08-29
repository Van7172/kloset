<?php

namespace Develoweb\App\Model;

use PDO;

class Sesion
{
	private $_usuario;
	private $_config;

	public function __construct($config = [])
	{
		if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
			$_SESSION['usuario'] = new Usuario(0);
		}
		$this->_usuario = $_SESSION['usuario'];
		$this->_config = $config;
	}

	public static function signIn()
	{
		if (!Validator::string($_POST['username'] ?? '')) {
			$errors[] = 'username';
		}
		if (!Validator::string($_POST['password'] ?? '')) {
			$errors[] = 'password';
		}
		if (isset($errors)) {
			return ['errors' => $errors];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare(
			'SELECT * FROM sistema_usuarios
			 WHERE correo_usuario_sistema = :username
			   AND estado_usuario_sistema = \'activo\'
			 LIMIT 1'
		);
		$sth->bindParam(':username', $_POST['username'], PDO::PARAM_STR);
		$sth->execute();
		$user = $sth->fetch();

		if (!$user || !password_verify($_POST['password'], $user['contrasena_usuario_sistema'])) {
			return ['errors' => 'El usuario o contraseña son incorrectos'];
		}

		$user_obj = new Usuario((int) $user['id_usuario_sistema']);
		$user_obj->setLogeado(true);
		$_SESSION['sesion']->setUsuario($user_obj);
		$_SESSION['usuario'] = $user_obj;
		$_SESSION['sesion'] = $_SESSION['sesion'];
		return true;
	}

	public function logout()
	{
		unset($_SESSION['usuario'], $_SESSION['sesion']);
		header('Location: ' . DW_PANEL);
		session_destroy();
		exit;
	}

	public function getUsuario()
	{
		return $this->_usuario;
	}

	public function getConfig()
	{
		return $this->_config;
	}

	public function setUsuario($value)
	{
		$this->_usuario = $value;
	}
}
