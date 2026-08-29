<?php

namespace Develoweb\App\Utilities;

class JwtHelper
{
	public static function encode(array $payload, string $secret, int $ttl): string
	{
		$header = ['typ' => 'JWT', 'alg' => 'HS256'];
		$now = time();
		$payload['iat'] = $now;
		$payload['exp'] = $now + $ttl;

		$segments = [
			self::base64UrlEncode(json_encode($header)),
			self::base64UrlEncode(json_encode($payload)),
		];
		$signing = implode('.', $segments);
		$signature = self::base64UrlEncode(hash_hmac('sha256', $signing, $secret, true));
		return $signing . '.' . $signature;
	}

	public static function decode(string $token, string $secret): object
	{
		$parts = explode('.', $token);
		if (count($parts) !== 3) {
			throw new \RuntimeException('Token inválido');
		}

		[$headerB64, $payloadB64, $signatureB64] = $parts;
		$signing = $headerB64 . '.' . $payloadB64;
		$expected = self::base64UrlEncode(hash_hmac('sha256', $signing, $secret, true));

		if (!hash_equals($expected, $signatureB64)) {
			throw new \RuntimeException('Firma inválida');
		}

		$payload = json_decode(self::base64UrlDecode($payloadB64));
		if (!$payload || ($payload->exp ?? 0) < time()) {
			throw new \RuntimeException('Token expirado');
		}

		return $payload;
	}

	/**
	 * Según la configuración de Apache la cabecera llega en HTTP_AUTHORIZATION,
	 * en REDIRECT_HTTP_AUTHORIZATION (tras el rewrite) o solo vía getallheaders().
	 */
	public static function authorizationHeader(): string
	{
		foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $clave) {
			if (!empty($_SERVER[$clave])) {
				return (string) $_SERVER[$clave];
			}
		}

		if (function_exists('getallheaders')) {
			foreach (getallheaders() as $nombre => $valor) {
				if (strcasecmp($nombre, 'Authorization') === 0) {
					return (string) $valor;
				}
			}
		}

		return '';
	}

	public static function bearerUserId(): ?int
	{
		$header = self::authorizationHeader();
		if (!preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
			return null;
		}
		global $_config;
		try {
			$decoded = self::decode($m[1], $_config['jwt']['secret']);
			return isset($decoded->sub) ? (int) $decoded->sub : null;
		} catch (\Throwable $e) {
			return null;
		}
	}

	private static function base64UrlEncode(string $data): string
	{
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	private static function base64UrlDecode(string $data): string
	{
		$remainder = strlen($data) % 4;
		if ($remainder) {
			$data .= str_repeat('=', 4 - $remainder);
		}
		return base64_decode(strtr($data, '-_', '+/')) ?: '';
	}
}
