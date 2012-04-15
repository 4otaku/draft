<?php

include 'framework/init.php';

Autoload::init(array(LIBS, EXTERNAL, FRAMEWORK_LIBS, FRAMEWORK_EXTERNAL), CACHE);

Config::parse('define.ini', true);

$url = explode('/', preg_replace('/\?[^\/]+$/', '', $_SERVER['REQUEST_URI']));
array_shift($url);
