<?php

function formato_date ($comodin, $fecha) {
	$nfecha = explode($comodin, $fecha);
	$dia = $nfecha[0];
	$mes = $nfecha[1];
	$axo = $nfecha[2];
	$ufecha = $axo . "-" . $mes . "-" . $dia;
	return $ufecha;
}

function formato_slash ($comodin, $fecha) {
	$nfecha = explode($comodin, $fecha);
	$dia = $nfecha[2];
	$mes = $nfecha[1];
	$axo = $nfecha[0];
	$ufecha = $dia . "/" . $mes . "/" . $axo;
	return $ufecha;
}

function uploadImgs(string $fileName, string $fileTmpPath, string $uploadDir): string {
    $uploadDir = rtrim($uploadDir, '/') . '/';
    
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
    $uniqueName = uniqid() . '_' . sanitizeFileName($fileName);
    $destPath = $uploadDir . $uniqueName;

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array(strtolower($fileExtension), $allowedExtensions)) {
        return '';
    }

    if (move_uploaded_file($fileTmpPath, $destPath)) {
        return $uniqueName;
    }

    return '';
}

// Función auxiliar para sanitizar nombres de archivo
function sanitizeFileName(string $filename): string {
    $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename);
    return substr($filename, 0, 100); // Limitar longitud
}

function passencode ($password) {
	$newpass = ( md5($password) . '&' . strrev(strlen($password)));
	return $newpass;
}

function passdecode ($password){
	$newpass = strrev($password);
	$newpass = explode('&', $newpass);
	$newpass = $newpass[0];	
	return $newpass;
}

function encriptar ($valor) {
	$cad = strlen($valor);
	$subcad = ceil($cad / 2);
	$prev_valor = substr(strrev($valor), 0, $subcad);
	$next_valor = substr(strrev($valor), $subcad, $cad);
	$pcad = $cad * 647667904564;	
	$pass = $pcad . '|' . $prev_valor . '$' . $subcad . '|' . $next_valor . '$w3809245n0t9';	
	return str_replace("'", "?", $pass);		
}

function desencriptar ($valor) {
	$cad = strlen($valor);
	$subcad = ceil($cad/2);
	$new_valor = explode("|",$valor);

	$pvalor = explode("$",$new_valor[1]);
	$prev_valor = $pvalor[0];

	$nvalor = explode("$",$new_valor[2]);
	$next_valor = $nvalor[0];

	$pass = strrev($prev_valor.$next_valor);
	return str_replace('?',"'",$pass);
}

function Month ($fecha) {
	$nfecha = explode("-",$fecha);
	$dia = $nfecha[2];
	$mes = $nfecha[1];
	$ano = $nfecha[0];
	$meses = array('01' => 'Enero','02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre');
	return  $meses[$mes]." ".$ano;
}

function fecha_long ($fecha){
	$nfecha = explode("-",$fecha);
	$dia = $nfecha[2];
	$mes = $nfecha[1];
	$ano = $nfecha[0];
	$meses = array('01' => 'Enero','02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre');
	return  $dia." de ".$meses[$mes]." del ".$ano; 
}

function ext ($archivo) {
	$trozos = explode("." , $archivo);
	$ext = $trozos[ count($trozos) - 1];
	return (string) $ext;
}

function aumentarMonth ($desde, $cant) {
	$fecha = $desde;
	return date("Y-m-d", strtotime("$fecha +".$cant." month"));
}

function createTree ($array, $currentParent, $currLevel = 0, $prevLevel = -1) {
	foreach ($array as $categoryId => $category) {
		if ($currentParent == $category['parent_id']) {

			return $categoryId . ",";

			if ($currLevel > $prevLevel) { $prevLevel = $currLevel; }
			$currLevel++; 
			createTree ($array, $categoryId, $currLevel, $prevLevel);
			$currLevel--;
		}
	}
}

function typeImage ($type) {
	$type_file = ""; 
	switch ($type) {
		case 'image/jpeg': case 'image/pjpeg':
			$type_file = ".jpg";
		break;
		case 'image/gif':
			$type_file = ".gif";
		break;
		case 'image/png':
			$type_file = ".png";
		break;	
	}
	return $type_file;
}

function url_friend ($url) {
	$replace = [
		'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Ä' => 'a', 'Å' => 'a', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'a', 'Æ' => 'a',
		'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
		'Ì' => 'i', 'Í' => 'i', 'Î' => 'i', 'Ï' => 'i', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
		'Ò' => 'o', 'Ó' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ö' => 'o', 'Ø' => 'o', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
		'Ù' => 'u', 'Ú' => 'u', 'Û' => 'u', 'Ü' => 'u', 'ù' => 'u', 'ú' => 'u', 'û' => 'u',
		'Ç' => 'c', 'ç' => 'c',
		'Þ' => 'b', 'þ' => 'b',
		'Ð' => 'd', 'ð' => 'd',
		'Ñ' => 'n', 'ñ' => 'n',
		'Ý' => 'y', 'ÿ' => 'y', 'ý' => 'y',
		'Ŕ' => 'r', 'ŕ' => 'r',
		'ß' => 's',
	];
	$url = strtr($url, $replace);
	$url = strtolower($url);
	$url = preg_replace('/[^A-Za-z0-9_-]/', ' ', $url);
	$url = trim($url);
	$url = preg_replace('/ +/', "-", strtolower($url));
	$url = preg_replace('/-+/', "-", strtolower($url));
	return $url;
}

function _get ($key) {
	return (isset($_GET[$key]) && ! empty($_GET[$key])) ? true : false;
}

function _post ($key) {
	return (isset($_POST[$key]) && ! empty($_POST[$key])) ? true : false;
}

function redirect ($url) {
	header('location: ' . DW_PANEL . $url);
}

function dd ($value) {
	echo "<pre>";
	print_r($value);
	echo "</pre>";
	die();
}
 
function formatBytes($size, $precision = 2)
{
    $base = log($size, 1024);
    $suffixes = array('', 'KB', 'MB', 'GB', 'TB');   

    return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
}