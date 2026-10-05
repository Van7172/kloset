<?php

namespace Develoweb\App\Model;

use Develoweb\App\Utilities\JwtHelper;
use PHPMailer\PHPMailer\PHPMailer;
use PDO;
use Throwable;

class AuthApi
{
	public static function login(): array
	{
		$correo = trim($_POST['correo'] ?? '');
		$password = $_POST['password'] ?? '';
		if ($correo === '' || $password === '') {
			return ['status' => 'error', 'message' => 'Correo y contraseña requeridos'];
		}
		if (!self::cumplePoliticaContrasena($password)) {
			http_response_code(422);
			return ['status' => 'error', 'message' => self::mensajePoliticaContrasena()];
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
		$correo = mb_strtolower(trim((string) ($_POST['correo'] ?? '')));
		$password = (string) ($_POST['password'] ?? '');
		$confirmarPassword = (string) ($_POST['confirmar_password'] ?? '');

		if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 120 || !filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 150) {
			http_response_code(422);
			return ['status' => 'error', 'message' => 'Revisa tu nombre y correo electrónico.'];
		}
		if (!self::cumplePoliticaContrasena($password)) {
			http_response_code(422);
			return ['status' => 'error', 'message' => self::mensajePoliticaContrasena()];
		}
		if ($password !== $confirmarPassword) {
			http_response_code(422);
			return ['status' => 'error', 'message' => 'Las contraseñas no coinciden.'];
		}

		$con = Conexion::getInstance();
		$check = $con->prepare('SELECT id_usuario_sistema FROM sistema_usuarios WHERE LOWER(correo_usuario_sistema) = :correo');
		$check->execute([':correo' => $correo]);
		if ($check->fetchColumn()) {
			http_response_code(409);
			return ['status' => 'error', 'message' => 'El correo ya está registrado'];
		}

		return self::crearRegistroPendiente($con, $nombre, $correo, $password);
	}

	private static function crearRegistroPendiente(PDO $con, string $nombre, string $correo, string $password): array
	{
		try {
			$con->beginTransaction();
			$consulta = $con->prepare(
				"SELECT id_codigo_verificacion, solicitado_en, usado_en FROM codigos_verificacion
				 WHERE tipo_codigo = 'registro' AND correo_codigo = :correo
				 ORDER BY id_codigo_verificacion DESC LIMIT 1 FOR UPDATE"
			);
			$consulta->execute([':correo' => $correo]);
			$pendiente = $consulta->fetch(PDO::FETCH_ASSOC);
			if ($pendiente && $pendiente['usado_en'] === null && strtotime($pendiente['solicitado_en']) > time() - 60) {
				$espera = max(1, 60 - (time() - strtotime($pendiente['solicitado_en'])));
				$con->commit();
				return ['status' => 'success', 'message' => 'Si el correo puede registrarse, recibirás un código de verificación.', 'reenviar_en' => $espera];
			}

			$codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
			$datos = [
				':nombre' => $nombre,
				':correo' => $correo,
				':contrasena' => password_hash($password, PASSWORD_DEFAULT),
				':codigo' => password_hash($codigo, PASSWORD_DEFAULT),
			];
			if ($pendiente) {
				$con->prepare(
					'UPDATE codigos_verificacion SET nombre_registro = :nombre,
					 contrasena_hash = :contrasena, codigo_hash = :codigo,
					 expira_en = DATE_ADD(NOW(), INTERVAL 10 MINUTE), intentos = 0,
					 solicitado_en = NOW(), usado_en = NULL WHERE id_codigo_verificacion = :id'
				)->execute([
					':nombre' => $nombre,
					':contrasena' => $datos[':contrasena'],
					':codigo' => $datos[':codigo'],
					':id' => (int) $pendiente['id_codigo_verificacion'],
				]);
				$idPendiente = (int) $pendiente['id_codigo_verificacion'];
			} else {
				$con->prepare(
					"INSERT INTO codigos_verificacion
					 (tipo_codigo, nombre_registro, correo_codigo, contrasena_hash, codigo_hash, expira_en)
					 VALUES ('registro', :nombre, :correo, :contrasena, :codigo, DATE_ADD(NOW(), INTERVAL 10 MINUTE))"
				)->execute($datos);
				$idPendiente = (int) $con->lastInsertId();
			}
			$con->commit();

			if (!self::enviarCodigoCorreo($correo, $codigo, 'registro')) {
				$con->prepare('UPDATE codigos_verificacion SET usado_en = NOW() WHERE id_codigo_verificacion = :id')
					->execute([':id' => $idPendiente]);
				http_response_code(503);
				return ['status' => 'error', 'message' => 'No pudimos enviar el código. Revisa la configuración del correo e inténtalo nuevamente.'];
			}
		} catch (Throwable $e) {
			if ($con->inTransaction()) $con->rollBack();
			error_log('Kloset · inicio de registro: ' . $e->getMessage());
			http_response_code(500);
			return ['status' => 'error', 'message' => 'No pudimos iniciar el registro. Inténtalo nuevamente.'];
		}

		return ['status' => 'success', 'message' => 'Si el correo puede registrarse, recibirás un código de verificación.', 'reenviar_en' => 60];
	}

