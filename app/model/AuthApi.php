<?php

namespace Develoweb\App\Model;

use Develoweb\App\Utilities\JwtHelper;
use PDO;

class AuthApi
{
	public static function login(): array
	{
		$correo = trim($_POST['correo'] ?? '');
		$password = $_POST['password'] ?? '';
		if ($correo === '' || $password === '') {
			return ['status' => 'error', 'message' => 'Correo y contraseña requeridos'];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare(
			'SELECT u.*, r.nombre_rol FROM sistema_usuarios u
			 INNER JOIN sistema_roles r ON r.id_rol = u.id_rol
			 WHERE u.correo_usuario_sistema = :correo AND u.estado_usuario_sistema = \'activo\''
		);
		$sth->execute([':correo' => $correo]);
		$user = $sth->fetch();

		if (!$user || !password_verify($password, $user['contrasena_usuario_sistema'])) {
			return ['status' => 'error', 'message' => 'Credenciales inválidas'];
		}

		global $_config;
		$token = JwtHelper::encode([
			'sub' => (int) $user['id_usuario_sistema'],
			'rol' => $user['nombre_rol'],
			'correo' => $user['correo_usuario_sistema'],
		], $_config['jwt']['secret'], (int) $_config['jwt']['ttl']);

		return [
			'status' => 'success',
			'token' => $token,
			'usuario' => [
				'id' => (int) $user['id_usuario_sistema'],
				'nombre' => $user['nombre_usuario_sistema'],
				'correo' => $user['correo_usuario_sistema'],
				'rol' => $user['nombre_rol'],
			],
		];
	}

	public static function register(): array
	{
		$nombre = trim($_POST['nombre'] ?? '');
		$correo = trim($_POST['correo'] ?? '');
		$password = $_POST['password'] ?? '';

		if ($nombre === '' || $correo === '' || strlen($password) < 8) {
			return ['status' => 'error', 'message' => 'Datos inválidos (mínimo 8 caracteres en contraseña)'];
		}

		$con = Conexion::getInstance();
		$check = $con->prepare('SELECT id_usuario_sistema FROM sistema_usuarios WHERE correo_usuario_sistema = :correo');
		$check->execute([':correo' => $correo]);
		if ($check->fetch()) {
			return ['status' => 'error', 'message' => 'El correo ya está registrado'];
		}

		$hash = password_hash($password, PASSWORD_DEFAULT);
		$sth = $con->prepare(
			'INSERT INTO sistema_usuarios (id_rol, nombre_usuario_sistema, correo_usuario_sistema, contrasena_usuario_sistema)
			 VALUES (2, :nombre, :correo, :pass)'
		);
		$sth->execute([':nombre' => $nombre, ':correo' => $correo, ':pass' => $hash]);

		$_POST['correo'] = $correo;
		$_POST['password'] = $password;
		return self::login();
	}

	public static function me(): array
	{
		$userId = JwtHelper::bearerUserId();
		if (!$userId) {
			http_response_code(401);
			return ['status' => 'error', 'message' => 'No autorizado'];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare(
			'SELECT u.id_usuario_sistema, u.nombre_usuario_sistema, u.correo_usuario_sistema, r.nombre_rol
			 FROM sistema_usuarios u
			 INNER JOIN sistema_roles r ON r.id_rol = u.id_rol
			 WHERE u.id_usuario_sistema = :id'
		);
		$sth->execute([':id' => $userId]);
		$user = $sth->fetch();
		if (!$user) {
			http_response_code(404);
			return ['status' => 'error', 'message' => 'Usuario no encontrado'];
		}

		return [
			'status' => 'success',
			'usuario' => [
				'id' => (int) $user['id_usuario_sistema'],
				'nombre' => $user['nombre_usuario_sistema'],
				'correo' => $user['correo_usuario_sistema'],
				'rol' => $user['nombre_rol'],
			],
		];
	}
}
