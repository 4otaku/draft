<?php

include 'framework/init.php';

Autoload::init(array(LIBS, EXTERNAL, FRAMEWORK_LIBS, FRAMEWORK_EXTERNAL), CACHE);

Config::parse('define.ini', true);

$url = explode('/', preg_replace('/\?[^\/]+$/', '', $_SERVER['REQUEST_URI']));
$url = array_filter($url);
if (empty($url)) {
	$url = array('index');
}

$module = reset($url);

$class = 'Module_' . ucfirst($module);
if (!class_exists($class)) {
	$class = 'Module_Error';
}

$worker = new $class($url);
$worker->send_headers()->send_output();
