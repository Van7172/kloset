<?php

namespace Develoweb\App\Utilities;

class VideoHelper
{
    /**
     * Extrae el código del video de YouTube de una URL
     * @param string $url URL del video de YouTube
     * @return string Código del video o la URL original si no se puede extraer
     */
    public static function extractYouTubeCode($url)
    {
        if (empty($url)) {
            return '';
        }

        // Si ya es solo un código (sin URL), devolverlo tal como está
        if (strlen($url) <= 11 && !str_contains($url, '/')) {
            return $url;
        }

        // Patrones para extraer el código del video
        $patterns = [
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/',
            '/youtu\.be\/([a-zA-Z0-9_-]+)/',
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/',
            '/youtube\.com\/v\/([a-zA-Z0-9_-]+)/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        // Si no se puede extraer, devolver la URL original
        return $url;
    }

    /**
     * Genera el HTML del iframe para el video de YouTube
     * @param string $videoCode Código del video o URL
     * @param int $width Ancho del video (por defecto 100%)
     * @param int $height Alto del video (por defecto 400)
     * @param array $options Opciones adicionales
     * @return string HTML del iframe
     */
    public static function generateYouTubeEmbed($videoCode, $width = 100, $height = 400, $options = [])
    {
        $code = self::extractYouTubeCode($videoCode);
        
        if (empty($code)) {
            return '';
        }

        // Opciones por defecto
        $defaultOptions = [
            'rel' => 0,
            'showinfo' => 0,
            'modestbranding' => 1,
            'controls' => 1
        ];

        $options = array_merge($defaultOptions, $options);
        
        // Construir query string
        $queryString = http_build_query($options);
        
        $widthStyle = $width === 100 ? '100%' : $width . 'px';
        $heightStyle = $height . 'px';

        return sprintf(
            '<div class="video-container mb-4">
                <iframe 
                    width="%s" 
                    height="%s" 
                    src="https://www.youtube.com/embed/%s?%s" 
                    frameborder="0" 
                    allowfullscreen>
                </iframe>
            </div>',
            $widthStyle,
            $heightStyle,
            $code,
            $queryString
        );
    }
} 