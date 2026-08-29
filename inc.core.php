<?php
 
$_config = require __DIR__ . '/app/inc.config.php';

date_default_timezone_set('America/Lima');
 
define('URL_WEB', $_config['server']['url']);
define('HOST', $_config['server']['host']);
define('DW_PANEL', $_config['server']['url'] . 'dw-panel/');	

define('PUBLIC_ROOT_HOST', $_config['server']['host'] . 'app/public_root/');
define('PUBLIC_ROOT', $_config['server']['url'] . 'app/public_root/');
define('IMGS', $_config['server']['url'] . 'app/public_root/imgs/');

//ACTULIAZACION DE LOGO
define('LOGO', $_config['server']['url'] . 'app/public_root/imgs/logo.png');

define('APP_MODULOS', 'app/modulos/');
define('APP_UTILITIES', $_config['server']['host'] . 'app/utilities/');

// constants from admin
define('ADMIN_TEMPLATE_HOST', HOST . 'app/view/back_end/' . $_config['plantilla']['back'] . '/');
define('ADMIN_TEMPLATE', URL_WEB . 'app/view/back_end/' . $_config['plantilla']['back'] . '/');
define('ADMIN_INCLUDE', ADMIN_TEMPLATE_HOST . 'includes/');
define('ADMIN_IMGS_HOST', ADMIN_INCLUDE . 'images/');

define('ADMIN_IMGS_WEB_DESTINOS', ADMIN_TEMPLATE_HOST . 'images/destinos');
define('ADMIN_IMGS_WEB_PAQUETES', ADMIN_TEMPLATE_HOST . 'images/paquetes');
define('ADMIN_IMGS_WEB_BLOGS', ADMIN_TEMPLATE_HOST . 'images/blogs');
define('ADMIN_IMGS_WEB_ICOS', ADMIN_TEMPLATE_HOST . 'images/icos');

define('ADMIN_IMGS', ADMIN_TEMPLATE . 'images/');
define('ADMIN_IMGS_WEB', ADMIN_IMGS . 'blogs/');
define('ADMIN_IMGS_WEB_ico', ADMIN_IMGS . 'icos/');

define('ADMIN_ASSETS', ADMIN_TEMPLATE . 'assets/');
define('ADMIN_IMGS_2', ADMIN_TEMPLATE . 'assets/images/');
define('ADMIN_CSS', ADMIN_TEMPLATE . 'assets/css/');
define('ADMIN_JS', ADMIN_TEMPLATE . 'assets/js/');

// front constant front
define('FRONT_TEMPLATE_HOST', HOST . 'app/view/front_end/' . $_config['plantilla']['front'] . '/');
define('FRONT_TEMPLATE', URL_WEB . 'app/view/front_end/' . $_config['plantilla']['front'] . '/');
define('FRONT_INCLUDE', FRONT_TEMPLATE_HOST . 'includes/');
define('FRONT_CSS', FRONT_TEMPLATE . 'css/');
define('FRONT_JS', FRONT_TEMPLATE . 'js/');
define('FRONT_IMGS', FRONT_TEMPLATE . 'img/');
define('FRONT_IMGS_HOST', FRONT_TEMPLATE_HOST . 'img/');
define('FRONT_PLUGINS', FRONT_TEMPLATE . 'plugins/');
define('FRONT_PLUGINS_ROOT', FRONT_TEMPLATE_HOST . 'plugins/');

// back constant back
define('BACK_TEMPLATE', URL_WEB . 'app/view/back_end/' . $_config['plantilla']['back'] . '/');
define('BACK_PLUGINS', BACK_TEMPLATE . 'assets/plugins/');

// constants from bbdd
define('DB_HOST', $_config['db']['host']);
define("DB_DATABASE", $_config['db']['name']);
define("DB_USER", $_config['db']['user']);
define("DB_PASS", $_config['db']['password']);
define("DB_PORT", $_config['db']['port']);

// constants from send mails
define('SERVIDOR_CORREO_URL', $_config['admin']['servidor_correo_url']);
define('SERVIDOR_CORREO_USUARIO', $_config['admin']['servidor_correo_usuario']);
define('SERVIDOR_CORREO_CONTRASENA', $_config['admin']['servidor_correo_contrasena']);

define('CURRENT_DATETIME', date('Y-m-d H:i:s'));
define('CURRENT_DATE', date('Y-m-d'));

define("MODEL_NAMESPACE", "Develoweb\\App\\Model\\");