	public static function reenviarCodigoRegistro(): array
	{
		$correo = mb_strtolower(trim((string) ($_POST['correo'] ?? '')));
		if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 150) {
			http_response_code(422);
			return ['status' => 'error', 'message' => 'Ingresa un correo válido.'];
		}

		$con = Conexion::getInstance();
		try {
			$con->beginTransaction();
			$consulta = $con->prepare(
				"SELECT id_codigo_verificacion, solicitado_en FROM codigos_verificacion
				 WHERE tipo_codigo = 'registro' AND correo_codigo = :correo AND usado_en IS NULL
				 ORDER BY id_codigo_verificacion DESC LIMIT 1 FOR UPDATE"
			);
			$consulta->execute([':correo' => $correo]);
			$pendiente = $consulta->fetch(PDO::FETCH_ASSOC);
			if (!$pendiente) {
				$con->rollBack();
				http_response_code(422);
				return ['status' => 'error', 'message' => 'No hay un registro pendiente para este correo.'];
			}
			$transcurrido = time() - strtotime($pendiente['solicitado_en']);
			if ($transcurrido < 60) {
				$con->commit();
				return ['status' => 'success', 'message' => 'Espera antes de solicitar otro código.', 'reenviar_en' => max(1, 60 - $transcurrido)];
			}

			$codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
			$con->prepare(
				'UPDATE codigos_verificacion SET codigo_hash = :codigo,
				 expira_en = DATE_ADD(NOW(), INTERVAL 10 MINUTE), intentos = 0, solicitado_en = NOW()
				 WHERE id_codigo_verificacion = :id'
			)->execute([
				':codigo' => password_hash($codigo, PASSWORD_DEFAULT),
				':id' => (int) $pendiente['id_codigo_verificacion'],
			]);
			$idPendiente = (int) $pendiente['id_codigo_verificacion'];
			$con->commit();

			if (!self::enviarCodigoCorreo($correo, $codigo, 'registro')) {
				$con->prepare('UPDATE codigos_verificacion SET usado_en = NOW() WHERE id_codigo_verificacion = :id')
					->execute([':id' => $idPendiente]);
				http_response_code(503);
				return ['status' => 'error', 'message' => 'No pudimos enviar el código. Inténtalo nuevamente.'];
			}
		} catch (Throwable $e) {
			if ($con->inTransaction()) $con->rollBack();
			error_log('Kloset · reenvío de registro: ' . $e->getMessage());
			http_response_code(500);
			return ['status' => 'error', 'message' => 'No pudimos reenviar el código. Inténtalo nuevamente.'];
		}

