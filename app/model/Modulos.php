<?php

namespace Develoweb\App\Model;

class Modulos
{
	
	private $_config;
	private $_rol;

	public function __construct ($_config, Rol $rol = null)
	{
		$this->_config = $_config;
		$this->_rol = $rol;

		if (count($this->_config) == 0) {
			echo "Falta Instalar";
		}
	}
	
	public function getModulosActivosDesactivos(){

			foreach ($this->_config as $modulos => $valor){			
				if ($modulos == 'modulos')	{
					foreach( $valor as $modulo=>$key ){							
						if ( $modulo == 'mod' ){
							$key = array_unique($key);
							foreach ($key as $modulo){
								$rutas_actives[] = array(
									'header' => $this->getRuta() . $modulo . '/inc.header.php',
									'body' => $this->getRuta() . $modulo . '/' . $modulo . '.php' ,
									'require' => $this->getRuta() . $modulo . '/require_once.php',
									'items_list' => $this->getRuta() . $modulo . '/admin/item_list.php',
									'url_modulo' => 'index.php?modulo=' . $modulo,
									'nombre' => ucwords(strtolower(preg_replace("/_/", " ", $modulo)))
								);
							}
						}
					}
				}						
			}		
	
		$modulos = ['active' => $rutas_actives ?? []];
		return $modulos;
	}
	
	public function getModulosPredefinidos () {
		foreach ($this->_config as $modulos => $valor){			
			if ( $modulos == "catalogo" ){
				foreach( $valor as $modulo=>$key ){
					if ( $key == TRUE ){
						$url_modulo = ($modulo=='productos')? "{$modulo}.php?cat=0":'';
						$replace = preg_replace("/_/", " ", $modulo);
						$rutas[] = [
							'nombre' => ucwords(strtolower($replace)),
							'url_modulo' => $url_modulo
						];
					}
				}
			}
		}		
		return $rutas;
	}
	
	// ubico si esta en una ubicacion antes los modulos para llamarlos
	public function getRuta(){
		if(preg_match("/dw-panel/i",$_SERVER['PHP_SELF'])){
			return "../".APP_MODULOS;
		}else{
			return APP_MODULOS;
		}
	}

	public function updateModulosSecciones(){
		
		$modulos = $this->getModulosActivosDesactivos();
		
		$modulos_activos = $modulos['active'];
		$modulos_desactivos	= $modulos['desactive'];
		
		$modulos_catalogo_predefinidos = $this->getModulosPredefinidos();
		
		$secciones = new Secciones();
		
		if ( count($modulos_activos) > 0 ){
			foreach ( $modulos_activos as $modulo_activo ){
				$data_active[] = [
					'id_modulo' => 3,
					'nombre_seccion' => $modulo_activo['nombre'],
					'url_seccion' => $modulo_activo['url_modulo']
				];
			}
			
			$secciones->addSecciones($data_active ?? []);
		}

		if ( count($modulos_catalogo_predefinidos) > 0 ){
			foreach ( $modulos_catalogo_predefinidos as $mod_cat_predef ) {
				$data_mod_cat_predef[] = [
					'id_modulo' => 2,
					'nombre_seccion' => $mod_cat_predef['nombre'],
					'url_seccion' => $mod_cat_predef['url_modulo'],
					'icon' => $mod_cat_predef['icon_modulo']
				];
			}			
			$secciones->addSecciones($data_mod_cat_predef ?? []);
		}

		if (count($modulos_desactivos) > 0) {
			foreach ($modulos_desactivos as $modulo_desactivo) {
				$data_desactive[] = ['nombre_seccion' => $modulo_desactivo['nombre']];
			}
			$secciones->deleteSecciones($data_desactive ?? []);
		}
		
	}

}