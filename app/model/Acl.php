<?php

namespace Develoweb\App\Model;

class Acl
{
	public static function usuario(): ?Usuario
	{
		$usuario = $_SESSION['usuario'] ?? null;
		if (!$usuario instanceof Usuario || $usuario->getId() <= 0) {
			return null;
		}
		return $usuario;
	}

	public static function allows(string $url_seccion): bool
	{
		$usuario = self::usuario();
		if ($usuario === null) {
			return false;
		}
		return !empty($usuario->findSeccionByCurrentUrl($url_seccion));
	}

	/**
	 * Devuelve null si el usuario puede operar en la sección; si no, el error listo para responder.
	 */
	public static function guard(string $url_seccion): ?array
	{
		if (self::usuario() === null) {
			return ['status' => 'error', 'message' => 'Sesión expirada'];
		}
		if (!self::allows($url_seccion)) {
			return ['status' => 'error', 'message' => 'No tienes permisos sobre esta sección'];
		}
		return null;
	}
}
