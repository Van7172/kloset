<?php

namespace Develoweb\App\Model;

use PDO;

class Categoria
{
    private $_id;
    private $_nombre;
    private $_slug;
    private $_descripcion;
    private $_categoriaPadre;
    private $_metaTitle;
    private $_metaDescription;
    private $_icono;

    public function __construct($id = 0)
    {
        if ($id > 0) {
            $con = Conexion::getInstance();
            $sth = $con->prepare("SELECT * FROM categorias WHERE id_categoria = :id");
            $sth->bindParam(":id", $id, PDO::PARAM_INT);
            $sth->execute();

            if ($sth->rowCount() > 0) {
                $categoria = $sth->fetch();
                $this->_id = $categoria['id_categoria'];
                $this->_nombre = $categoria['nombre_categoria'];
                $this->_slug = $categoria['slug_categoria'];
                $this->_descripcion = $categoria['descripcion_categoria'];
                $this->_categoriaPadre = $categoria['categoria_padre_id'];
                $this->_metaTitle = $categoria['meta_title'];
                $this->_metaDescription = $categoria['meta_description'];
                $this->_icono = $categoria['icono_categoria'];
            }
        }
    }

    public function __get($atributo)
    {
        return $this->$atributo;
    }

}
