<?php

// Register the working tree before Composer so tests never resolve the
// installed API vendor copy of this package.
spl_autoload_register(static function (string $class): void {
    $prefix = 'BradTipper\\RestfulServer\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
}, true, true);

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
