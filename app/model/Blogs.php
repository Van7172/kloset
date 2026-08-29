<?php

namespace Develoweb\App\Model;

use PDO;
use Exception;

class Blogs
{
    private $_msgbox;

    public function __construct($msg = null)
    {
        $this->_msgbox = $msg;
    }

    public static function get($pagina = 1, $porPagina = 10000)
    {
        $con = Conexion::getInstance();
        $inicio = ($pagina - 1) * $porPagina;

        $sth = $con->prepare("SELECT p.*, u.nombres_usuario
            FROM posts p
            LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario 
            ORDER BY p.fecha_creacion_post DESC 
            LIMIT :inicio, :porPagina");

        $sth->bindParam(':inicio', $inicio, PDO::PARAM_INT);
        $sth->bindParam(':porPagina', $porPagina, PDO::PARAM_INT);
        $sth->execute();

        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getByFront($pagina = 1, $porPagina = 100)
    {
        $con = Conexion::getInstance();
        $inicio = ($pagina - 1) * $porPagina;

        $sth = $con->prepare("SELECT p.*, 
            u.nombres_usuario, 
            GROUP_CONCAT(DISTINCT c.nombre_categoria ORDER BY c.nombre_categoria SEPARATOR ', ') AS nombre_categoria,
            GROUP_CONCAT(DISTINCT c.slug_categoria ORDER BY c.slug_categoria SEPARATOR ', ') AS slug_categoria
        FROM posts p
        LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario           
        LEFT JOIN posts_categorias pc ON p.id_post = pc.id_post
        LEFT JOIN categorias c ON pc.id_categoria = c.id_categoria
        WHERE p.estado_post = 'publicado'
        GROUP BY p.id_post, u.nombres_usuario
        ORDER BY p.fecha_creacion_post DESC     
        LIMIT :inicio, :porPagina");

        $sth->bindParam(':inicio', $inicio, PDO::PARAM_INT);
        $sth->bindParam(':porPagina', $porPagina, PDO::PARAM_INT);
        $sth->execute();

        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Convierte URLs de video en embeds responsivos
     * @param string $content Contenido HTML
     * @return string Contenido HTML con videos embebidos
     */
    public static function convertVideoUrlsToEmbeds($content)
    {
        if (empty($content)) {
            return $content;
        }

        // Patrones para detectar URLs de YouTube
        $youtubePatterns = [
            '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/v\/)([a-zA-Z0-9_-]+)/',
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?.*?v=([a-zA-Z0-9_-]+)/',
        ];

        foreach ($youtubePatterns as $pattern) {
            $content = preg_replace_callback($pattern, function($matches) {
                $videoCode = $matches[1];
                
                // Generar el embed responsivo
                return '<div class="video-container mb-4">
                    <iframe 
                        width="100%" 
                        height="400" 
                        src="https://www.youtube.com/embed/' . $videoCode . '?rel=0&showinfo=0&modestbranding=1&controls=1" 
                        frameborder="0" 
                        allowfullscreen>
                    </iframe>
                </div>';
            }, $content);
        }

        // Patrones para detectar URLs de Vimeo
        $vimeoPattern = '/(?:https?:\/\/)?(?:www\.)?vimeo\.com\/([0-9]+)/';
        $content = preg_replace_callback($vimeoPattern, function($matches) {
            $videoCode = $matches[1];
            
            return '<div class="video-container mb-4">
                <iframe 
                    width="100%" 
                    height="400" 
                    src="https://player.vimeo.com/video/' . $videoCode . '?title=0&byline=0&portrait=0" 
                    frameborder="0" 
                    allowfullscreen>
                </iframe>
            </div>';
        }, $content);

        return $content;
    }
 
    public static function renderPostContent($json)
    {
        $data = json_decode($json, true);
        $html = '';
    
        if (isset($data['blocks'])) {
            foreach ($data['blocks'] as $block) {
                switch ($block['type']) {
                    case 'header':
                        if (isset($block['data']['level']) && isset($block['data']['text'])) {
                            $level = intval($block['data']['level']);
                            $text = $block['data']['text'];
                            $html .= "<h{$level}>{$text}</h{$level}>";
                        }
                        break;
    
                    case 'paragraph':
                        if (isset($block['data']['text'])) {
                            $text = $block['data']['text'];
                            $html .= "<p>{$text}</p>";
                        }
                        break;
    
                    case 'image':
                        // Verificar que existan todos los campos necesarios
                        if (isset($block['data']['file']['url']) && !empty($block['data']['file']['url'])) {
                            $imageUrl = $block['data']['file']['url'];
                            $caption = isset($block['data']['caption']) ? $block['data']['caption'] : '';
        
                            $html .= "<div class=\"post-image\">";
                            $html .= "<img src=\"{$imageUrl}\" alt=\"" . htmlspecialchars($caption, ENT_QUOTES) . "\" style=\"max-width:100%;height:auto;\">";
                            if (!empty($caption)) {
                                $html .= "<p class=\"caption\"><em>{$caption}</em></p>";
                            }
                            $html .= "</div>";
                        } else {
                            // Si no hay URL de imagen, mostrar un mensaje o saltar
                            $html .= "<!-- Imagen no disponible -->";
                        }
                        break;
    
                    case 'list':
                        if (isset($block['data']['items']) && is_array($block['data']['items'])) {
                            $listType = isset($block['data']['style']) && $block['data']['style'] == 'ordered' ? 'ol' : 'ul';
                            $html .= "<{$listType}>";
                            
                            // Procesa los items de la lista, incluidos los anidados
                            $html .= self::renderListItems($block['data']['items']);
                            
                            $html .= "</{$listType}>";
                        }
                        break;
                        
                    case 'table':
                        $html .= "<table class=\"post-table\" border=\"1\" cellpadding=\"5\" cellspacing=\"0\" style=\"width:100%; border-collapse: collapse;\">";
                        
                        // Si la tabla tiene encabezados
                        $withHeadings = isset($block['data']['withHeadings']) ? $block['data']['withHeadings'] : false;
                        
                        if (isset($block['data']['content']) && is_array($block['data']['content'])) {
                            foreach ($block['data']['content'] as $rowIndex => $row) {
                                // Si es la primera fila y tiene encabezados
                                if ($rowIndex === 0 && $withHeadings) {
                                    $html .= "<thead><tr>";
                                    foreach ($row as $cell) {
                                        $html .= "<th style=\"background-color:#f2f2f2; font-weight:bold; border:1px solid #ddd; padding:8px; text-align:left;\">{$cell}</th>";
                                    }
                                    $html .= "</tr></thead><tbody>";
                                } else {
                                    // Si es la primera fila sin encabezados, abrimos el tbody
                                    if ($rowIndex === 0 && !$withHeadings) {
                                        $html .= "</tbody>";
                                    }
                                    
                                    $html .= "<tr>";
                                    foreach ($row as $cell) {
                                        $html .= "<td style=\"border:1px solid #ddd; padding:8px;\">{$cell}</td>";
                                    }
                                    $html .= "</tr>";
                                }
                            }
                            $html .= "</tbody>";
                        }
                        
                        $html .= "</table>";
                        break;
    
                    case 'quote':
                        if (isset($block['data']['text'])) {
                            $text = $block['data']['text'];
                            $caption = isset($block['data']['caption']) ? $block['data']['caption'] : '';
                            $alignment = isset($block['data']['alignment']) ? $block['data']['alignment'] : 'left';
                            
                            $html .= "<blockquote class=\"align-{$alignment}\">";
                            $html .= "<p>{$text}</p>";
                            if (!empty($caption)) {
                                $html .= "<footer>{$caption}</footer>";
                            }
                            $html .= "</blockquote>";
                        }
                        break;
    
                    default:
                        // Para tipos de bloques no soportados explícitamente
                        $html .= "<!-- Tipo de bloque no soportado: {$block['type']} -->";
                        break;
                }
            }
        }
    
        // Convertir URLs de video en embeds responsivos
        $html = self::convertVideoUrlsToEmbeds($html);
        
        return $html;
    }
    
    /**
     * Convierte contenido JSON del editor a HTML
     */
    public static function convertJsonToHtml($json)
    {
        return self::renderPostContent($json);
    }
    
    /**
     * Convierte contenido HTML a JSON del editor
     */
    public static function convertHtmlToJson($html)
    {
        // Crear una estructura JSON básica basada en el HTML
        $blocks = [];
        
        // Dividir el HTML en líneas para procesar
        $lines = explode("\n", $html);
        $currentBlock = null;
        
        // Procesar contenido HTML más complejo
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n", $html);
        $html = preg_replace('/<\/div>\s*<div[^>]*>/i', "\n", $html);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Detectar headers
            if (preg_match('/^<h([1-6])>(.*?)<\/h[1-6]>$/', $line, $matches)) {
                $blocks[] = [
                    'id' => uniqid('header_'),
                    'type' => 'header',
                    'data' => [
                        'text' => $matches[2],
                        'level' => intval($matches[1])
                    ]
                ];
            }
            // Detectar párrafos
            elseif (preg_match('/^<p>(.*?)<\/p>$/', $line, $matches)) {
                $blocks[] = [
                    'id' => uniqid('paragraph_'),
                    'type' => 'paragraph',
                    'data' => [
                        'text' => $matches[1]
                    ]
                ];
            }
            // Detectar listas
            elseif (preg_match('/^<ul>(.*?)<\/ul>$/s', $line, $matches)) {
                $listItems = [];
                preg_match_all('/<li>(.*?)<\/li>/', $matches[1], $itemMatches);
                
                foreach ($itemMatches[1] as $item) {
                    $listItems[] = [
                        'content' => $item,
                        'meta' => [],
                        'items' => []
                    ];
                }
                
                $blocks[] = [
                    'id' => uniqid('list_'),
                    'type' => 'list',
                    'data' => [
                        'style' => 'unordered',
                        'items' => $listItems
                    ]
                ];
            }
            // Detectar listas ordenadas
            elseif (preg_match('/^<ol>(.*?)<\/ol>$/s', $line, $matches)) {
                $listItems = [];
                preg_match_all('/<li>(.*?)<\/li>/', $matches[1], $itemMatches);
                
                foreach ($itemMatches[1] as $item) {
                    $listItems[] = [
                        'content' => $item,
                        'meta' => [],
                        'items' => []
                    ];
                }
                
                $blocks[] = [
                    'id' => uniqid('list_'),
                    'type' => 'list',
                    'data' => [
                        'style' => 'ordered',
                        'items' => $listItems
                    ]
                ];
            }
            // Detectar imágenes
            elseif (preg_match('/^<img.*?src="(.*?)".*?alt="(.*?)".*?>$/', $line, $matches)) {
                $imageUrl = self::buildImageUrl($matches[1]);
                $blocks[] = [
                    'id' => uniqid('image_'),
                    'type' => 'image',
                    'data' => [
                        'file' => [
                            'url' => $imageUrl
                        ],
                        'caption' => $matches[2]
                    ]
                ];
            }
            // Detectar imágenes sin alt
            elseif (preg_match('/^<img.*?src="(.*?)".*?>$/', $line, $matches)) {
                $imageUrl = self::buildImageUrl($matches[1]);
                $blocks[] = [
                    'id' => uniqid('image_'),
                    'type' => 'image',
                    'data' => [
                        'file' => [
                            'url' => $imageUrl
                        ],
                        'caption' => ''
                    ]
                ];
            }
            // Detectar citas
            elseif (preg_match('/^<blockquote.*?>(.*?)<\/blockquote>$/', $line, $matches)) {
                $blocks[] = [
                    'id' => uniqid('quote_'),
                    'type' => 'quote',
                    'data' => [
                        'text' => $matches[1],
                        'caption' => '',
                        'alignment' => 'left'
                    ]
                ];
            }
            // Detectar separadores (delimiters)
            elseif (preg_match('/^<hr.*?>$/', $line)) {
                $blocks[] = [
                    'id' => uniqid('delimiter_'),
                    'type' => 'delimiter',
                    'data' => []
                ];
            }
            // Detectar código en línea
            elseif (preg_match('/^<code>(.*?)<\/code>$/', $line, $matches)) {
                $blocks[] = [
                    'id' => uniqid('inline_code_'),
                    'type' => 'inline_code',
                    'data' => [
                        'text' => $matches[1]
                    ]
                ];
            }
            // Detectar videos de YouTube
            elseif (preg_match('/^<iframe.*?src=".*?youtube\.com\/embed\/([a-zA-Z0-9_-]+).*?".*?<\/iframe>$/', $line, $matches)) {
                $blocks[] = [
                    'id' => uniqid('video_'),
                    'type' => 'video',
                    'data' => [
                        'url' => 'https://www.youtube.com/watch?v=' . $matches[1],
                        'caption' => 'Video de YouTube'
                    ]
                ];
            }
            // Para cualquier otro contenido, crear un párrafo
            else {
                // Limpiar HTML tags básicos
                $cleanText = strip_tags($line);
                if (!empty($cleanText)) {
                    $blocks[] = [
                        'id' => uniqid('paragraph_'),
                        'type' => 'paragraph',
                        'data' => [
                            'text' => $cleanText
                        ]
                    ];
                }
            }
        }
        
        // Si no se encontraron bloques, crear un párrafo con el contenido original
        if (empty($blocks)) {
            $cleanHtml = strip_tags($html);
            if (!empty($cleanHtml)) {
                $blocks[] = [
                    'id' => uniqid('paragraph_'),
                    'type' => 'paragraph',
                    'data' => [
                        'text' => $cleanHtml
                    ]
                ];
            }
        }
        
        // Crear la estructura JSON completa
        $jsonStructure = [
            'time' => time() * 1000,
            'blocks' => $blocks,
            'version' => '2.31.0-rc.7'
        ];
        
        return json_encode($jsonStructure);
    }
    
    /**
     * Método auxiliar para renderizar elementos de lista, incluidos los anidados
     */
    private static function renderListItems($items)
    {
        $html = '';
        
        foreach ($items as $item) {
            $html .= "<li>";
            
            // El contenido principal del item
            $html .= $item['content'];
            
            // Si tiene items anidados
            if (!empty($item['items'])) {
                $nestedListType = isset($item['style']) && $item['style'] == 'ordered' ? 'ol' : 'ul';
                $html .= "<{$nestedListType}>";
                $html .= self::renderListItems($item['items']);
                $html .= "</{$nestedListType}>";
            }
            
            $html .= "</li>";
        }
        
        return $html;
    }

    // Asignar el contenido convertido a la variable que se mostrará en la vista

    public static function getURLsCategoriasByIdPost($id_post)
    {
        $con = Conexion::getInstance();

        $sth = $con->prepare("SELECT c.*
        FROM categorias c
        LEFT JOIN posts_categorias pc ON c.id_categoria = pc.id_categoria
        WHERE pc.id_post = :id_post");

        $sth->bindParam(':id_post', $id_post, PDO::PARAM_INT);
        $sth->execute();

        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getTotalPosts()
    {
        $con = Conexion::getInstance();
        $sth = $con->query("SELECT COUNT(*) FROM posts WHERE estado_post = 'publicado'");
        return $sth->fetchColumn();
    }

    public static function getTotalPostsByCategoria($id_categoria)
    {
        $con = Conexion::getInstance();
        $sth = $con->prepare("SELECT COUNT(id_post) as total FROM posts_categorias WHERE id_categoria = :id_categoria");
        $sth->bindParam(':id_categoria', $id_categoria, PDO::PARAM_INT);
        $sth->execute();

        return (int) $sth->fetchColumn();
    }

    public static function getPostPorSlug($slug)
    {
        $con = Conexion::getInstance();
        $sth = $con->prepare("
            SELECT p.*, 
                u.nombres_usuario, 
                GROUP_CONCAT(DISTINCT c.nombre_categoria ORDER BY c.nombre_categoria SEPARATOR ', ') AS nombre_categoria,
                GROUP_CONCAT(DISTINCT c.slug_categoria ORDER BY c.slug_categoria SEPARATOR ', ') AS slug_categoria
            FROM posts p 
            LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario 
            LEFT JOIN posts_categorias pc ON p.id_post = pc.id_post
            LEFT JOIN categorias c ON pc.id_categoria = c.id_categoria
            WHERE p.url_post = :slug
            GROUP BY p.id_post, u.nombres_usuario
        ");

        $sth->bindParam(':slug', $slug, PDO::PARAM_STR);
        $sth->execute();

        return $sth->fetch(PDO::FETCH_ASSOC);
    }

    public static function getLatestPostPorId($id)
    {
        $con = Conexion::getInstance();
        $sth = $con->prepare("
            SELECT p.*, 
                GROUP_CONCAT(DISTINCT c.nombre_categoria ORDER BY c.nombre_categoria SEPARATOR ', ') AS nombre_categoria,
                GROUP_CONCAT(DISTINCT c.slug_categoria ORDER BY c.slug_categoria SEPARATOR ', ') AS slug_categoria
            FROM posts p 
            LEFT JOIN posts_categorias pc ON p.id_post = pc.id_post
            LEFT JOIN categorias c ON pc.id_categoria = c.id_categoria
            WHERE p.id_post != :id AND p.estado_post = 'publicado'
            GROUP BY p.id_post
            ORDER BY p.id_post DESC 
            LIMIT 6
        ");

        $sth->bindValue(':id', $id, PDO::PARAM_INT);
        $sth->execute();

        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getLatestPost()
    {
        $con = Conexion::getInstance();
        $sth = $con->prepare("
            SELECT p.*, 
                GROUP_CONCAT(DISTINCT c.nombre_categoria ORDER BY c.nombre_categoria SEPARATOR ', ') AS nombre_categoria,
                GROUP_CONCAT(DISTINCT c.slug_categoria ORDER BY c.slug_categoria SEPARATOR ', ') AS slug_categoria
            FROM posts p 
            LEFT JOIN posts_categorias pc ON p.id_post = pc.id_post
            LEFT JOIN categorias c ON pc.id_categoria = c.id_categoria
            WHERE p.estado_post = 'publicado'
            GROUP BY p.id_post
            ORDER BY p.fecha_creacion_post DESC 
            LIMIT 6
        ");

        $sth->execute();
        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }


    public static function LinkedCategoriesPost()
    {

        $con = Conexion::getInstance();

        $id = $_POST['id_post'];

        $sth = $con->prepare("SELECT id_categoria FROM posts_categorias WHERE id_post = :id");
        $sth->bindValue(':id', $id, PDO::PARAM_INT);
        $sth->execute();

        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }




    public static function getPostsPorCategoria($id_categoria, $pagina = 1, $porPagina = 8)
    {
        $inicio = ($pagina - 1) * $porPagina; // Calcula correctamente el offset

        $con = Conexion::getInstance();

        $sth = $con->prepare("
            SELECT p.*, 
                u.nombres_usuario, 
                GROUP_CONCAT(DISTINCT c.nombre_categoria ORDER BY c.nombre_categoria SEPARATOR ', ') AS nombre_categoria,
                GROUP_CONCAT(DISTINCT c.slug_categoria ORDER BY c.slug_categoria SEPARATOR ', ') AS slug_categoria
            FROM posts p
            LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
            LEFT JOIN posts_categorias pc ON p.id_post = pc.id_post
            LEFT JOIN categorias c ON pc.id_categoria = c.id_categoria
            WHERE pc.id_categoria = :categoria
            AND p.estado_post = 'publicado'
            GROUP BY p.id_post, u.nombres_usuario
            ORDER BY p.fecha_creacion_post DESC
            LIMIT :inicio, :porPagina
        ");

        $sth->bindParam(':categoria', $id_categoria, PDO::PARAM_INT);
        $sth->bindParam(':inicio', $inicio, PDO::PARAM_INT);
        $sth->bindParam(':porPagina', $porPagina, PDO::PARAM_INT);

        $sth->execute();

        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }

    // En tu clase Model (por ejemplo, en el archivo Post.php)
    public static function buscarPostTitulo($query)
    {
        $input = trim(strip_tags($query));

        if (strlen($input) < 3) {
            return []; // Mínimo 3 caracteres
        }

        $db = Conexion::getInstance();

        $sql = "SELECT * FROM posts WHERE titulo_post LIKE :query LIMIT 10";
        $stmt = $db->prepare($sql);
        $param = '%' . $input . '%';
        $stmt->bindParam(':query', $param, PDO::PARAM_STR);

        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result ?: [];
    }


    public static function incrementarVistas($id_post)
    {
        $con = Conexion::getInstance();
        $sth = $con->prepare("UPDATE posts 
            SET vistas_post = vistas_post + 1 
            WHERE id_post = :id");
        $sth->bindParam(':id', $id_post, PDO::PARAM_INT);
        return $sth->execute();
    }

    public static function existPostCategory($post, $categoria)
    {
        $con = Conexion::getInstance();
        $sth = $con->prepare("SELECT * FROM posts_categorias WHERE id_post = :post AND id_categoria = :categoria");
        $sth->bindParam(':post', $post);
        $sth->bindParam(':categoria', $categoria);
        $sth->execute();
        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function insertPostCategorias($val, $lastInsertId)
    {

        $existe = self::existPostCategory($lastInsertId, $val);

        if ($existe) {
            return false;
        }

        $con = Conexion::getInstance();
        $sth = $con->prepare("INSERT posts_categorias (id_post, id_categoria) VALUES (:post, :categoria)");
        $sth->bindParam(':post', $lastInsertId);
        $sth->bindParam(':categoria', $val);
        $sth->execute();

        return true;
    }

    public static function deletePostCategorias()
    {
        $con = Conexion::getInstance();
        $sth = $con->prepare("DELETE FROM posts_categorias WHERE id_post = :post AND id_categoria = :categoria");
        $sth->bindParam(':post', $_POST['id_post']);
        $sth->bindParam(':categoria', $_POST['id_categoria']);
        $sth->execute();

        return true;
    }

    public static function save()
    {
        try {
            $titulo = $_POST['titulo_post'];
            $contenido_json = $_POST['contenido_json'] ?? '';
            
            // Convertir automáticamente del JSON a HTML
            $contenido_html = self::convertJsonToHtml($contenido_json);
            
            $video_youtube = $_POST['video_youtube_post'] ?? '';
            $creador_contenido = $_POST['creador_contenido'] ?? '';
            
            $slug = url_friend($titulo);
            $extracto = $_POST['extracto_post'];
            $fecha_creacion = date('Y-m-d H:i:s');
            $fecha_modificacion = date('Y-m-d H:i:s');
            $id_usuario = $_SESSION['usuario']->getId();
            $estado = $_POST['estado_post'];
            $meta_descripcion = $_POST['meta_descripcion_post'];
            $meta_keywords = $_POST['meta_keywords_post'];

            if (empty($titulo)) {
                return ['error' => 'Debe añadir un título al post'];
            }

            $obj_medios = new Medios();
            $imagenDestacada = null;
            $imagenBanner = null;

            // Imagen destacada
            error_log('=== DEBUG IMAGEN DESTACADA ===');
            error_log('FILES imagen_destacada_post: ' . print_r($_FILES['imagen_destacada_post'] ?? 'NO ENVIADO', true));
            error_log('POST biblioteca-destacado: ' . ($_POST['biblioteca-destacado'] ?? 'NO ENVIADO'));
            
            if (!empty($_FILES['imagen_destacada_post']['name']) && $_POST['biblioteca-destacado'] == "0") {
                $fileDestacada = $obj_medios->uploadMedios($_FILES['imagen_destacada_post']);
                error_log('Resultado uploadMedios destacada: ' . ($fileDestacada ?: 'FALSE'));
                if ($fileDestacada) {
                    $imagenDestacada = $fileDestacada;
                }
            } elseif (!empty($_POST['biblioteca-destacado'])) {
                $imagenDestacada = $_POST['biblioteca-destacado'];
                error_log('Imagen destacada desde biblioteca: ' . $imagenDestacada);
            }
            error_log('Imagen destacada final: ' . ($imagenDestacada ?: 'NULL'));

            // Imagen banner
            error_log('=== DEBUG IMAGEN BANNER ===');
            error_log('FILES imagen_banner_post: ' . print_r($_FILES['imagen_banner_post'] ?? 'NO ENVIADO', true));
            error_log('POST biblioteca-banner: ' . ($_POST['biblioteca-banner'] ?? 'NO ENVIADO'));
            
            if (!empty($_FILES['imagen_banner_post']['name']) && $_POST['biblioteca-banner'] == "0") {
                $fileBanner = $obj_medios->uploadMedios($_FILES['imagen_banner_post']);
                error_log('Resultado uploadMedios banner: ' . ($fileBanner ?: 'FALSE'));
                if ($fileBanner) {
                    $imagenBanner = $fileBanner;
                }
            } elseif (!empty($_POST['biblioteca-banner'])) {
                $imagenBanner = $_POST['biblioteca-banner'];
                error_log('Imagen banner desde biblioteca: ' . $imagenBanner);
            }
            error_log('Imagen banner final: ' . ($imagenBanner ?: 'NULL'));

            $con = Conexion::getInstance();
            $sth = $con->prepare("INSERT INTO posts 
                (titulo_post, url_post, contenido_html_post, video_youtube_post, creador_contenido, extracto_post, 
                imagen_destacada_post, imagen_banner_post, 
                fecha_creacion_post, fecha_modificacion_post, 
                id_usuario, estado_post, meta_descripcion_post, meta_keywords_post) 
                VALUES 
                (:titulo, :slug, :contenido_html, :video_youtube, :creador_contenido, :extracto, 
                :imagen_destacada, :imagen_banner, 
                :fecha_creacion, :fecha_modificacion, 
                :id_usuario, :estado, :meta_descripcion, :meta_keywords)");

            $sth->bindParam(':titulo', $titulo);
            $sth->bindParam(':slug', $slug);
            $sth->bindParam(':contenido_html', $contenido_html);
            $sth->bindParam(':video_youtube', $video_youtube);
            $sth->bindParam(':creador_contenido', $creador_contenido);
            $sth->bindParam(':extracto', $extracto);
            $sth->bindParam(':imagen_destacada', $imagenDestacada);
            $sth->bindParam(':imagen_banner', $imagenBanner);
            $sth->bindParam(':fecha_creacion', $fecha_creacion);
            $sth->bindParam(':fecha_modificacion', $fecha_modificacion);
            $sth->bindParam(':id_usuario', $id_usuario);
            $sth->bindParam(':estado', $estado);
            $sth->bindParam(':meta_descripcion', $meta_descripcion);
            $sth->bindParam(':meta_keywords', $meta_keywords);

            $sth->execute();

            // Guardar categorías
            if (!empty($_POST['categorias'])) {
                $categorias = explode(',', $_POST['categorias']);
                $lastInsertId = $con->lastInsertId();

                foreach ($categorias as $val) {
                    self::insertPostCategorias($val, $lastInsertId);
                }
            }

            return true;
        } catch (Exception $e) {
            error_log('Error en Blogs::save(): ' . $e->getMessage());
            return ['error' => 'Error al guardar el post: ' . $e->getMessage()];
        }
    }

    public static function update()
    {
        $id_post = $_POST['id_post'];
        $titulo = $_POST['titulo_post'];
        $contenido_json = $_POST['contenido_json'] ?? '';
        
        // Convertir automáticamente del JSON a HTML
        $contenido_html = self::convertJsonToHtml($contenido_json);
        
        $video_youtube = $_POST['video_youtube_post'] ?? '';
        $creador_contenido = $_POST['creador_contenido'] ?? '';
        
        $extracto = $_POST['extracto_post'];
        $estado = $_POST['estado_post'];
        $meta_descripcion = $_POST['meta_descripcion_post'];
        $meta_keywords = $_POST['meta_keywords_post'];
        $fecha_modificacion = date('Y-m-d H:i:s');
        $id_usuario = $_SESSION['usuario']->getId();
        $imagenes = [];
        $obj_medios = new Medios();

        $fieldsToUpdate = "titulo_post = :titulo, contenido_html_post = :contenido_html, video_youtube_post = :video_youtube, creador_contenido = :creador_contenido, extracto_post = :extracto, ";
        $imagenes = [];

        if (!empty($_FILES['imagen_destacada_post']['name']) && $_POST['biblioteca-destacado'] == "0") {
            $fileDestacada =  $obj_medios->uploadMedios($_FILES['imagen_destacada_post']);

            if ($fileDestacada) {
                $fieldsToUpdate .= "imagen_destacada_post = :imagen_destacada, ";
                $imagenes['imagen_destacada'] = $fileDestacada;
            }
        } elseif (!empty($_POST['biblioteca-destacado'])) {
            $fieldsToUpdate .= "imagen_destacada_post = :imagen_destacada, ";
            $imagenes['imagen_destacada'] = $_POST['biblioteca-destacado'];
        }

        if (!empty($_FILES['imagen_banner_post']['name']) && $_POST['biblioteca-banner'] == "0") {
            $fileBanner = $obj_medios->uploadMedios($_FILES['imagen_banner_post']);

            if ($fileBanner) {
                $fieldsToUpdate .= "imagen_banner_post = :imagen_banner, ";
                $imagenes['imagen_banner'] = $fileBanner;
            }
        } elseif (!empty($_POST['biblioteca-banner'])) {
            $fieldsToUpdate .= "imagen_banner_post = :imagen_banner, ";
            $imagenes['imagen_banner'] = $_POST['biblioteca-banner'];
        }

        $fieldsToUpdate .= "fecha_modificacion_post = :fecha_modificacion, id_usuario = :id_usuario, estado_post = :estado, meta_descripcion_post = :meta_descripcion, meta_keywords_post = :meta_keywords";
        $con = Conexion::getInstance();

        $query = "UPDATE posts SET $fieldsToUpdate WHERE id_post = :id";
        $sth = $con->prepare($query);

        $sth->bindParam(':titulo', $titulo);
        $sth->bindParam(':contenido_html', $contenido_html);
        $sth->bindParam(':video_youtube', $video_youtube);
        $sth->bindParam(':creador_contenido', $creador_contenido);
        $sth->bindParam(':extracto', $extracto);
        $sth->bindParam(':fecha_modificacion', $fecha_modificacion);
        $sth->bindParam(':id_usuario', $id_usuario);
        $sth->bindParam(':estado', $estado);
        $sth->bindParam(':meta_descripcion', $meta_descripcion);
        $sth->bindParam(':meta_keywords', $meta_keywords);
        $sth->bindParam(':id', $id_post);

        if (strpos($fieldsToUpdate, ':imagen_destacada') !== false) {
            $sth->bindParam(':imagen_destacada', $imagenes['imagen_destacada']);
        }
        if (strpos($fieldsToUpdate, ':imagen_banner') !== false) {
            $sth->bindParam(':imagen_banner', $imagenes['imagen_banner']);
        }

        $sth->execute();

        if (!empty($_POST['categorias'])) {
            $categorias = explode(',', $_POST['categorias']);

            foreach ($categorias as $val) {
                self::insertPostCategorias($val, $id_post);
            }
        }
        return true;
    }

    public static function delete()
    {
        try {
            // Validar que se recibió el ID del post
            if (!isset($_POST['id_post']) || empty($_POST['id_post'])) {
                return [
                    'success' => false,
                    'message' => 'ID del post no válido'
                ];
            }

            $id_post = $_POST['id_post'];
            $con = Conexion::getInstance();
            
            // Verificar que el post existe
            $checkPost = $con->prepare("SELECT id_post FROM posts WHERE id_post = :id");
            $checkPost->bindParam(':id', $id_post);
            $checkPost->execute();
            
            if (!$checkPost->fetch()) {
                return [
                    'success' => false,
                    'message' => 'El post no existe'
                ];
            }
            
            // Iniciar transacción
            $con->beginTransaction();
            
            // 1. Eliminar relaciones en posts_categorias primero
            $sth1 = $con->prepare("DELETE FROM posts_categorias WHERE id_post = :id");
            $sth1->bindParam(':id', $id_post);
            $sth1->execute();
            
            // 2. Eliminar el post
            $sth2 = $con->prepare("DELETE FROM posts WHERE id_post = :id");
            $sth2->bindParam(':id', $id_post);
            $result = $sth2->execute();
            
            // Confirmar transacción
            $con->commit();
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Post eliminado correctamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'No se pudo eliminar el post'
                ];
            }
            
        } catch (\Exception $e) {
            // Revertir transacción en caso de error
            if (isset($con) && $con->inTransaction()) {
                $con->rollback();
            }
            
            // Log del error para debugging
            error_log("Error al eliminar post: " . $e->getMessage());
            
            // Devolver respuesta estructurada
            return [
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ];
        }
    }

    public static function writeFriendlyUrl()
    {

        $result = url_friend($_POST['data']);
        return $result;
    }

    /**
     * Construye URL correcta para imágenes
     */
    private static function buildImageUrl($imagen)
    {
        if (empty($imagen)) {
            return '';
        }
        
        // Si ya es una URL completa, usarla
        if (strpos($imagen, 'http://') === 0 || strpos($imagen, 'https://') === 0) {
            return $imagen;
        }
        
        // Usar la configuración correcta
        global $_config;
        $baseUrl = $_config['server']['url'];
        
        // Si empieza con /app/public_root/, construir URL completa
        if (strpos($imagen, '/app/public_root/') === 0) {
            return rtrim($baseUrl, '/') . $imagen;
        }
        
        // Si es solo un nombre de archivo, construir URL local directamente en medios
        return rtrim($baseUrl, '/') . '/app/public_root/imgs/medios/' . $imagen;
    }
}
