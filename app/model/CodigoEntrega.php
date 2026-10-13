<?php

namespace Develoweb\App\Model;

final class CodigoEntrega
{
    private static function key(): string
    {
        global $_config;
        return hash('sha256', (string) ($_config['jwt']['secret'] ?? ''), true);
    }

    public static function crear(): array
    {
        $codigo = (string) random_int(10000000, 99999999);
        $iv = random_bytes(12);
        $tag = '';
        $cifrado = openssl_encrypt($codigo, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cifrado === false) throw new \RuntimeException('No se pudo crear el código de entrega');
        return [password_hash($codigo, PASSWORD_DEFAULT), base64_encode($iv . $tag . $cifrado)];
    }

    public static function abrir(?string $cifrado): ?string
    {
        if (!$cifrado) return null;
        $raw = base64_decode($cifrado, true);
        if ($raw === false || strlen($raw) < 29) return null;
        $codigo = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $codigo === false ? null : $codigo;
    }
}
