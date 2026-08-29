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
			die("Error al conectarse al servidor: " . $e->getMessage());
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
