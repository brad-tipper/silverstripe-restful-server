<?php

$autoloaders = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../api/vendor/autoload.php',
];

foreach ($autoloaders as $autoloader) {
    if (is_file($autoloader)) {
        require_once $autoloader;
        break;
    }
}

require_once __DIR__ . '/../src/Api/JsonResponseTrait.php';
