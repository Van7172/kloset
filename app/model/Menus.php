<?php

namespace Develoweb\App\Model;

use Exception;
use PDO;

class Menus
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
            "SELECT * FROM `menus` ORDER BY `menus`.`orden` ASC"
        );

        $sth->execute();
        $menusConCategorias = $sth->fetchAll(PDO::FETCH_ASSOC);
        return $menusConCategorias;
    }

    public static function getActivos()
    {
        try {
            $con = Conexion::getInstance();
            $sth = $con->prepare(
                "SELECT * FROM `menus` WHERE `estado_menu` = 1 ORDER BY `orden` ASC"
            );
    
            if ($sth->execute()) {
                return $sth->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Log del error
                error_log('Error ejecutando el query de Menus::getActivos()');
                return [];
            }
        } catch (\Exception $e) {
            error_log('Excepción en Menus::getActivos(): ' . $e->getMessage());
            return [];
        }
    }
    

    public static function getPaginasForMenu()
    {
        $con = Conexion::getInstance();
        $sth = $con->prepare(
            "SELECT * FROM menus m
            LEFT JOIN paginas p ON p.url_pagina = m.url_menu 
            WHERE `estado_menu` = 1
            ORDER BY m.orden ASC"
        );
    
        $sth->execute();
        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function save()
    {

        if (empty($_POST['title_menu'])) {
            return ['error' => 'Debe agregar un Titulo'];
        }

        $titulo = $_POST['title_menu'];
        $url = $_POST['update_url_menu'];
        $estado = '1';
        $icon = $_POST['iconSelect'];

        $con = Conexion::getInstance();

        $sth = $con->prepare("SELECT MAX(orden) as max_orden FROM menus");
        $sth->execute();
        $maxOrden = $sth->fetch(PDO::FETCH_ASSOC)['max_orden'];

        $nuevoOrden = ($maxOrden !== null) ? $maxOrden + 1 : 1;

        $sth = $con->prepare("INSERT INTO menus(nombre_menu, url_menu, ico_menu, estado_menu, orden) 
            VALUES (:titulo, :url, :img, :estado, :orden)");
        $sth->bindParam(':titulo', $titulo);
        $sth->bindParam(':url', $url);
        $sth->bindParam(':img', $icon);
        $sth->bindParam(':estado', $estado);
        $sth->bindParam(':orden', $nuevoOrden);

        return $sth->execute();
    }

    public static function update()
    {
        $id_menu = $_POST['id_menu'];
        $titulo = $_POST['update_title_menu'];
        $icon = $_POST['iconSelect2'];
        $url = $_POST['update_url_menu'];

        $con = Conexion::getInstance();

        if ($icon) {
            $sth = $con->prepare("UPDATE menus SET nombre_menu = :titulo, ico_menu = :imagen, url_menu = :url WHERE id_menu = :id_menu");
            $sth->bindParam(':imagen', $icon);
        } else {
            $sth = $con->prepare("UPDATE menus SET nombre_menu = :titulo, url_menu = :url WHERE id_menu = :id_menu");
        }

        $sth->bindParam(':titulo', $titulo);
        $sth->bindParam(':id_menu', $id_menu);
        $sth->bindParam(':url', $url);

        return $sth->execute();
    }

    public static function changeStatus()
    {

        $con = Conexion::getInstance();
        $estado = '0';

        if ($_POST['action'] == 'enable') {
            $estado = '1';
        }

        $sth = $con->prepare("UPDATE menus SET estado_menu = :estado WHERE id_menu = :menu");
        $sth->bindParam(':estado', $estado);
        $sth->bindParam(':menu', $_POST['id_menu']);
        return $sth->execute();
    }

    public static function ChangeOrden()
    {

        $data = json_decode($_POST['registro'], true);

        /*      if(empty($data)){
           $register = self::registerCategoryInMenu($_POST['id_categoria']);
            return ['success' => $register];
        } */

        $con = Conexion::getInstance();

        try {
            foreach ($data as $item) {
                $id_menu = intval($item['id_menu']);
                $orden = intval($item['orden']);

                $stmt = $con->prepare('UPDATE menus SET orden = ? WHERE id_menu = ?');
                $stmt->bindParam(1, $orden, PDO::PARAM_INT);
                $stmt->bindParam(2, $id_menu, PDO::PARAM_INT);

                $stmt->execute();
            }

            return ['success' => true];
        } catch (Exception $err) {
            return ['error' => $err->getMessage()];
        }
    }

    public static function registerCategoryInMenu()
    {
        try {
            $con = Conexion::getInstance();

            $titulo = $_POST['nombre'];
            $url = $_POST['url'];

            $sth = $con->prepare("SELECT MAX(orden) as max_orden FROM menus");
            if (!$sth->execute()) {
                throw new Exception('Error al obtener el orden máximo');
            }

            $maxOrden = $sth->fetch(PDO::FETCH_ASSOC)['max_orden'];
            $nuevoOrden = ($maxOrden !== null) ? $maxOrden + 1 : 1;
            $estado = '1';
            $ico_menu = 'icon ni ni-menu'; // Icono por defecto, puedes cambiarlo

            $sth = $con->prepare("INSERT INTO menus(nombre_menu, url_menu, estado_menu, orden, ico_menu) 
                VALUES (:titulo, :url, :estado, :orden, :ico_menu)");
            $sth->bindParam(':titulo', $titulo);
            $sth->bindParam(':url', $url);
            $sth->bindParam(':estado', $estado);
            $sth->bindParam(':orden', $nuevoOrden);
            $sth->bindParam(':ico_menu', $ico_menu);

            if (!$sth->execute()) {
                throw new Exception('Error al insertar el nuevo menú');
            }

            $id_menu = $con->lastInsertId();

            $response = [
                'success' => true,
                'menu' => [
                    'id_menu' => $id_menu,
                    'nombre_menu' => $titulo,
                    'url_menu' => $url,
                    'ico_menu' => $ico_menu,
                    'orden' => $nuevoOrden,
                    'estado_menu' => $estado
                ]
            ];

            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        } catch (Exception $err) {
            $response = [
                'success' => false,
                'error' => $err->getMessage()
            ];

            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode($response);
            exit;
        }
    }
}
