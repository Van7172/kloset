<?php

namespace Develoweb\App\Model;

use Exception;
use PDO;

class Banners
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
			"SELECT * FROM `banners` ORDER BY `banners`.`orden` ASC"
		);
		$sth->execute();
		$categorias = $sth->fetchAll();
		return $categorias;
	}

	public static function create(){
	
		if(empty($_FILES['file_imagen_banner']['name'])){

			return['error'=>'Debe agregar una imagen'];
		}

		$titulo = $_POST['title_banner'];
        $descripcion = $_POST['description_banner'];
		$url = $_POST['url_banner'];

		$ruta = PUBLIC_ROOT_HOST . 'imgs/banners';

        $banner = uploadImgs($_FILES['file_imagen_banner']['name'], $_FILES['file_imagen_banner']['tmp_name'], $ruta);

        $con = Conexion::getInstance();
		
		$sth = $con->prepare("SELECT MAX(orden) as max_orden FROM banners");
		$sth->execute();
		$maxOrden = $sth->fetch(PDO::FETCH_ASSOC)['max_orden'];

		$nuevoOrden = ($maxOrden !== null) ? $maxOrden + 1 : 1;

        $sth = $con->prepare("INSERT INTO banners(titulo_banner, descripcion_banner, enlace_banner, img_banner, orden) 
		VALUES (:titulo, :descripcion, :url, :img, :orden)");
        $sth->bindParam(':titulo', $titulo);
        $sth->bindParam(':descripcion', $descripcion);
        $sth->bindParam(':url', $url);
        $sth->bindParam(':img', $banner);
		$sth->bindParam(':orden', $nuevoOrden);

        return $sth->execute();
	}


	public static function update() {
	
		$id_banner = $_POST['id_banner'];
		
		$titulo = $_POST['title_banner'];
        $descripcion = $_POST['description_banner'];
		$url = $_POST['url_banner'];
	
		$ruta = PUBLIC_ROOT_HOST . 'imgs/banners';
	
		$banner = '';
		if (!empty($_FILES['file_imagen_banner']['name'])) {
			$banner = uploadImgs($_FILES['file_imagen_banner']['name'], $_FILES['file_imagen_banner']['tmp_name'], $ruta);
		}
		
		$con = Conexion::getInstance();

		if ($banner) {
			$sth = $con->prepare("UPDATE banners SET titulo_banner = :titulo, descripcion_banner = :descripcion, enlace_banner = :url, img_banner = :imagen WHERE id_banner = :id_banner");
			$sth->bindParam(':imagen', $banner);

		} else {
			$sth = $con->prepare("UPDATE banners SET titulo_banner = :titulo, descripcion_banner = :descripcion, enlace_banner = :url WHERE id_banner = :id_banner");
		}
		
		$sth->bindParam(':titulo', $titulo);
		$sth->bindParam(':descripcion', $descripcion);
		$sth->bindParam(':url', $url);
		$sth->bindParam(':id_banner', $id_banner);
		
		return $sth->execute();
	}

	public static function delete(){

		$id_banner = $_POST['id_banner'];

        $con = Conexion::getInstance();
        $sth = $con->prepare("DELETE FROM banners WHERE id_banner = :id");
        $sth->bindParam(':id', $id_banner);
        return $sth->execute();
	}

	public static function ChangeOrden() {
		$con = Conexion::getInstance();
		$data = json_decode($_POST['registro'], true);
	
		try {
			foreach ($data as $item) {
				$id_banner = intval($item['id_banner']);
				$orden = intval($item['orden']);
	
				$stmt = $con->prepare('UPDATE banners SET orden = ? WHERE id_banner = ?');
				$stmt->bindParam(1, $orden, PDO::PARAM_INT);
				$stmt->bindParam(2, $id_banner, PDO::PARAM_INT);
	
				$stmt->execute();
			}
	
			return ['success' => true];
	
		} catch (Exception $err) {
			return ['error' => $err->getMessage()];
		}
	}
	
}
