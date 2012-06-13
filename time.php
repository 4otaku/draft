<?php

header('Content-type: application/json');
echo json_encode(array('time' => microtime(true)));
