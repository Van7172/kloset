<?php

namespace Develoweb\App\Model;

use PDO;

// Incluir las clases necesarias
require_once __DIR__ . '/Conexion.php';
require_once __DIR__ . '/Usuario.php';

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
    private $_video_youtube;
    private $_imagen_banner;
    private $_id_padre;
    private $_creador_contenido;

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
                $this->_video_youtube = $post['video_youtube_post'] ?? '';
                $this->_imagen_banner = $post['imagen_banner_post'] ?? '';
                $this->_id_padre = $post['id_padre'] ?? null;
                $this->_creador_contenido = $post['creador_contenido'] ?? '';
            }
        }
    }

    /**
     * Guarda un post desde un array de datos JSON
     * @param array $datosPost Array con los datos del post
     * @return bool True si se guardó correctamente, False en caso contrario
     */
    public function guardarDesdeJson($datosPost)
    {
        // Validar que los datos requeridos estén presentes
        if (!isset($datosPost['titulo_post']) || !isset($datosPost['contenido_html_post'])) {
            return false;
        }

        // Limpiar URLs de imágenes que vienen de CURL para evitar concatenaciones malformadas
        $imagen_destacada = $datosPost['imagen_destacada_post'] ?? '';
        $imagen_banner = $datosPost['imagen_banner_post'] ?? '';
        
        // Si las imágenes vienen como URLs completas de CURL, limpiarlas para que solo quede el nombre del archivo
        if (!empty($imagen_destacada) && (strpos($imagen_destacada, 'http://') === 0 || strpos($imagen_destacada, 'https://') === 0)) {
            $imagen_destacada = basename($imagen_destacada);
        }
        
        if (!empty($imagen_banner) && (strpos($imagen_banner, 'http://') === 0 || strpos($imagen_banner, 'https://') === 0)) {
            $imagen_banner = basename($imagen_banner);
        }
        
        // Asignar los valores a las propiedades
        $this->_titulo = $datosPost['titulo_post'];
        $this->_slug = $datosPost['url_post'] ?? '';
        $this->_contenido = $datosPost['contenido_html_post'];
        $this->_extracto = $datosPost['extracto_post'] ?? '';
        $this->_imagen_destacada = $imagen_destacada;
        $this->_imagen_banner = $imagen_banner;
        $this->_video_youtube = $datosPost['video_youtube_post'] ?? '';
        $this->_estado = $datosPost['estado_post'] ?? 'borrador';
        $this->_meta_descripcion = $datosPost['meta_descripcion_post'] ?? '';
        $this->_meta_keywords = $datosPost['meta_keywords_post'] ?? '';
        $this->_vistas = $datosPost['vistas_post'] ?? 0;
        $this->_id_padre = $datosPost['id_padre'] ?? null;
        $this->_creador_contenido = $datosPost['creador_contenido'] ?? '';

        // Manejar fechas
        if (isset($datosPost['fecha_creacion_post'])) {
            $this->_fecha_creacion = $datosPost['fecha_creacion_post'];
        } else {
            $this->_fecha_creacion = date('Y-m-d H:i:s');
        }

        if (isset($datosPost['fecha_modificacion_post'])) {
            $this->_fecha_modificacion = $datosPost['fecha_modificacion_post'];
        } else {
            $this->_fecha_modificacion = date('Y-m-d H:i:s');
        }

        // Manejar usuario
        if (isset($datosPost['id_usuario'])) {
            $this->_usuario = new Usuario($datosPost['id_usuario']);
        } else {
            // Usuario por defecto (ID 1)
            $this->_usuario = new Usuario(1);
        }

        // Guardar en la base de datos
        $resultado = $this->guardar();
        
        // Si se guardó exitosamente y hay categorías, guardarlas también
        if ($resultado && isset($datosPost['categorias']) && is_array($datosPost['categorias'])) {
            $this->guardarCategorias($datosPost['categorias']);
        }
        
        return $resultado;
    }

    public function guardar()
    {
        $con = Conexion::getInstance();

        if ($this->_id > 0) {
            $sth = $con->prepare("UPDATE posts SET 
                titulo_post = :titulo,
                url_post = :slug,
                contenido_html_post = :contenido,
                video_youtube_post = :video_youtube,
                extracto_post = :extracto,
                imagen_destacada_post = :imagen,
                imagen_banner_post = :imagen_banner,
                fecha_creacion_post = :fecha_creacion,
                fecha_modificacion_post = :fecha_modificacion,
                id_usuario = :usuario,
                estado_post = :estado,
                meta_descripcion_post = :meta_desc,
                meta_keywords_post = :meta_keys,
                vistas_post = :vistas,
                id_padre = :id_padre,
                creador_contenido = :creador_contenido
                WHERE id_post = :id");

            $sth->bindParam(":id", $this->_id, PDO::PARAM_INT);
        } else {
            // Insertar nuevo
            $sth = $con->prepare("INSERT INTO posts (
                titulo_post, url_post, contenido_html_post, video_youtube_post, extracto_post,
                imagen_destacada_post, imagen_banner_post, fecha_creacion_post, fecha_modificacion_post,
                id_usuario, estado_post, meta_descripcion_post, meta_keywords_post, vistas_post,
                id_padre, creador_contenido)
                VALUES (
                :titulo, :slug, :contenido, :video_youtube, :extracto, :imagen, :imagen_banner,
                :fecha_creacion, :fecha_modificacion, :usuario, :estado, :meta_desc, :meta_keys,
                :vistas, :id_padre, :creador_contenido)");
        }

        $sth->bindParam(":titulo", $this->_titulo);
        $sth->bindParam(":slug", $this->_slug);
        $sth->bindParam(":contenido", $this->_contenido);
        $sth->bindParam(":video_youtube", $this->_video_youtube);
        $sth->bindParam(":extracto", $this->_extracto);
        $sth->bindParam(":imagen", $this->_imagen_destacada);
        $sth->bindParam(":imagen_banner", $this->_imagen_banner);
        $sth->bindParam(":fecha_creacion", $this->_fecha_creacion);
        $sth->bindParam(":fecha_modificacion", $this->_fecha_modificacion);
        // Obtener el ID del usuario antes de bindParam para evitar error de referencia
        $idUsuario = $this->_usuario->getId();
        $sth->bindParam(":usuario", $idUsuario);
        $sth->bindParam(":estado", $this->_estado);
        $sth->bindParam(":meta_desc", $this->_meta_descripcion);
        $sth->bindParam(":meta_keys", $this->_meta_keywords);
        $sth->bindParam(":vistas", $this->_vistas, PDO::PARAM_INT);
        // Manejar id_padre que puede ser null
        if ($this->_id_padre !== null) {
            $sth->bindParam(":id_padre", $this->_id_padre, PDO::PARAM_INT);
        } else {
            $sth->bindValue(":id_padre", null, PDO::PARAM_NULL);
        }
        $sth->bindParam(":creador_contenido", $this->_creador_contenido);

        $resultado = $sth->execute();
        
        // Si es un insert y fue exitoso, obtener el ID generado
        if (!$this->_id && $resultado) {
            $this->_id = $con->lastInsertId();
        }
        
        return $resultado;
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

    /**
     * Obtiene el video de YouTube
     */
    public function getVideoYoutube()
    {
        return $this->_video_youtube;
    }

    /**
     * Establece el video de YouTube
     */
    public function setVideoYoutube($valor)
    {
        $this->_video_youtube = $valor;
    }

    /**
     * Obtiene la imagen del banner
     */
    public function getImagenBanner()
    {
        return $this->_imagen_banner;
    }

    /**
     * Establece la imagen del banner
     */
    public function setImagenBanner($valor)
    {
        $this->_imagen_banner = $valor;
    }

    /**
     * Obtiene el ID del post padre
     */
    public function getIdPadre()
    {
        return $this->_id_padre;
    }

    /**
     * Establece el ID del post padre
     */
    public function setIdPadre($valor)
    {
        $this->_id_padre = $valor;
    }

    /**
     * Obtiene el creador del contenido
     */
    public function getCreadorContenido()
    {
        return $this->_creador_contenido;
    }

    /**
     * Establece el creador del contenido
     */
    public function setCreadorContenido($valor)
    {
        $this->_creador_contenido = $valor;
    }

    /**
     * Establece el extracto
     */
    public function setExtracto($valor)
    {
        $this->_extracto = $valor;
    }

    /**
     * Establece la imagen destacada
     */
    public function setImagenDestacada($valor)
    {
        $this->_imagen_destacada = $valor;
    }

    /**
     * Establece la fecha de creación
     */
    public function setFechaCreacion($valor)
    {
        $this->_fecha_creacion = $valor;
    }

    /**
     * Establece la fecha de modificación
     */
    public function setFechaModificacion($valor)
    {
        $this->_fecha_modificacion = $valor;
    }

    /**
     * Establece el usuario
     */
    public function setUsuario($usuario)
    {
        $this->_usuario = $usuario;
    }

    /**
     * Establece la categoría
     */
    public function setCategoria($categoria)
    {
        $this->_categoria = $categoria;
    }

    /**
     * Establece la meta descripción
     */
    public function setMetaDescripcion($valor)
    {
        $this->_meta_descripcion = $valor;
    }

    /**
     * Establece las meta keywords
     */
    public function setMetaKeywords($valor)
    {
        $this->_meta_keywords = $valor;
    }

    /**
     * Establece las vistas
     */
    public function setVistas($valor)
    {
        $this->_vistas = $valor;
    }

    /**
     * Guarda múltiples posts desde un array de datos JSON
     * @param array $postsArray Array con múltiples posts
     * @return array Array con los resultados de cada operación
     */
    public static function guardarMultiplesPosts($postsArray)
    {
        $resultados = [];
        
        foreach ($postsArray as $postData) {
            $post = new Post();
            $resultado = $post->guardarDesdeJson($postData);
            $resultados[] = [
                'id_post' => $post->getId(),
                'titulo' => $post->getTitulo(),
                'exitoso' => $resultado
            ];
        }
        
        return $resultados;
    }

    /**
     * Lee un archivo JSON y guarda todos los posts en la base de datos
     * @param string $rutaArchivo Ruta al archivo JSON
     * @return array Array con los resultados de la operación
     */
    public static function importarDesdeArchivoJson($rutaArchivo)
    {
        try {
            // Verificar que el archivo existe
            if (!file_exists($rutaArchivo)) {
                return [
                    'exitoso' => false,
                    'mensaje' => 'El archivo JSON no existe en la ruta especificada',
                    'posts_procesados' => 0
                ];
            }

            // Leer el contenido del archivo
            $contenido = file_get_contents($rutaArchivo);
            if ($contenido === false) {
                return [
                    'exitoso' => false,
                    'mensaje' => 'No se pudo leer el archivo JSON',
                    'posts_procesados' => 0
                ];
            }

            // Decodificar el JSON
            $datos = json_decode($contenido, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'exitoso' => false,
                    'mensaje' => 'Error al decodificar JSON: ' . json_last_error_msg(),
                    'posts_procesados' => 0
                ];
            }

            // Verificar que existe la clave 'posts'
            if (!isset($datos['posts']) || !is_array($datos['posts'])) {
                return [
                    'exitoso' => false,
                    'mensaje' => 'El archivo JSON no contiene un array de posts válido',
                    'posts_procesados' => 0
                ];
            }

            // Procesar cada post
            $resultados = [];
            $postsExitosos = 0;
            $postsFallidos = 0;

            foreach ($datos['posts'] as $postData) {
                try {
                    $post = new Post();
                    $resultado = $post->guardarDesdeJson($postData);
                    
                    if ($resultado) {
                        $postsExitosos++;
                        $resultados[] = [
                            'id_post' => $post->getId(),
                            'titulo' => $post->getTitulo(),
                            'exitoso' => true,
                            'mensaje' => 'Post guardado correctamente'
                        ];
                    } else {
                        $postsFallidos++;
                        $resultados[] = [
                            'titulo' => $postData['titulo_post'] ?? 'Sin título',
                            'exitoso' => false,
                            'mensaje' => 'Error al guardar el post'
                        ];
                    }
                } catch (Exception $e) {
                    $postsFallidos++;
                    $resultados[] = [
                        'titulo' => $postData['titulo_post'] ?? 'Sin título',
                        'exitoso' => false,
                        'mensaje' => 'Excepción: ' . $e->getMessage()
                    ];
                }
            }

            return [
                'exitoso' => true,
                'mensaje' => "Proceso completado. Posts exitosos: {$postsExitosos}, Posts fallidos: {$postsFallidos}",
                'posts_procesados' => count($datos['posts']),
                'posts_exitosos' => $postsExitosos,
                'posts_fallidos' => $postsFallidos,
                'detalle_resultados' => $resultados
            ];

        } catch (Exception $e) {
            return [
                'exitoso' => false,
                'mensaje' => 'Error general: ' . $e->getMessage(),
                'posts_procesados' => 0
            ];
        }
    }

    /**
     * Guarda las categorías de un post en la tabla de relación
     * @param int $idPost ID del post
     * @param array $categorias Array de categorías con id_categoria
     * @return bool True si se guardaron correctamente
     */
    public function guardarCategorias($categorias)
    {
        if (empty($categorias) || !is_array($categorias)) {
            return false;
        }

        $con = Conexion::getInstance();
        
        try {
                    // Primero eliminar categorías existentes
        $sth = $con->prepare("DELETE FROM posts_categorias WHERE id_post = :id_post");
        $sth->bindParam(":id_post", $this->_id, PDO::PARAM_INT);
        $sth->execute();

        // Insertar nuevas categorías
        $sth = $con->prepare("INSERT INTO posts_categorias (id_post, id_categoria) VALUES (:id_post, :id_categoria)");
            
            foreach ($categorias as $categoria) {
                if (isset($categoria['id_categoria'])) {
                    $sth->bindParam(":id_post", $this->_id, PDO::PARAM_INT);
                    $sth->bindParam(":id_categoria", $categoria['id_categoria'], PDO::PARAM_INT);
                    $sth->execute();
                }
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
