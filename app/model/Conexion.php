<?php

namespace Develoweb\App\Model;

use PDO;
use PDOException;

if (basename($_SERVER['PHP_SELF']) === "conexion.php") exit;

class Conexion
{

	private static $instance = null;
	private $pdo;

	public function __construct() {

		$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE . ";port=" . DB_PORT . ";charset=utf8mb4";
		try {
			$this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_PERSISTENT => true
			]);
		} catch (PDOException $e) {
			// El detalle va al log; al cliente nunca, para no exponer credenciales
			error_log('Kloset · fallo de conexión a la base de datos: ' . $e->getMessage());
			http_response_code(503);

			$uri = $_SERVER['REQUEST_URI'] ?? '';
			$script = basename($_SERVER['SCRIPT_NAME'] ?? '');
			$esApi = str_contains($uri, '/api/') || $script === 'ajax.php';

			if (!headers_sent()) {
				header('Content-Type: ' . ($esApi ? 'application/json' : 'text/html') . '; charset=utf-8');
				header('Retry-After: 120');
			}

			if ($esApi) {
				die(json_encode(['status' => 'error', 'message' => 'Servicio no disponible temporalmente']));
			}

			die('<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
				. '<meta name="viewport" content="width=device-width,initial-scale=1">'
				. '<title>Kloset · servicio no disponible</title>'
				. '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#F2F2F0;color:#1C1C1A;'
				. 'font-family:system-ui,sans-serif;padding:24px}div{max-width:420px;border-top:3px solid #C4211F;'
				. 'background:#fff;padding:28px;line-height:1.6}h1{font-family:Georgia,serif;font-weight:400;'
				. 'font-size:26px;margin:0 0 10px}p{margin:0;color:#54544F;font-size:14px}</style></head><body>'
				. '<div><h1>Servicio no disponible</h1>'
				. '<p>No pudimos conectar con la base de datos. Vuelve a intentarlo en unos minutos.</p></div>'
				. '</body></html>');
		}
	}

	public static function getInstance(): PDO {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		$i = self::$instance;
		return $i->getPdo();
	}

	private function getPdo(): PDO {
		return $this->pdo;
	}

}
