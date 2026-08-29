<?php

namespace Develoweb\App\Model;

use PDO;

class Paquetes
{

	private $_msgbox;

	public function __construct ($msg = null)
	{
		$this->_msgbox = $msg;
	}

	public static function get()
	{
		$con = Conexion::getInstance();
        $sth = $con->prepare(
			"SELECT * FROM paquetes"
		);
		$sth->execute();
		$users = $sth->fetchAll();
		return $users; 
	}

	
	// Método para obtener paquete por 'url_paquete'
	public static function getByUrlPaquete($url_paquete)
	{
		// Asumiendo que tienes una consulta que filtra los destinos por la URL
		$con = Conexion::getInstance();
		$stmt = $con->prepare("SELECT * FROM paquetes WHERE url_paquete = :url_paquete");
		$stmt->bindParam(':url_paquete', $url_paquete);
		$stmt->execute();
		return $stmt->fetch(PDO::FETCH_ASSOC);
	}

	public static function getByDestino($destino_id) {
		$con = Conexion::getInstance();
		$stmt = $con->prepare("SELECT * FROM paquetes WHERE id_destino = :id_destino");
		$stmt->bindParam(':id_destino', $destino_id);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public static function getByCategoria($categoria_id) {
		$con = Conexion::getInstance();
		$stmt = $con->prepare("SELECT * FROM paquetes WHERE id_categoria = :id_categoria");
		$stmt->bindParam(':id_categoria', $categoria_id);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	

	private static function validForm ()
	{
		if (! Validator::string($_POST['names'])) {
			$errors['names'] = 'Este campo es requerido.';
		}
		if (! Validator::string($_POST['lastnames'])) {
			$errors['lastnames'] = 'Este campo es requerido.';
		}
		if (! Validator::email($_POST['email'])) {
			$errors['email'] = 'No es una correo válido';
		}
		if (! empty($_POST['phone']) && (! ctype_digit($_POST['phone']) || strlen($_POST['phone']) !== 9)) {
			$errors['phone'] = 'El número no es válido. Y deben ser 9 digitos';
		}
		if (! Validator::string($_POST['username'])) {
			$errors['username'] = 'Este campo es requerido.';
		}
		if (($_POST['id'] == 0 || ! empty($_POST['password'])) && ! Validator::string($_POST['password'], 8)) {
			$errors['password'] = 'Este campo es requerido. Debe tene minimo 8 digitos.';
		}
		return $errors ?? [];
	}

	private static function uploadFile(string $fileName, string $fileTmpPath, string $uploadDir): string {

		$fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
		$uniqueName = uniqid() . '.' . $fileExtension;
		$destPath = rtrim($uploadDir, '/') . '/' . $uniqueName;
	
		$relativePath = 'images/paquetes/' . $uniqueName;
	
		if (!move_uploaded_file($fileTmpPath, $destPath)) {
			echo "Error al mover el archivo de imagen";
		}
	
		return $relativePath;
	}
	
	public static function store() {
	 	/* var_dump($_POST);
		var_dump($_FILES);
		exit; */
		$urlDpackage= url_friend($_POST['names']);
		$deteCreate = date("Y-m-d H:i:s");
	
		$imagePath = null;
		if (isset($_FILES['files-images-portada']) && $_FILES['files-images-portada']['error'] === UPLOAD_ERR_OK) {
			$imagePath = static::uploadFile($_FILES['files-images-portada']['name'], $_FILES['files-images-portada']['tmp_name'], ADMIN_IMGS_WEB_PAQUETES);
		}
	
		$con = Conexion::getInstance();
		$stmt = $con->prepare("INSERT INTO paquetes (id_destino, nombre_paquete, url_paquete, precio_paquete, precio_confidencial_paquete ,imagen_portada_paquete, fecha_creacion_paquete) VALUES (:destino, :names, :url, :prices, :pricesconfi, :imgPortada, :fechacreate)");
		$stmt->bindParam(":destino", $_POST['select_destination'], PDO::PARAM_INT);
		$stmt->bindParam(":names", $_POST['names'], PDO::PARAM_STR);
		$stmt->bindParam(":url", $urlDpackage, PDO::PARAM_STR);
		$stmt->bindParam(":prices", $_POST['prices'], PDO::PARAM_STR);
		$stmt->bindParam(":pricesconfi", $_POST['pricesconfi'], PDO::PARAM_STR);
		$stmt->bindParam(":imgPortada", $imagePath, PDO::PARAM_STR);
		$stmt->bindParam(":fechacreate", $deteCreate, PDO::PARAM_STR);
	
		$result = $stmt->execute();
	
		if (!$result) {
			return ['errors' => 'Ocurrió un error al guardar los cambios'];
		}
	
		return ['success' => true, 'message' => 'Paquete guardado correctamente'];
	}

	public static function uploadGaleryPackage() {

		if (isset($_FILES['files'])) {
			foreach ($_FILES['files']['name'] as $key => $name) {
				$tmp_name = $_FILES['files']['tmp_name'][$key];
				$destination = ADMIN_IMGS_WEB_PAQUETES . '/'.$name;
				
				if (move_uploaded_file($tmp_name, $destination)) {
					echo "Archivo {$name} subido con éxito.\n";
				} else {
					echo "Error al subir el archivo {$name}.\n";
				}
			}
		}
		exit;
	}
	

	public static function update ()
	{
		$validation = static::validForm();

		if (! empty($validation)) return ['errors' => $validation];

		$password_where = ! empty($_POST['password']) ? ', password_usuario = :password ' : '';

		$con = Conexion::getInstance();
		$stmt = $con->prepare("UPDATE usuarios SET id_rol = :id_rol, nombres_usuario = :nombres, apellidos_usuario = :apellidos, email_usuario = :email, login_usuario = :login, celular_usuario = :celular {$password_where} WHERE id_usuario = :id");
		$stmt->bindParam(":id_rol", $_POST['rol'], PDO::PARAM_INT);
		$stmt->bindParam(":nombres", $_POST['names'], PDO::PARAM_STR);
		$stmt->bindParam(":apellidos", $_POST['lastnames'], PDO::PARAM_STR);
		$stmt->bindParam(":email", $_POST['email'], PDO::PARAM_STR);
		$stmt->bindParam(":celular", $_POST['phone'], PDO::PARAM_STR);
		$stmt->bindParam(":login", $_POST['username'], PDO::PARAM_STR);
		$stmt->bindParam(":id", $_POST['id'], PDO::PARAM_INT);
		if (! empty($_POST['password'])) {
			$stmt->bindParam(":password", $password_hash, PDO::PARAM_STR);
		}

		$result = $stmt->execute();

		if (! $result) return ['errors' => 'Ocurrio un error al guardar los cambios'];
		return true;
	}

	public static function getAccess ()
	{
		$con = Conexion::getInstance();
        $sth = $con->prepare("SELECT s.id_seccion, m.nombre_modulo, s.nombre_seccion FROM secciones s, modulos m WHERE s.id_modulo = m.id_modulo AND s.estado_seccion = 1");
		$sth->execute();
		$access = $sth->fetchAll();
		return $access;
	}

	public static function loadCheckBox ()
	{
		$con = Conexion::getInstance();
        $sth = $con->prepare("SELECT id_seccion FROM usuarios_secciones WHERE id_usuario = :id");
		$sth->bindParam(":id", $_POST['user_id'], PDO::PARAM_INT);
		$sth->execute();
		$users_sections = $sth->fetchAll();

		foreach ($users_sections as $user_section) {
			$sections_id[] = $user_section["id_seccion"];
		}
		return $sections_id ?? [];
	}

	public static function storeDeleteAccess ()
	{
		$user_id = $_POST["user_id"];
		$section_id = $_POST["section_id"];
		$check = $_POST["check"];
		$sql = "DELETE FROM usuarios_secciones WHERE id_usuario = :user_id AND id_seccion = :section_id";
		if ($check == 1) {
			$sql = "INSERT INTO usuarios_secciones VALUES (:user_id, :section_id)";
		}

		$con = Conexion::getInstance();
        $stmt = $con->prepare($sql);
		$stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
		$stmt->bindParam(":section_id", $section_id, PDO::PARAM_INT);
		$result = $stmt->execute();

		if (! $result) return false;
        return true;
	}

}