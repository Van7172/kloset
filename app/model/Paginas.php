<?php

namespace Develoweb\App\Model;

use PDO;

class Paginas
{
    private $_msgbox;

    public function __construct($msg = null)
    {
        $this->_msgbox = $msg;
    }

    public static function get($pagina = 1, $porPagina = 25)
    {
        $con = Conexion::getInstance();
        $inicio = ($pagina - 1) * $porPagina;

        $sth = $con->prepare("SELECT p.*, u.nombres_usuario 
            FROM paginas p
            LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario 
            ORDER BY p.fecha_creacion_pagina DESC 
            LIMIT :inicio, :porPagina");

        $sth->bindParam(':inicio', $inicio, PDO::PARAM_INT);
        $sth->bindParam(':porPagina', $porPagina, PDO::PARAM_INT);
        $sth->execute();

        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getByUrlPagina($url_pagina)
    {
        $con = Conexion::getInstance();
        $stmt = $con->prepare("SELECT * FROM paginas WHERE url_pagina = :url_pagina");
        $stmt->bindParam(':url_pagina', $url_pagina);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function renderPaginaContent($json)
    {
        $data = json_decode($json, true);
        $html = '';

        if (isset($data['blocks'])) {
            foreach ($data['blocks'] as $block) {
                switch ($block['type']) {
                    case 'header':
                        $level = intval($block['data']['level']);
                        $text = $block['data']['text'];
                        $html .= "<h{$level}>{$text}</h{$level}>";
                        break;

                    case 'paragraph':
                        $text = $block['data']['text'];
                        $html .= "<p>{$text}</p>";
                        break;

                    case 'image':
                        $imageUrl = $block['data']['file']['url'];
                        $caption = isset($block['data']['caption']) ? $block['data']['caption'] : '';

                        $html .= "<div class=\"post-image\">";
                        $html .= "<img src=\"{$imageUrl}\" alt=\"" . htmlspecialchars($caption, ENT_QUOTES) . "\" style=\"max-width:100%;height:auto;\">";
                        if (!empty($caption)) {
                            $html .= "<p class=\"caption\"><em>{$caption}</em></p>";
                        }
                        $html .= "</div>";
                        break;

                    case 'list':
                        $listType = $block['data']['style'] == 'unordered' ? 'ul' : 'ol';
                        $html .= "<{$listType}>";

                        // Procesa los items de la lista, incluidos los anidados
                        $html .= self::renderListItems($block['data']['items']);

                        $html .= "</{$listType}>";
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
                                        $html .= "<tbody>";
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
                        $text = $block['data']['text'];
                        $caption = isset($block['data']['caption']) ? $block['data']['caption'] : '';
                        $alignment = isset($block['data']['alignment']) ? $block['data']['alignment'] : 'left';

                        $html .= "<blockquote class=\"align-{$alignment}\">";
                        $html .= "<p>{$text}</p>";
                        if (!empty($caption)) {
                            $html .= "<footer>{$caption}</footer>";
                        }
                        $html .= "</blockquote>";
                        break;

                    default:
                        // Para tipos de bloques no manejados explícitamente
                        $html .= "<!-- Tipo de bloque no soportado: {$block['type']} -->";
                        break;
                }
            }
        }

        return $html;
    }

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

    public static function add()
    {
        $titulo = trim($_POST['titulo_pagina']);
        $cuerpo = trim($_POST['cuerpo_pagina']);
        $user = $_SESSION['usuario']->getId();
        $url = trim($_POST['url_pagina']);
        $estado = $_POST['estado_pagina'];
        $metaTitle = trim($_POST['meta_title_pagina'] ?? '');
        $metaDescription = trim($_POST['meta_descripcion_pagina'] ?? '');
        $fecha = date('Y-m-d');
        $banner = null;

        $obj_medios = new Medios();

        // Manejo de imagen de banner (desde input o biblioteca)
        if (!empty($_FILES['imagen_banner_pagina']['name']) && $_POST['biblioteca-banner'] == "0") {
            $banner = $obj_medios->uploadMedios($_FILES['imagen_banner_pagina']);
        } elseif (!empty($_POST['biblioteca-banner'])) {
            $banner = $_POST['biblioteca-banner'];
        }

        try {
            $con = Conexion::getInstance();
            $sth = $con->prepare("INSERT INTO paginas (
                id_usuario, titulo_pagina, cuerpo_pagina, fecha_creacion_pagina, fecha_modificacion_pagina,
                url_pagina, meta_title_pagina, meta_description_pagina, estado_pagina, banner_pagina
            ) VALUES (
                :usuario, :titulo, :cuerpo, :fechaC, :fechaM,
                :url, :metaTitle, :metaDescription, :estado, :banner
            )");

            $sth->bindParam(':usuario', $user);
            $sth->bindParam(':titulo', $titulo);
            $sth->bindParam(':cuerpo', $cuerpo);
            $sth->bindParam(':fechaC', $fecha);
            $sth->bindParam(':fechaM', $fecha);
            $sth->bindParam(':url', $url);
            $sth->bindParam(':metaTitle', $metaTitle);
            $sth->bindParam(':metaDescription', $metaDescription);
            $sth->bindParam(':estado', $estado);
            $sth->bindParam(':banner', $banner);

            if ($sth->execute()) {
                return true;
            }

            throw new \Exception('Error al insertar la página');
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }


    public static function update()
    {
        $id_pagina = $_POST['id_pagina'];
        $titulo = $_POST['titulo_pagina'];
        $cuerpo = $_POST['cuerpo_pagina'];
        $estado = $_POST['estado_pagina'];
        $metaTitle = $_POST['meta_title_pagina'];
        $metaDescription = $_POST['meta_descripcion_pagina'];
        $fecha_modificacion = date('Y-m-d');
        $obj_medios = new Medios();

        $fieldsToUpdate = "titulo_pagina = :titulo, cuerpo_pagina = :cuerpo, estado_pagina = :estado, meta_title_pagina = :metaTitle, meta_description_pagina = :metaDescription, fecha_modificacion_pagina = :fechaM";
        $imagenes = [];

        // Manejo de imagen de banner (desde input o biblioteca)
        if (!empty($_FILES['imagen_banner_pagina']['name']) && $_POST['biblioteca-banner'] == "") {
            $fileBanner = $obj_medios->uploadMedios($_FILES['imagen_banner_pagina']);
            if ($fileBanner) {
                $fieldsToUpdate .= ", banner_pagina = :banner";
                $imagenes['banner'] = $fileBanner;
            }
        } elseif (!empty($_POST['biblioteca-banner'])) {
            $fieldsToUpdate .= ", banner_pagina = :banner";
            $imagenes['banner'] = $_POST['biblioteca-banner'];
        }

        $query = "UPDATE paginas SET $fieldsToUpdate WHERE id_pagina = :id";

        $con = Conexion::getInstance();
        $sth = $con->prepare($query);

        // Bind principales
        $sth->bindParam(':id', $id_pagina);
        $sth->bindParam(':titulo', $titulo);
        $sth->bindParam(':cuerpo', $cuerpo);
        $sth->bindParam(':metaTitle', $metaTitle);
        $sth->bindParam(':metaDescription', $metaDescription);
        $sth->bindParam(':estado', $estado);
        $sth->bindParam(':fechaM', $fecha_modificacion);

        // Bind imagen si se estableció
        if (strpos($fieldsToUpdate, ':banner') !== false) {
            $sth->bindParam(':banner', $imagenes['banner']);
        }

        return $sth->execute();
    }

    public static function delete()
    {
        $id_pagina = $_POST['id_pagina'];
        $con = Conexion::getInstance();
        $sth = $con->prepare("DELETE FROM paginas WHERE id_pagina = :id");
        $sth->bindParam(':id', $id_pagina);
        return $sth->execute();
    }

    public static function writeFriendlyUrl()
    {
        $result = url_friend($_POST['data']);
        return $result;
    }
}
