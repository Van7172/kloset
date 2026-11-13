<?php

namespace Develoweb\App\Model;

class Usuarios
{
	private const SECCION = 'usuarios';
	private const SECCIONES_ASESOR = ['categorias','productos','variantes','pedidos','pagos','notificaciones','clientes'];

	public static function rolesDeAlta(): array
	{
		return Conexion::getInstance()->query("SELECT id_rol,nombre_rol FROM sistema_roles WHERE nombre_rol IN ('Asesor','Cliente') AND estado_rol='activo' ORDER BY FIELD(nombre_rol,'Asesor','Cliente')")->fetchAll();
	}

	public static function seccionesAsesor(): array
	{
		$permitidas = implode(',', array_fill(0, count(self::SECCIONES_ASESOR), '?'));
		$sth = Conexion::getInstance()->prepare("SELECT s.id_seccion,s.nombre_seccion,s.url_seccion,m.nombre_modulo FROM sistema_secciones s JOIN sistema_modulos m ON m.id_modulo=s.id_modulo WHERE s.estado_seccion='activo' AND m.estado_modulo='activo' AND s.url_seccion IN ($permitidas) ORDER BY m.orden_modulo,s.orden_seccion");
		$sth->execute(self::SECCIONES_ASESOR);
		return $sth->fetchAll();
	}

	public static function get(): array
	{
		$con = Conexion::getInstance();
		$sth = $con->query(
			'SELECT u.id_usuario_sistema, u.nombre_usuario_sistema, u.correo_usuario_sistema,
			        u.estado_usuario_sistema, r.id_rol, r.nombre_rol,
			        (SELECT COUNT(*) FROM usuarios_secciones us WHERE us.id_usuario_sistema = u.id_usuario_sistema) AS total_secciones
			 FROM sistema_usuarios u
			 INNER JOIN sistema_roles r ON r.id_rol = u.id_rol
			 ORDER BY u.id_usuario_sistema DESC'
		);
		return $sth->fetchAll();
	}

	/**
	 * Datos del usuario + matriz de acceso (usuarios_secciones).
	 */
	public static function getById(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$id = (int) ($_POST['id'] ?? 0);
		$con = Conexion::getInstance();

		$sth = $con->prepare(
			'SELECT u.id_usuario_sistema, u.nombre_usuario_sistema, u.correo_usuario_sistema,
			        u.estado_usuario_sistema, u.fecha_creacion, r.nombre_rol
			 FROM sistema_usuarios u
			 INNER JOIN sistema_roles r ON r.id_rol = u.id_rol
			 WHERE u.id_usuario_sistema = :id'
		);
		$sth->execute([':id' => $id]);
		$usuario = $sth->fetch();
		if (!$usuario) {
			return ['status' => 'error', 'message' => 'Usuario no encontrado'];
		}

		$asignadas = $con->prepare('SELECT id_seccion FROM usuarios_secciones WHERE id_usuario_sistema = :id');
		$asignadas->execute([':id' => $id]);
		$activas = array_map('intval', array_column($asignadas->fetchAll(), 'id_seccion'));

		$seccionesVisibles = $usuario['nombre_rol'] === 'Asesor'
			? self::seccionesAsesor()
			: $con->query("SELECT s.id_seccion,s.nombre_seccion,s.url_seccion,m.nombre_modulo FROM sistema_secciones s JOIN sistema_modulos m ON m.id_modulo=s.id_modulo WHERE s.estado_seccion='activo' ORDER BY m.orden_modulo,s.orden_seccion")->fetchAll();

		$secciones = [];
		foreach ($seccionesVisibles as $sec) {
			$secciones[] = [
				'id_seccion' => (int) $sec['id_seccion'],
				'nombre_seccion' => $sec['nombre_seccion'],
				'url_seccion' => $sec['url_seccion'],
				'nombre_modulo' => $sec['nombre_modulo'],
				'activa' => in_array((int) $sec['id_seccion'], $activas, true),
			];
		}

		return ['status' => 'success', 'usuario' => $usuario, 'secciones' => $secciones];
	}

	public static function store(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$nombre = trim((string) ($_POST['nombre'] ?? ''));
		$correo = mb_strtolower(trim((string) ($_POST['correo'] ?? '')));
		$password = (string) ($_POST['password'] ?? '');
		$idRol = (int) ($_POST['id_rol'] ?? 0);

		if ($nombre === '') {
			return ['status' => 'error', 'message' => 'El nombre es obligatorio'];
		}
		if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
			return ['status' => 'error', 'message' => 'El correo no es válido'];
		}
		if (strlen($password) < 8 || strlen($password) > 72 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
			return ['status' => 'error', 'message' => 'La contraseña necesita 8 a 72 caracteres, mayúscula, minúscula, número y símbolo'];
		}
		if ($idRol <= 0) {
			return ['status' => 'error', 'message' => 'Selecciona un rol'];
		}

