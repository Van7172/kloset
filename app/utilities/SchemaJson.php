<?php

namespace Develoweb\App\Utilities;

use Develoweb\App\Model\Post;
use Develoweb\App\Model\Blogs;
use Develoweb\App\Model\Categoria;
use Develoweb\App\Model\Categorias;

class SchemaJson
{
    // Función helper para construir URL de imagen inteligentemente
    static private function buildImageUrl($imagen, $carpeta = 'medios') {
        if (empty($imagen)) return '';
        
        // Si es una URL completa (viene de CURL), usarla tal como está
        if (strpos($imagen, 'http://') === 0 || strpos($imagen, 'https://') === 0 || strpos($imagen, '://') !== false) {
            return $imagen;
        }
        
        // Si es solo un nombre de archivo, construir la URL local
        return IMGS . $carpeta . '/' . $imagen;
    }

    static public function getAllSchemaArticles()
    {
        $noticias = Blogs::getByFront();

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => []
        ];

        foreach ($noticias as $index => $noticia) {
            $url = URL_WEB . $noticia['url_post'];

            $schema['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'item' => [
                    '@type' => 'NewsArticle',
                    'headline' => $noticia['titulo_post'],
                    'image' => [self::buildImageUrl($noticia['imagen_destacada_post'])],
                    'datePublished' => $noticia['fecha_creacion_post'],
                    'dateModified' => $noticia['fecha_modificacion_post'],
                    'url' => $url,
                    'author' => [
                        '@type' => 'Person',
                        'name' => $noticia['nombres_usuario']
                    ],
                    'publisher' => [
                        '@type' => 'Organization',
                        'name' => constant('NOMBRE_SITIO'),
                        'url' => URL_WEB
                    ],
                    'articleSection' => $noticia['nombre_categoria']
                ]
            ];
        }

        return json_encode($schema);
    }

    static public function getSchemaArticle($id_noticia)
    {
        $noticia = new Post($id_noticia);
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $noticia->getTitulo(),
            'image' => [ self::buildImageUrl($noticia->getImagenDestacada())],
            'datePublished' => $noticia->getFechaCreacion(),
            'dateModified' => $noticia->getFechaModificacion(),
            'author' => array(['@type' => 'Organization', 'name' => constant('NOMBRE_SITIO'), 'url' => URL_WEB]),
            'publisher' => ['name' => constant('NOMBRE_SITIO'), 'url' => URL_WEB]
        );

        return json_encode($schema);
    }

    static public function getSchemaBreadcrumb($tipo, $id = '')
    {
        $retorno = array(
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList'
        );
        $ruta[] = array('@type' => 'ListItem', 'position' => 1, 'name' => 'home', 'item' => URL_WEB);

        switch ($tipo) {
            case 'detalle-post':
                $noticia = new Post($id);
                $ruta[] = array('@type' => 'ListItem', 'position' => 2, 'name' => $noticia->getTitulo());
                break;

            case 'categoria':
                $categoria = new Categoria($id);

                $ruta[] = array('@type' => 'ListItem', 'position' => 2, 'name' => 'categoria', 'item' => URL_WEB.'categoria');

                if ($categoria->__get('_id') > 0) {
                    
                    $ruta[] = array('@type' => 'ListItem', 'position' => 3, 'name' => $categoria->__get('_nombre'), 'item' => URL_WEB.'categoria/'.$categoria->__get('_slug'));
                }
                
                break;

            default:
                # code
                break;
        }
        $retorno['itemListElement'] = $ruta;
        return json_encode($retorno);
    }

    static public function getSchemaCategoria($id)
    {
        $categoria = new Categoria($id);
    
        if ($categoria->__get('_id') > 0) {
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $categoria->__get('_nombre'),
                'description' => $categoria->__get('_descripcion'),
                'url' => URL_WEB . 'categoria/' . $categoria->__get('_slug')
            ];
    
            return json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    
        return json_encode(['error' => 'Categoría no encontrada']);
    }

    static public function getSchemaPostsByCategoria($id, $pagina = 1, $porPagina = 5)
    {
        $posts = Blogs::getPostsPorCategoria($id, $pagina, $porPagina);

        if (empty($posts)) {
            return json_encode(['error' => 'No hay artículos en esta categoría']);
        }
 
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => 'Posts por Categoría',
            'itemListElement' => []
        ];

        foreach ($posts as $index => $post) {
            $schema['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'item' => [
                    '@type' => 'NewsArticle',
                    'headline' => $post['titulo_post'],
                    'datePublished' => $post['fecha_creacion_post'],
                    'dateModified' => $post['fecha_modificacion_post'],
                    'url' => URL_WEB . $post['url_post'],
                    'author' => [
                        '@type' => 'Person',
                        'name' => $post['nombres_usuario']
                    ],
                    'publisher' => [
                        '@type' => 'Organization',
                        'name' => constant('NOMBRE_SITIO'),
                        'url' => URL_WEB
                    ]
                ]
            ];
        }

        return json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

}
