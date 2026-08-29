<?php

namespace Develoweb\App\Model;

use Error;
use Exception;
use PDO;

class Editor
{
	private $_msgbox;

	public function __construct($msg = null)
	{
		$this->_msgbox = $msg;
	}

	public static function uploadImgsEditor() {
        // Cambiar temporalmente el índice a 'file' para reutilizar el método
        if (!isset($_FILES['image'])) {
            echo json_encode([
                'success' => 0,
                'message' => 'No se envió ninguna imagen'
            ]);
            exit;
        }
    
        // Reasignar el índice para reutilizar Medios::uploadMedios
        $_FILES['file'] = $_FILES['image'];
    
        $obj_medios = new Medios();
        $res = $obj_medios->uploadMedios(); // usa $_FILES['file'] internamente
    
        if ($res) {
            $url_publica = str_replace('/dw-panel/', '', URL_WEB) . 'app/public_root/imgs/medios/' . $res;
    
            echo json_encode([
                'success' => 1,
                'file' => [
                    'url' => $url_publica
                ]
            ]);
        } else {
            echo json_encode([
                'success' => 0,
                'message' => 'Error al guardar la imagen'
            ]);
        }
        exit;
    }


}