		$con = Conexion::getInstance();
		$rol = $con->prepare("SELECT nombre_rol FROM sistema_roles WHERE id_rol = ? AND estado_rol = 'activo'");
		$rol->execute([$idRol]);
		$nombreRol = $rol->fetchColumn();
		if (!in_array($nombreRol, ['Asesor', 'Cliente'], true)) return ['status'=>'error', 'message'=>'Solo puedes crear asesores o clientes'];
		if (mb_strlen($nombre) > 120 || mb_strlen($correo) > 150 || strlen($password) > 72) return ['status'=>'error', 'message'=>'Nombre, correo o contraseña demasiado largos'];
		$secciones = array_values(array_unique(array_map('intval', (array) ($_POST['secciones'] ?? []))));
		$validas = array_map('intval', array_column(self::seccionesAsesor(), 'id_seccion'));
		if ($nombreRol === 'Cliente' && $secciones) return ['status'=>'error', 'message'=>'Los clientes no tienen acceso al panel'];
		if ($nombreRol === 'Asesor' && (!$secciones || array_diff($secciones, $validas))) return ['status'=>'error', 'message'=>'Selecciona al menos una sección permitida para el asesor'];
		$sth = $con->prepare('SELECT COUNT(*) FROM sistema_usuarios WHERE correo_usuario_sistema = :correo');
		$sth->execute([':correo' => $correo]);
		if ((int) $sth->fetchColumn() > 0) {
			return ['status' => 'error', 'message' => 'Ese correo ya está registrado'];
		}

		$con->beginTransaction();
		try {
		$sth = $con->prepare(
			'INSERT INTO sistema_usuarios (id_rol, nombre_usuario_sistema, correo_usuario_sistema, contrasena_usuario_sistema)
			 VALUES (:rol, :nombre, :correo, :pass)'
		);
		$sth->execute([
			':rol' => $idRol,
			':nombre' => $nombre,
			':correo' => $correo,
			':pass' => password_hash($password, PASSWORD_DEFAULT),
		]);

		$id = (int) $con->lastInsertId();
		if ($nombreRol === 'Asesor') {
			$insert = $con->prepare('INSERT INTO usuarios_secciones (id_usuario_sistema,id_seccion) VALUES (?,?)');
			foreach ($secciones as $seccion) $insert->execute([$id,$seccion]);
		}
		$con->commit();
		return ['status' => 'success', 'message' => $nombreRol . ' creado', 'id' => $id];
		} catch (\Throwable $e) {
			$con->rollBack();
			if ($e instanceof \PDOException && $e->getCode() === '23000') return ['status'=>'error','message'=>'Ese correo ya está registrado'];
			throw $e;
		}
	}

	/**
	 * Reescribe usuarios_secciones para un usuario.
	 */
	public static function updateSecciones(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$id = (int) ($_POST['id'] ?? 0);
		if ($id <= 0) {
			return ['status' => 'error', 'message' => 'Usuario no válido'];
		}

		$secciones = array_map('intval', (array) ($_POST['secciones'] ?? []));
		$secciones = array_values(array_unique(array_filter($secciones, fn ($v) => $v > 0)));

		$con = Conexion::getInstance();
		$existe = $con->prepare('SELECT r.nombre_rol FROM sistema_usuarios u JOIN sistema_roles r ON r.id_rol=u.id_rol WHERE u.id_usuario_sistema = ?');
		$existe->execute([$id]);
		$nombreRol = $existe->fetchColumn();
		if (!$nombreRol) return ['status'=>'error', 'message'=>'Usuario no encontrado'];
		if ($nombreRol === 'Cliente') return ['status'=>'error','message'=>'Los clientes no tienen acceso al panel'];
		$validas = array_map('intval', array_column(self::seccionesAsesor(), 'id_seccion'));
		if ($nombreRol === 'Administrador') $validas = array_map('intval', array_column($con->query("SELECT id_seccion FROM sistema_secciones WHERE estado_seccion='activo'")->fetchAll(), 'id_seccion'));
		if (array_diff($secciones, $validas)) return ['status'=>'error', 'message'=>'Una sección solicitada no existe o está inactiva'];
		if ($nombreRol === 'Asesor' && !$secciones) return ['status'=>'error','message'=>'El asesor necesita al menos una sección'];

		// Evita que el administrador se deje a sí mismo sin acceso al ACL
		$actual = Acl::usuario();
		if ($actual && $actual->getId() === $id) {
			$sth = $con->prepare('SELECT id_seccion FROM sistema_secciones WHERE url_seccion = :url');
			$sth->execute([':url' => self::SECCION]);
			$propia = (int) $sth->fetchColumn();
			if ($propia > 0 && !in_array($propia, $secciones, true)) {
				return ['status' => 'error', 'message' => 'No puedes quitarte el acceso a Usuarios'];
			}
		}

		$con->beginTransaction();
		try {
			$con->prepare('DELETE FROM usuarios_secciones WHERE id_usuario_sistema = :id')->execute([':id' => $id]);
			$insert = $con->prepare('INSERT INTO usuarios_secciones (id_usuario_sistema, id_seccion) VALUES (:u, :s)');
			foreach ($secciones as $seccion) {
				$insert->execute([':u' => $id, ':s' => $seccion]);
			}
			$con->commit();
		} catch (\Throwable $e) {
			$con->rollBack();
			return ['status' => 'error', 'message' => 'No se pudo guardar el acceso'];
		}

		return [
			'status' => 'success',
			'message' => 'usuarios_secciones actualizada (' . count($secciones) . ' secciones)',
			'total' => count($secciones),
		];
	}
}