		return ['status' => 'success', 'message' => 'Código reenviado.', 'reenviar_en' => 60];
	}

	public static function verificarRegistro(): array
	{
		$correo = mb_strtolower(trim((string) ($_POST['correo'] ?? '')));
		$codigo = trim((string) ($_POST['codigo'] ?? ''));
		if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 150 || !preg_match('/^\d{6}$/', $codigo)) {
			http_response_code(422);
			return ['status' => 'error', 'message' => 'Ingresa el código de verificación de 6 dígitos.'];
		}

		$con = Conexion::getInstance();
		try {
			$con->beginTransaction();
			$consulta = $con->prepare(
				"SELECT id_codigo_verificacion, nombre_registro, correo_codigo,
				 contrasena_hash, codigo_hash, expira_en, intentos FROM codigos_verificacion
				 WHERE tipo_codigo = 'registro' AND correo_codigo = :correo AND usado_en IS NULL
				 ORDER BY id_codigo_verificacion DESC LIMIT 1 FOR UPDATE"
			);
			$consulta->execute([':correo' => $correo]);
			$pendiente = $consulta->fetch(PDO::FETCH_ASSOC);
			if (!$pendiente || strtotime($pendiente['expira_en']) <= time() || (int) $pendiente['intentos'] >= 5) {
				if ($pendiente) {
					$con->prepare('UPDATE codigos_verificacion SET usado_en = NOW() WHERE id_codigo_verificacion = :id')
						->execute([':id' => (int) $pendiente['id_codigo_verificacion']]);
				}
				$con->commit();
				http_response_code(422);
				return ['status' => 'error', 'message' => 'El código no es válido o ha vencido.'];
			}

			if (!password_verify($codigo, $pendiente['codigo_hash'])) {
				$con->prepare(
					'UPDATE codigos_verificacion SET intentos = intentos + 1,
				 usado_en = IF(intentos + 1 >= 5, NOW(), usado_en) WHERE id_codigo_verificacion = :id'
				)->execute([':id' => (int) $pendiente['id_codigo_verificacion']]);
				$con->commit();
				http_response_code(422);
				return ['status' => 'error', 'message' => 'El código no es válido o ha vencido.'];
			}

			$check = $con->prepare('SELECT id_usuario_sistema FROM sistema_usuarios WHERE LOWER(correo_usuario_sistema) = :correo');
			$check->execute([':correo' => $correo]);
			if ($check->fetchColumn()) {
				$con->prepare('UPDATE codigos_verificacion SET usado_en = NOW() WHERE id_codigo_verificacion = :id')
					->execute([':id' => (int) $pendiente['id_codigo_verificacion']]);
				$con->commit();
				http_response_code(409);
				return ['status' => 'error', 'message' => 'El correo ya está registrado.'];
			}

			$insertar = $con->prepare(
				'INSERT INTO sistema_usuarios (id_rol, nombre_usuario_sistema, correo_usuario_sistema, contrasena_usuario_sistema)
				 VALUES (2, :nombre, :correo, :contrasena)'
			);
			$insertar->execute([
				':nombre' => $pendiente['nombre_registro'],
				':correo' => $pendiente['correo_codigo'],
				':contrasena' => $pendiente['contrasena_hash'],
			]);
			$con->prepare('UPDATE codigos_verificacion SET usado_en = NOW() WHERE id_codigo_verificacion = :id')
				->execute([':id' => (int) $pendiente['id_codigo_verificacion']]);
			$con->commit();
		} catch (Throwable $e) {
			if ($con->inTransaction()) $con->rollBack();
			error_log('Kloset · verificación de registro: ' . $e->getMessage());
			http_response_code(500);
			return ['status' => 'error', 'message' => 'No pudimos verificar el correo. Inténtalo nuevamente.'];
		}

		return ['status' => 'success', 'message' => 'Correo verificado. Tu cuenta ya está creada.'];
	}

	public static function solicitarRecuperacion(): array
	{
		$correo = mb_strtolower(trim((string) ($_POST['correo'] ?? '')));
		if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 150) {
			http_response_code(422);
			return ['status' => 'error', 'message' => 'Ingresa un correo válido.'];
		}

		$con = Conexion::getInstance();
		try {
			$con->beginTransaction();
			$usuario = $con->prepare(
				"SELECT id_usuario_sistema FROM sistema_usuarios
				 WHERE LOWER(correo_usuario_sistema) = :correo AND id_rol = 2 AND estado_usuario_sistema = 'activo'
				 FOR UPDATE"
			);
			$usuario->execute([':correo' => $correo]);
			$idUsuario = $usuario->fetchColumn();
			if (!$idUsuario) {
				$con->commit();
				return self::respuestaRecuperacion();
			}

			$consulta = $con->prepare(
				"SELECT id_codigo_verificacion, solicitado_en FROM codigos_verificacion
				 WHERE tipo_codigo = 'recuperacion' AND correo_codigo = :correo
				 ORDER BY id_codigo_verificacion DESC LIMIT 1 FOR UPDATE"
			);
			$consulta->execute([':correo' => $correo]);
			$recuperacion = $consulta->fetch(PDO::FETCH_ASSOC);
			if ($recuperacion && strtotime($recuperacion['solicitado_en']) > time() - 60) {
				$con->commit();
				return self::respuestaRecuperacion();
			}

			$codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
			if ($recuperacion) {
				$con->prepare(
					'UPDATE codigos_verificacion SET id_usuario_sistema = :usuario,
					 codigo_hash = :codigo, expira_en = DATE_ADD(NOW(), INTERVAL 10 MINUTE),
					 intentos = 0, solicitado_en = NOW(), usado_en = NULL
					 WHERE id_codigo_verificacion = :id'
				)->execute([
					':usuario' => (int) $idUsuario,
					':codigo' => password_hash($codigo, PASSWORD_DEFAULT),
					':id' => (int) $recuperacion['id_codigo_verificacion'],
				]);
				$idRecuperacion = (int) $recuperacion['id_codigo_verificacion'];
			} else {
				$insertar = $con->prepare(
					"INSERT INTO codigos_verificacion
					 (tipo_codigo, id_usuario_sistema, correo_codigo, codigo_hash, expira_en)
					 VALUES ('recuperacion', :usuario, :correo, :codigo, DATE_ADD(NOW(), INTERVAL 10 MINUTE))"
				);
				$insertar->execute([
					':usuario' => (int) $idUsuario,
					':correo' => $correo,
					':codigo' => password_hash($codigo, PASSWORD_DEFAULT),
				]);
				$idRecuperacion = (int) $con->lastInsertId();
			}
			$con->commit();

			if (!self::enviarCodigoCorreo($correo, $codigo, 'recuperacion')) {
				$con->prepare('UPDATE codigos_verificacion SET usado_en = NOW() WHERE id_codigo_verificacion = :id')
					->execute([':id' => $idRecuperacion]);
			}
		} catch (Throwable $e) {
			if ($con->inTransaction()) $con->rollBack();
			error_log('Kloset · solicitud de recuperación: ' . $e->getMessage());
			http_response_code(500);
			return ['status' => 'error', 'message' => 'No pudimos procesar la solicitud. Inténtalo nuevamente.'];
		}

		return self::respuestaRecuperacion();
	}

	public static function restablecerContrasena(): array
	{
		$correo = mb_strtolower(trim((string) ($_POST['correo'] ?? '')));
		$codigo = trim((string) ($_POST['codigo'] ?? ''));
		$password = (string) ($_POST['password'] ?? '');
		$confirmarPassword = (string) ($_POST['confirmar_password'] ?? '');
		if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 150
			|| !preg_match('/^\d{6}$/', $codigo)) {
			http_response_code(422);
			return ['status' => 'error', 'message' => 'Ingresa un correo válido y el código de 6 dígitos.'];
		}
		if (!self::cumplePoliticaContrasena($password)) {
			http_response_code(422);
			return ['status' => 'error', 'message' => self::mensajePoliticaContrasena()];
		}
		if ($password !== $confirmarPassword) {
			http_response_code(422);
			return ['status' => 'error', 'message' => 'Las contraseñas no coinciden.'];
		}

		$con = Conexion::getInstance();
		try {
			$con->beginTransaction();
			$usuario = $con->prepare(
				"SELECT id_usuario_sistema, contrasena_usuario_sistema FROM sistema_usuarios
				 WHERE LOWER(correo_usuario_sistema) = :correo AND id_rol = 2 AND estado_usuario_sistema = 'activo'
				 FOR UPDATE"
			);
			$usuario->execute([':correo' => $correo]);
			$datosUsuario = $usuario->fetch(PDO::FETCH_ASSOC);
			$idUsuario = $datosUsuario['id_usuario_sistema'] ?? null;
			if (!$idUsuario) {
				$con->rollBack();
				http_response_code(422);
				return ['status' => 'error', 'message' => 'El código no es válido o ha vencido.'];
			}

			$consulta = $con->prepare(
				"SELECT id_codigo_verificacion, codigo_hash, expira_en, intentos FROM codigos_verificacion
				 WHERE tipo_codigo = 'recuperacion' AND id_usuario_sistema = :usuario AND usado_en IS NULL
				 ORDER BY id_codigo_verificacion DESC LIMIT 1 FOR UPDATE"
			);
			$consulta->execute([':usuario' => (int) $idUsuario]);
			$recuperacion = $consulta->fetch(PDO::FETCH_ASSOC);
			if (!$recuperacion || strtotime($recuperacion['expira_en']) <= time() || (int) $recuperacion['intentos'] >= 5) {
				if ($recuperacion) {
					$con->prepare('UPDATE codigos_verificacion SET usado_en = NOW() WHERE id_codigo_verificacion = :id')
						->execute([':id' => (int) $recuperacion['id_codigo_verificacion']]);
				}
				$con->commit();
				http_response_code(422);
				return ['status' => 'error', 'message' => 'El código no es válido o ha vencido.'];
			}

			if (!password_verify($codigo, $recuperacion['codigo_hash'])) {
				$con->prepare(
					'UPDATE codigos_verificacion
					 SET intentos = intentos + 1, usado_en = IF(intentos + 1 >= 5, NOW(), usado_en)
					 WHERE id_codigo_verificacion = :id'
				)->execute([':id' => (int) $recuperacion['id_codigo_verificacion']]);
				$con->commit();
				http_response_code(422);
				return ['status' => 'error', 'message' => 'El código no es válido o ha vencido.'];
			}

			if (password_verify($password, $datosUsuario['contrasena_usuario_sistema'])) {
				$con->rollBack();
				http_response_code(422);
				return ['status' => 'error', 'message' => 'La nueva contraseña debe ser distinta de la contraseña actual.'];
			}

			$con->prepare('UPDATE sistema_usuarios SET contrasena_usuario_sistema = :password WHERE id_usuario_sistema = :usuario')
				->execute([':password' => password_hash($password, PASSWORD_DEFAULT), ':usuario' => (int) $idUsuario]);
			$con->prepare('UPDATE codigos_verificacion SET usado_en = NOW() WHERE id_codigo_verificacion = :id')
				->execute([':id' => (int) $recuperacion['id_codigo_verificacion']]);
			$con->commit();
		} catch (Throwable $e) {
			if ($con->inTransaction()) $con->rollBack();
			error_log('Kloset · restablecimiento de contraseña: ' . $e->getMessage());
			http_response_code(500);
			return ['status' => 'error', 'message' => 'No pudimos actualizar la contraseña. Inténtalo nuevamente.'];
		}

		return ['status' => 'success', 'message' => 'Contraseña actualizada. Ya puedes iniciar sesión.'];
	}

	private static function respuestaRecuperacion(): array
	{
		return [
			'status' => 'success',
			'message' => 'Si el correo está registrado, recibirás un código para restablecer tu contraseña.',
			'reenviar_en' => 60,
		];
	}

	public static function cumplePoliticaContrasena(string $password): bool
	{
		return strlen($password) >= 8 && strlen($password) <= 128
			&& preg_match('/\p{Lu}/u', $password) === 1
			&& preg_match('/\p{Ll}/u', $password) === 1
			&& preg_match('/\p{N}/u', $password) === 1
			&& preg_match('/[^\p{L}\p{N}\s]/u', $password) === 1;
	}

	public static function mensajePoliticaContrasena(): string
	{
		return 'La contraseña debe tener entre 8 y 128 caracteres, una mayúscula, una minúscula, un número y un carácter especial.';
	}

	private static function enviarCodigoCorreo(string $correo, string $codigo, string $tipo): bool
	{
		global $_config;
		$admin = $_config['admin'] ?? [];
		$host = trim((string) ($admin['servidor_correo_url'] ?? ''));
		$usuario = trim((string) ($admin['servidor_correo_usuario'] ?? ''));
		$contrasena = (string) ($admin['servidor_correo_contrasena'] ?? '');
		$puertoConfig = $admin['servidor_correo_puerto'] ?? getenv('KLOSET_SMTP_PORT');
		$puerto = $puertoConfig !== false && $puertoConfig !== null ? (int) $puertoConfig : 587;
		$seguridadConfig = $admin['servidor_correo_seguridad'] ?? getenv('KLOSET_SMTP_SECURITY');
		$seguridad = strtolower((string) ($seguridadConfig ?: ($puerto === 465 ? 'ssl' : 'tls')));

		if ($host === '' || $usuario === '' || $puerto < 1 || $puerto > 65535) {
			error_log('Kloset · recuperación: falta configurar el servidor SMTP y su cuenta remitente.');
			return false;
		}

		try {
			$mailer = new PHPMailer(true);
			$mailer->isSMTP();
			$mailer->Host = $host;
			$mailer->SMTPAuth = true;
			$mailer->Username = $usuario;
			$mailer->Password = $contrasena;
			$mailer->Port = $puerto;
			$mailer->Timeout = 10;
			$mailer->CharSet = PHPMailer::CHARSET_UTF8;
			$mailer->SMTPSecure = $seguridad === 'ssl'
				? PHPMailer::ENCRYPTION_SMTPS
				: ($seguridad === 'none' ? '' : PHPMailer::ENCRYPTION_STARTTLS);
			$mailer->setFrom($usuario, 'Kloset');
			$mailer->addAddress($correo);
			$mailer->isHTML(true);
			$esRegistro = $tipo === 'registro';
			$mailer->Subject = $esRegistro ? 'Verifica tu correo electrónico en Kloset' : 'Tu código para restablecer la contraseña';
			$titulo = $esRegistro ? 'Tu código para verificar tu cuenta' : 'Tu código para restablecer la contraseña';
			$instruccion = $esRegistro
				? 'Utiliza el siguiente código para verificar tu correo y continuar con la creación de tu cuenta.'
				: 'Utiliza el siguiente código para continuar con el restablecimiento de tu contraseña.';
			$codigoSeguro = htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8');
			$casillas = '';
			foreach (str_split($codigoSeguro) as $digito) {
				$casillas .= '<td style="width:58px;height:76px;border:2px solid #ef1d25;border-radius:6px;background:#fff8f8;color:#080808;font-family:Arial,sans-serif;font-size:48px;font-weight:700;line-height:76px;text-align:center;">' . $digito . '</td><td style="width:7px;font-size:1px;">&nbsp;</td>';
			}
			$mailer->Body = '<!doctype html><html lang="es"><body style="margin:0;background:#f7f7f6;color:#111;font-family:Arial,sans-serif;">'
				. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f7f6;padding:18px 0;"><tr><td align="center">'
				. '<table role="presentation" width="760" cellpadding="0" cellspacing="0" style="width:100%;max-width:760px;background:#fff;border:1px solid #dededb;padding:28px 42px 34px;">'
				. '<tr><td align="center" style="padding:0 0 20px;border-bottom:1px solid #cfcfcb;"><div style="font-family:Georgia,serif;font-size:32px;font-weight:700;letter-spacing:7px;line-height:1;">KLOSET</div><div style="width:208px;border-bottom:3px solid #ef1d25;margin:7px auto 8px;"></div><div style="font-size:11px;letter-spacing:5px;color:#333;">TU ESTILO, TU MOVIMIENTO</div></td></tr>'
				. '<tr><td align="center" style="padding:62px 0 0;"><h1 style="margin:0;color:#080808;font-family:Georgia,serif;font-size:46px;line-height:1.08;font-weight:700;">' . $titulo . '</h1><p style="max-width:540px;margin:24px auto 0;color:#60636a;font-size:21px;line-height:1.45;">' . $instruccion . '</p></td></tr>'
				. '<tr><td align="center" style="padding:48px 0 0;"><table role="presentation" cellpadding="0" cellspacing="0"><tr>' . $casillas . '</tr></table></td></tr>'
				. '<tr><td align="center" style="padding:44px 0 0;color:#60636a;font-size:18px;line-height:1.5;">Este código vence en <strong style="color:#111;">10 minutos.</strong><br><span style="font-size:16px;">Si no solicitaste esta acción, ignora este mensaje.</span></td></tr>'
				. '<tr><td align="center" style="padding:54px 0 0;border-top:1px solid #cfcfcb;margin-top:54px;"><div style="font-family:Georgia,serif;font-size:28px;font-weight:700;letter-spacing:7px;">KLOSET</div><div style="width:145px;border-bottom:3px solid #ef1d25;margin:6px auto 7px;"></div><div style="font-size:8px;letter-spacing:4px;color:#333;">TU ESTILO, TU MOVIMIENTO</div></td></tr>'
				. '</table></td></tr></table></body></html>';
			$mailer->AltBody = "$titulo. $instruccion Código: $codigo. Vence en 10 minutos. Si no solicitaste esta acción, ignora este mensaje.";
			return $mailer->send();
		} catch (Throwable $e) {
			error_log('Kloset · envío de código de verificación: ' . $e->getMessage());
			return false;
		}
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
