<?php

// Configurar manejo de errores para evitar HTML en respuestas JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

include_once '../app/utilities/vendor/autoload.php';
include 'inc.app.top.php';

// Todas las acciones del panel exigen sesión activa
if (\Develoweb\App\Model\Acl::usuario() === null) {
	http_response_code(401);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['status' => 'error', 'message' => 'Sesión expirada']);
	exit();
}

header('Content-Type: application/json; charset=utf-8');

if (isset($_POST['class']) && !empty($_POST['class'])) { 
	try {
		$class = MODEL_NAMESPACE . $_POST['class'];
		$method= $_POST['method'];
		if (class_exists($class) && method_exists($class, $method)) {
			echo json_encode($class::$method());
		} else {
			echo json_encode(['error' => 'Class or method does not exist.']);
		}
	} catch (Exception $e) {
		error_log('Error en ajax.php: ' . $e->getMessage());
		echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]);
	}
	exit();
}

if (isset($_GET['class']) && !empty($_GET['class'])) {
	$class = MODEL_NAMESPACE . $_GET['class'];
	$method= $_GET['method'];
	if (class_exists($class)) {
		$result = $class::$method();
		// Solo hacer echo si el método no ya envió su propia respuesta
		if (!headers_sent()) {
			echo json_encode($result);
		}
	}
	exit();
}

if (isset($_POST['util']) && !empty($_POST['util'])) {
	$util = $_POST['util'];
	$p1 = $_POST['p1'];
	$p2 = $_POST['p2'];
	$util($p1, $p2);
}

// Endpoint para convertir JSON a HTML
if (isset($_POST['action']) && $_POST['action'] === 'convert_json_to_html') {
	$json_content = $_POST['json_content'];
	
	// Incluir el modelo Blogs para usar la función de conversión
	require_once '../app/model/Blogs.php';
	
	$html = \Develoweb\App\Model\Blogs::convertJsonToHtml($json_content);
	
	echo json_encode(['html' => $html]);
	exit();
}

// Endpoint para convertir HTML a JSON
if (isset($_POST['action']) && $_POST['action'] === 'convert_html_to_json') {
	$html_content = $_POST['html_content'];
	
	// Incluir el modelo Blogs para usar la función de conversión
	require_once '../app/model/Blogs.php';
	
	$json_data = \Develoweb\App\Model\Blogs::convertHtmlToJson($html_content);
	
	if ($json_data) {
		echo json_encode([
			'success' => true,
			'json_data' => $json_data
		]);
	} else {
		echo json_encode([
			'success' => false,
			'error' => 'No se pudo convertir el HTML a JSON'
		]);
	}
	exit();
}

// Endpoint optimizado para subida de imágenes del editor
if (isset($_GET['upload-img-wysiwyg'])) {
	// Configuración del directorio de subida
	$uploadDir = PUBLIC_ROOT_HOST . 'imgs/wysiwyg/';
	
	// Crear directorio si no existe
	if (!is_dir($uploadDir)) {
		mkdir($uploadDir, 0755, true);
	}
	
	$basename = basename($_FILES['file']['name']);
	$uploadFile = $uploadDir . $basename;

	// Validaciones optimizadas
	$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
	$fileType = $_FILES['file']['type'];
	$fileSize = $_FILES['file']['size'];

	// Verificar si el archivo es una imagen válida
	if (!in_array($fileType, $allowedTypes)) {
		http_response_code(400);
		echo json_encode(['error' => 'Tipo de archivo no válido. Solo se permiten JPEG, PNG, GIF y WebP.']);
		exit;
	}

	// Verificar el tamaño del archivo (máximo 10 MB)
	if ($fileSize > 10 * 1024 * 1024) {
		http_response_code(400);
		echo json_encode(['error' => 'El archivo excede el límite máximo de 10 MB.']);
		exit;
	}

	// Mover el archivo subido al directorio de destino
	if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadFile)) {
		// Retornar la URL del archivo subido
		echo json_encode(['location' => str_replace('/dw-panel/', '', URL_WEB) . 'app/public_root/imgs/wysiwyg/' . $basename]);
	} else {
		http_response_code(500);
		echo json_encode(['error' => 'Error al subir el archivo.']);
	}
	exit;
}


// Endpoint optimizado para subida rápida de imágenes del editor (sin base de datos)
if (isset($_POST['action']) && $_POST['action'] === 'upload_image_editor') {
	// Configuración del directorio de subida final (medios/)
	$uploadDir = PUBLIC_ROOT_HOST . 'imgs/medios/';
	
	// Crear directorio si no existe
	if (!is_dir($uploadDir)) {
		mkdir($uploadDir, 0755, true);
	}
	
	// Verificar que se envió una imagen
	if (!isset($_FILES['image']) || $_FILES['image']['error'] !== 0) {
		echo json_encode([
			'success' => false,
			'message' => 'No se envió ninguna imagen válida'
		]);
		exit;
	}
	
	$file = $_FILES['image'];
	$fileName = $file['name'];
	$fileTmpPath = $file['tmp_name'];
	$fileSize = $file['size'];
	$fileType = $file['type'];
	
	// Validaciones
	$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
	if (!in_array($fileType, $allowedTypes)) {
		echo json_encode([
			'success' => false,
			'message' => 'Tipo de archivo no válido. Solo se permiten JPEG, PNG, GIF y WebP.'
		]);
		exit;
	}
	
	// Verificar tamaño (máximo 10 MB)
	if ($fileSize > 10 * 1024 * 1024) {
		echo json_encode([
			'success' => false,
			'message' => 'El archivo excede el límite máximo de 10 MB.'
		]);
		exit;
	}
	
	// Generar nombre único con timestamp para evitar conflictos
	$fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
	$uniqueName = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $fileName);
	$destPath = $uploadDir . $uniqueName;
	
	// Mover archivo
	if (move_uploaded_file($fileTmpPath, $destPath)) {
		// Construir URL pública final sin /dw-panel/
		$publicUrl = str_replace('/dw-panel/', '', URL_WEB) . 'app/public_root/imgs/medios/' . $uniqueName;
		
		echo json_encode([
			'success' => true,
			'file' => [
				'url' => $publicUrl,
				'name' => $uniqueName,
				'final' => true // Marcar como final (no temporal)
			]
		]);
	} else {
		echo json_encode([
			'success' => false,
			'message' => 'Error al guardar la imagen'
		]);
	}
	exit;
}

// Endpoint para mover imágenes temporales a la carpeta final cuando se guarda el post
if (isset($_POST['action']) && $_POST['action'] === 'move_temp_images') {
	$tempImages = $_POST['temp_images'] ?? [];
	$movedImages = [];
	
	foreach ($tempImages as $tempImage) {
		$tempPath = PUBLIC_ROOT_HOST . 'imgs/temp/' . $tempImage;
		$finalPath = PUBLIC_ROOT_HOST . 'imgs/medios/' . $tempImage;
		
		// Crear directorio final si no existe
		$finalDir = dirname($finalPath);
		if (!is_dir($finalDir)) {
			mkdir($finalDir, 0755, true);
		}
		
		// Mover archivo de temporal a final
		if (file_exists($tempPath) && rename($tempPath, $finalPath)) {
			$movedImages[] = $tempImage;
		}
	}
	
	echo json_encode([
		'success' => true,
		'moved_images' => $movedImages
	]);
	exit;
}