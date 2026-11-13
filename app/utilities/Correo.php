<?php

namespace Develoweb\App\Utilities;

use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

final class Correo
{
	public static function enviarDetallePedido(string $destinatario, string $nombre, array $pedido): bool
	{
		global $_config;
		$admin = $_config['admin'] ?? [];
		$host = trim((string) ($admin['servidor_correo_url'] ?? ''));
		$usuario = trim((string) ($admin['servidor_correo_usuario'] ?? ''));
		$contrasena = (string) ($admin['servidor_correo_contrasena'] ?? '');
		$puerto = (int) ($admin['servidor_correo_puerto'] ?? 587);
		$seguridad = strtolower((string) ($admin['servidor_correo_seguridad'] ?? ($puerto === 465 ? 'ssl' : 'tls')));

		if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL) || $host === '' || $usuario === '' || $puerto < 1 || $puerto > 65535) {
			error_log('Kloset · pedido: falta configurar el servidor SMTP o el destinatario.');
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
			$mailer->addAddress($destinatario, $nombre);
			$mailer->isHTML(true);
			$mailer->Subject = 'Tu pedido ' . (string) ($pedido['ref'] ?? '') . ' está confirmado';

			$filas = '';
			foreach (($pedido['items'] ?? []) as $item) {
				$producto = htmlspecialchars((string) ($item['nombre_producto'] ?? ''), ENT_QUOTES, 'UTF-8');
				$filas .= '<li>' . $producto . ' · talla ' . htmlspecialchars((string) ($item['talla'] ?? ''), ENT_QUOTES, 'UTF-8')
					. ' · cantidad ' . (int) ($item['cantidad'] ?? 0) . '</li>';
			}
			$ref = htmlspecialchars((string) ($pedido['ref'] ?? ''), ENT_QUOTES, 'UTF-8');
			$total = number_format((float) ($pedido['total'] ?? 0), 2, '.', '');
			$mailer->Body = '<h1>Pedido confirmado</h1><p>Hola ' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8')
				. ', recibimos tu pedido <strong>' . $ref . '</strong>.</p><ul>' . $filas
				. '</ul><p>Total: S/ ' . $total . '</p>';
			$mailer->AltBody = 'Pedido confirmado ' . $ref . '. Total: S/ ' . $total;

			return $mailer->send();
		} catch (Throwable $e) {
			error_log('Kloset · envío de detalle de pedido: ' . $e->getMessage());
			return false;
		}
	}

		public static function enviarAprobacionCambio(string $destinatario, string $nombre, array $datos): bool
		{
			global $_config;
			$admin = $_config['admin'] ?? [];
			$host = trim((string) ($admin['servidor_correo_url'] ?? ''));
			$usuario = trim((string) ($admin['servidor_correo_usuario'] ?? ''));
			$puerto = (int) ($admin['servidor_correo_puerto'] ?? 587);
			if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL) || $host === '' || $usuario === '') return false;
			try {
				$mailer = new PHPMailer(true);
				$mailer->isSMTP();
				$mailer->Host = $host;
				$mailer->SMTPAuth = true;
				$mailer->Username = $usuario;
				$mailer->Password = (string) ($admin['servidor_correo_contrasena'] ?? '');
				$mailer->Port = $puerto;
				$mailer->Timeout = 10;
				$mailer->CharSet = PHPMailer::CHARSET_UTF8;
				$seguridad = strtolower((string) ($admin['servidor_correo_seguridad'] ?? ($puerto === 465 ? 'ssl' : 'tls')));
				$mailer->SMTPSecure = $seguridad === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : ($seguridad === 'none' ? '' : PHPMailer::ENCRYPTION_STARTTLS);
				$mailer->setFrom($usuario, 'Kloset');
				$mailer->addAddress($destinatario, $nombre);
				$mailer->isHTML(true);
				$mailer->Subject = 'Tu cambio de prenda ha sido aprobado - Kloset';
				$mailer->Body = '<h1>Cambio de prenda aprobado</h1><p>Hola ' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8')
					. ', revisamos tu solicitud del pedido <strong>' . htmlspecialchars((string) ($datos['codigo_pedido'] ?? ''), ENT_QUOTES, 'UTF-8')
					. '</strong> y fue aprobada.</p><p>Lamentamos el inconveniente. Enviaremos la prenda correcta en los próximos días.</p>';
				$mailer->AltBody = 'Tu cambio de prenda del pedido ' . ($datos['codigo_pedido'] ?? '') . ' fue aprobado. Recibirás la prenda correcta en los próximos días.';
				return $mailer->send();
			} catch (Throwable $e) {
				error_log('Kloset · envío de aprobación de cambio: ' . $e->getMessage());
				return false;
			}
		}
}
