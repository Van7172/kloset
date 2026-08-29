<?php

namespace Develoweb\App\Model;

use PDO;

class Destinos
{
	private $_msgbox;

	public function __construct($msg = null)
	{
		$this->_msgbox = $msg;
	}

	public static function get()
	{
		$con = Conexion::getInstance();
		$sth = $con->prepare(
			"SELECT * FROM destinos ORDER BY id_destino DESC"
		);
		$sth->execute();
		$destinos = $sth->fetchAll();
		return $destinos;
	}

	public static function getByUrlDestino($url_destino)
	{
		$con = Conexion::getInstance();
		$stmt = $con->prepare("SELECT * FROM destinos WHERE url_destino = :url_destino");
		$stmt->bindParam(':url_destino', $url_destino);
		$stmt->execute();
		return $stmt->fetch(PDO::FETCH_ASSOC);
	}

	private static function uploadFile(string $fileName, string $fileTmpPath, string $uploadDir): string {

		$fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
		$uniqueName = uniqid() . '.' . $fileExtension;
		$destPath = rtrim($uploadDir, '/') . '/' . $uniqueName;
	
		// Ruta relativa para la base de datos
		$relativePath = 'images/destinos/' . $uniqueName;
	
		if (!move_uploaded_file($fileTmpPath, $destPath)) {
			echo "Error al mover el archivo de imagen";
		}
	
		return $relativePath;
	}
	
	public static function store() {
	
		$urlDestination = url_friend($_POST['names']);
	
		$imagePath = null;
		if (isset($_FILES['fileimgs']) && $_FILES['fileimgs']['error'] === UPLOAD_ERR_OK) {
			$imagePath = static::uploadFile($_FILES['fileimgs']['name'], $_FILES['fileimgs']['tmp_name'], ADMIN_IMGS_WEB_DESTINOS);
		}
	
		$con = Conexion::getInstance();
		$stmt = $con->prepare("INSERT INTO destinos (nombre_destino, url_destino, descripcion_destino, imagen_destino) VALUES (:names, :url, :descriptions, :image_path)");
		$stmt->bindParam(":names", $_POST['names'], PDO::PARAM_STR);
		$stmt->bindParam(":url", $urlDestination, PDO::PARAM_STR);
		$stmt->bindParam(":descriptions", $_POST['descriptions'], PDO::PARAM_STR);
		$stmt->bindParam(":image_path", $imagePath, PDO::PARAM_STR);
	
		$result = $stmt->execute();
	
		if (!$result) {
			return ['errors' => 'Ocurrió un error al guardar los cambios'];
		}
	
		return ['success' => true, 'message' => 'Destino guardado correctamente'];
	}

	public static function update() 
	{
		$imagePath = null;
		$atributeImg = '';

		if (isset($_FILES['fileimgs']) && $_FILES['fileimgs']['error'] === UPLOAD_ERR_OK) {
			$imagePath = static::uploadFile($_FILES['fileimgs']['name'], $_FILES['fileimgs']['tmp_name'], ADMIN_IMGS_WEB_DESTINOS);
			$atributeImg = ', imagen_destino = :image_path'; // Agregar parte de la consulta solo si hay imagen
		}

		$con = Conexion::getInstance();
		$sql = "UPDATE destinos SET nombre_destino = :names, descripcion_destino = :descriptions $atributeImg WHERE id_destino = :id";
		$stmt = $con->prepare($sql);

		$stmt->bindParam(":names", $_POST['names'], PDO::PARAM_STR);
		$stmt->bindParam(":descriptions", $_POST['descriptions'], PDO::PARAM_STR);
		$stmt->bindParam(":id", $_POST['id'], PDO::PARAM_INT);

		if ($imagePath !== null) {
			$stmt->bindParam(":image_path", $imagePath, PDO::PARAM_STR);
		}

		$result = $stmt->execute();

		if (!$result) {
			return ['errors' => 'Ocurrió un error al guardar los cambios'];
		}

		return ['success' => true, 'message' => 'Destino actualizado correctamente'];
	}

	public static function disableDestination() {
		$con = Conexion::getInstance();
		$sql = "UPDATE destinos SET flag_destino = :flag WHERE id_destino = :id";
		$stmt = $con->prepare($sql);

		if($_POST['flag']== 1){
			$flag = '0';
		}else{
			$flag = '1';
		}

		$id = $_POST['id'];
	
		$stmt->bindParam(":flag", $flag, PDO::PARAM_STR);
		$stmt->bindParam(":id", $id, PDO::PARAM_INT);
	
		$result = $stmt->execute();
	
		if (!$result) {
			return ['errors' => 'Ocurrió un error al deshabilitar el destino'];
		}
	
		return ['success' => true, 'message' => 'Destino actualizado correctamente'];
	}
	


}
