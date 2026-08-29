<?php

namespace Develoweb\App\Model;

use Exception;
use PDO;

class Medios {

    private $_msgbox;

    public function __construct($msg = null)
	{
		$this->_msgbox = $msg;
	}

    public static function get() {
        $con = Conexion::getInstance();
        $sth = $con->prepare("SELECT * FROM medios");
        $sth->execute();
        $medios = $sth->fetchAll(PDO::FETCH_ASSOC);
    
        return ["data" => $medios];
    }

    public static function uploadMedios($img = null) {

        $ruta = PUBLIC_ROOT_HOST . 'imgs/medios';

        $fileData = $img ?? $_FILES['file'];

        if (empty($fileData) || $fileData['error'] !== 0) {
            return false;
        }

        $nombre = $fileData['name'];
        $tmp_name = $fileData['tmp_name'];
        $size = formatBytes($fileData['size']);
        $url = url_friend($nombre);

        $medio = uploadImgs($nombre, $tmp_name, $ruta);

        if (!$medio) return false;

        $dataFile = getimagesize(IMGS . 'medios/' . $medio);
        $dimension = $dataFile[0] . ' x ' . $dataFile[1];
        $fecha = date('Y-m-d');

        $con = Conexion::getInstance();
        $sth = $con->prepare("INSERT INTO medios(url_medio, nombre_medio, imagen_medio, peso_medio, dimension_medio, fecha_medio) 
            VALUES (:url, :nombre, :imagen, :peso, :dimensiones, :fecha)");

        $sth->bindParam(':url', $url);
        $sth->bindParam(':nombre', $nombre);
        $sth->bindParam(':imagen', $medio);
        $sth->bindParam(':peso', $size);
        $sth->bindParam(':dimensiones', $dimension);
        $sth->bindParam(':fecha', $fecha);

        // Devolver solo el nombre del medio, útil para luego guardar en el post
        if ($sth->execute()) {
            return $medio;
        }

        return false;
    }


    public static function addSaveMediosbyPostAndPages($img){
       
    }

    public static function update() {
        if (!isset($_POST['id_medio']) || !isset($_POST['title_medio_update'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID y título son requeridos.']);
            exit;
        }
    
        $id = $_POST['id_medio'];
        $titulo = $_POST['title_medio_update'];
        $caption = $_POST['caption_medio_update'];
    
        $con = Conexion::getInstance();
    
        $sth = $con->prepare("UPDATE medios SET nombre_medio = :titulo, caption_medio = :caption WHERE id_medio = :id");
        $sth->bindParam(':titulo', $titulo);
        $sth->bindParam(':caption', $caption);
        $sth->bindParam(':id', $id, PDO::PARAM_INT);
    
        if ($sth->execute()) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo actualizar el medio.']);
        }
    
        exit;
    }

    public static function delete()
    {
        $id_pagina = $_POST['id_medio'];
        $con = Conexion::getInstance();
        $sth = $con->prepare("DELETE FROM medios WHERE id_medio = :id");
        $sth->bindParam(':id', $id_pagina);
        return $sth->execute();
    }
    
    
}