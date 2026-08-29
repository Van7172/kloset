<?php

namespace Develoweb\App\Model;

use PDO;

class Secciones
{

	public function getSeccionesPorModulo ($id_modulo) {
		$con = Conexion::getInstance();
        $sth = $con->prepare("SELECT id_seccion, nombre_seccion, url_seccion, icono_seccion FROM secciones WHERE id_modulo = :id_modulo AND estado_seccion = 1");
		$sth->bindParam(':id_modulo', $id_modulo, PDO::PARAM_INT);
        $sth->execute();
        $sections = $sth->fetchAll();
		return $sections;
	}

	public function deleteSecciones ($nombres_secciones) {
		foreach ($nombres_secciones as $nombre_seccion) {
			$con = Conexion::getInstance();
            $stmt = $con->prepare("DELETE FROM secciones WHERE nombre_seccion = :nombre");
            $stmt->bindParam(":nombre", $nombre_seccion['nombre_seccion'], PDO::PARAM_STR);
			if (! $stmt->execute()) continue;
		}
	}

	public function addSecciones ($datas) {
		foreach ($datas as $data) {
			if ($this->existeSeccion($data) == -1) {
				$con = Conexion::getInstance();
				$stmt = $con->prepare("INSERT INTO secciones VALUES(NULL, :id_modulo, :nombre, :url, '', 0)");
				$stmt->bindParam(":id_modulo", $data['id_modulo'], PDO::PARAM_INT);
				$stmt->bindParam(":nombre", $data['nombre_seccion'], PDO::PARAM_STR);
				$stmt->bindParam(":url", $data['url_seccion'], PDO::PARAM_STR);
				if (! $stmt->execute()) continue;
			}
		}
	}

	public function existeSeccion ($data) {
		$con = Conexion::getInstance();

        $sth = $con->prepare("SELECT id_seccion FROM secciones WHERE nombre_seccion = :nombre");
		$sth->bindParam(":nombre", $data['nombre_seccion'], PDO::PARAM_STR);
		$sth->execute();

		if ($sth->rowCount() === 0) return -1;
		$section = $sth->fetch();

		$stmt = $con->prepare("UPDATE secciones SET nombre_seccion = :nombre, url_seccion = :url WHERE id_seccion = :id");
		$stmt->bindParam(":id", $section['id_seccion'], PDO::PARAM_INT);
		$stmt->bindParam(":url", $data['url_seccion'], PDO::PARAM_STR);
		$stmt->bindParam(":nombre", $data['nombre_seccion'], PDO::PARAM_STR);
		$sth->execute();
	}

}