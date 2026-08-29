<?php

namespace Develoweb\App\Model;

use PDO;

class Post
{
    private $_id;
    private $_titulo;
    private $_slug;
    private $_contenido;
    private $_extracto;
    private $_imagen_destacada;
    private $_fecha_creacion;
    private $_fecha_modificacion;
    private $_usuario;
    private $_categoria;
    private $_estado;
    private $_meta_descripcion;
    private $_meta_keywords;
    private $_vistas;

    public function __construct($id = 0)
    {
        $this->_id = $id;
        $con = Conexion::getInstance();

        if ($this->_id > 0) {
            $sth = $con->prepare("SELECT * FROM posts WHERE id_post = :id");
            $sth->bindParam(":id", $this->_id, PDO::PARAM_INT);
            $sth->execute();

            if ($sth->rowCount() > 0) {
                $post = $sth->fetch();

                $this->_titulo = $post['titulo_post'];
                $this->_slug = $post['url_post'];
                $this->_contenido = $post['contenido_html_post'] ?? '';
                $this->_extracto = $post['extracto_post'];
                $this->_imagen_destacada = $post['imagen_destacada_post'];
                $this->_fecha_creacion = $post['fecha_creacion_post'];
                $this->_fecha_modificacion = $post['fecha_modificacion_post'];
                $this->_usuario = new Usuario($post['id_usuario']);
                $this->_estado = $post['estado_post'];
                $this->_meta_descripcion = $post['meta_descripcion_post'];
                $this->_meta_keywords = $post['meta_keywords_post'];
                $this->_vistas = $post['vistas_post'];
            }
        }
    }

    public function guardar()
    {
        $con = Conexion::getInstance();

        if ($this->_id > 0) {
            $sth = $con->prepare("UPDATE posts SET 
                titulo_post = :titulo,
                url_post = :slug,
                contenido_html_post = :contenido,
                extracto_post = :extracto,
                imagen_destacada_post = :imagen,
                id_usuario = :usuario,
                id_categoria = :categoria,
                estado_post = :estado,
                meta_descripcion_post = :meta_desc,
                meta_keywords_post = :meta_keys,
                vistas_post = :vistas
                WHERE id_post = :id");

            $sth->bindParam(":id", $this->_id, PDO::PARAM_INT);
        } else {
            // Insertar nuevo
            $sth = $con->prepare("INSERT INTO posts (
                titulo_post, url_post, contenido_html_post, extracto_post,
                imagen_destacada_post, id_usuario, id_categoria, estado_post,
                meta_descripcion_post, meta_keywords_post, vistas_post)
                VALUES (
                :titulo, :slug, :contenido, :extracto, :imagen, :usuario,
                :categoria, :estado, :meta_desc, :meta_keys, :vistas)");
        }

        $sth->bindParam(":titulo", $this->_titulo);
        $sth->bindParam(":slug", $this->_slug);
        $sth->bindParam(":contenido", $this->_contenido);
        $sth->bindParam(":extracto", $this->_extracto);
        $sth->bindParam(":imagen", $this->_imagen_destacada);
        $sth->bindParam(":usuario", $this->_usuario->getId());
        // $sth->bindParam(":categoria", $this->_categoria->getId());
        $sth->bindParam(":estado", $this->_estado);
        $sth->bindParam(":meta_desc", $this->_meta_descripcion);
        $sth->bindParam(":meta_keys", $this->_meta_keywords);
        $sth->bindParam(":vistas", $this->_vistas);

        return $sth->execute();
    }

    public function getPostsPorCategoria($id_categoria, $limite = 10)
    {
        $con = Conexion::getInstance();
        $sth = $con->prepare("SELECT id_post FROM posts WHERE id_categoria = :categoria 
                             ORDER BY fecha_creacion_post DESC LIMIT :limite");
        $sth->bindParam(":categoria", $id_categoria, PDO::PARAM_INT);
        $sth->bindParam(":limite", $limite, PDO::PARAM_INT);
        $sth->execute();

        $posts = [];
        while ($row = $sth->fetch()) {
            $posts[] = new Post($row['id_post']);
        }
        return $posts;
    }

    public function getId()
    {
        return $this->_id;
    }

    public function getTitulo()
    {
        return $this->_titulo;
    }

    public function setTitulo($valor)
    {
        $this->_titulo = $valor;
    }

    public function getSlug()
    {
        return $this->_slug;
    }

    public function setSlug($valor)
    {
        $this->_slug = $valor;
    }

    public function getContenido()
    {
        return $this->_contenido;
    }

    public function setContenido($valor)
    {
        $this->_contenido = $valor;
    }

    public function getExtracto()
    {
        return $this->_extracto;
    }

    public function getImagenDestacada()
    {
        return $this->_imagen_destacada;
    }

    public function getFechaCreacion()
    {
        return $this->_fecha_creacion;
    }

    public function getFechaModificacion()
    {
        return $this->_fecha_modificacion;
    }

    public function getUsuario()
    {
        return $this->_usuario;
    }

    public function getCategoria()
    {
        return $this->_categoria;
    }

    public function getEstado()
    {
        return $this->_estado;
    }

    public function setEstado($valor)
    {
        $this->_estado = $valor;
    }

    public function incrementarVistas()
    {
        $this->_vistas++;
        $con = Conexion::getInstance();
        $sth = $con->prepare("UPDATE posts SET vistas_post = vistas_post + 1 WHERE id_post = :id");
        $sth->bindParam(":id", $this->_id, PDO::PARAM_INT);
        return $sth->execute();
    }

    public function __toString()
    {
        return $this->_titulo;
    }
}
