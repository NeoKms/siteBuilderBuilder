<?php

function clsAutoload($className)
{
    $className = str_replace(['classes\\', 'Publication\API\v1'], '', $className);
    $className = str_replace('\\', '/', $className);
    $dirs = [
        '',
        'classes',
    ];
    $found = false;
    foreach ($dirs as $dir) {
        $fileName = __DIR__ . '/'. $dir . '/' . $className . '.php';
        if (is_file($fileName)) {
            require_once($fileName);
            $found = true;
        }
    }
    if (!$found) {
        return false;
    }
    return true;
}

$dir = __DIR__;
if (isset($dotenvDir)) {
    $dir = $dotenvDir;
    include_once($dotenvDir . 'vendor/autoload.php');//автолоад модулей композера
} else {
    include_once('vendor/autoload.php');//автолоад модулей композера
}
if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], ".dev.lan") !== false) {//скорее всего лишнее
    $dir = __DIR__;
}
//установка переменных окружения
$dotenv = \Dotenv\Dotenv::createUnsafeImmutable($dir);
$dotenv->load();
try {
    $dotenv->required([
        'API_HOST_NAME',
        'APP_PATH',
        'WWW_PATH',
        'SITE_ID',
        "API_PASS",
        'API_LOGIN',
    ]);
} catch (Exception $e) {
    die($e->getMessage());
}
//

spl_autoload_register("clsAutoload");
