<?php

include_once './app/utilities/vendor/autoload.php';
include_once 'inc.aplication_top.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$class = isset($_GET['class']) ? MODEL_NAMESPACE . $_GET['class'] : '';
$method = $_GET['method'] ?? '';

if (empty($class) || ! class_exists($class) || empty($method) || ! method_exists($class, $method)) {
  header("HTTP/1.0 404 Not Found", true, 404);
  echo 'page not found';
  exit(); 
}

$result = call_user_func([ $class, $method ]);

echo json_encode($result); 
exit();