<?php

namespace Develoweb\App\Model;

use PDO;

class Widgets
{
    private $_msgbox;

    public function __construct($msg = null)
    {
        $this->_msgbox = $msg;
    }

    public static function get()
    {
        $con = Conexion::getInstance();
        $sth = $con->prepare("SELECT * FROM widgets");
        $sth->execute();

        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function save(){

        $estado = 1;

        if(empty($_POST['widget_title'])){
            return['error'=>'Debe agregar un título'];
 
        }

        $con = Conexion::getInstance();
        $sth = $con->prepare("INSERT INTO widgets (nombre_widget, codigo_widget, estado_widget) VALUES (:nombre, :codigo, :estado)");
        $sth->bindParam(':nombre', $_POST['widget_title']);
        $sth->bindParam(':codigo', $_POST['code_widget']);
        $sth->bindParam(':estado', $estado);
        return $sth->execute();
        
    }

    public static function update() {

        if(empty($_POST['widget_title'])){
            return['error'=>'Debe agregar un título'];
 
        }

        $con = Conexion::getInstance();
		$sth = $con->prepare("UPDATE widgets SET nombre_widget = :nombre, codigo_widget = :codigo WHERE id_widget = :id_widget");
		$sth->bindParam(':id_widget', $_POST['id_widget']);
		$sth->bindParam(':nombre', $_POST['widget_title']);
        $sth->bindParam(':codigo', $_POST['code_widget']);

		return $sth->execute();
    }

    public static function delete() {

        $id = $_POST['id_widget'];

        $con = Conexion::getInstance();
        $sth = $con->prepare("DELETE FROM widgets WHERE id_widget = :id");
        $sth->bindParam(':id', $id);
        return $sth->execute();
    }

}