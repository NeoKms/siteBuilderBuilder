<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('max_execution_time', '1200');
/////////////////////////////СБОРКА САЙТА///////////////////////
if (!file_exists(__DIR__.'/.env')) die('no .env');
file_put_contents(
    __DIR__.'/.env',
    str_replace('{{id}}', (explode('/', __DIR__)[6] ?? 1), file_get_contents(__DIR__.'/.env'))
);
include_once('autoload.php');
include_once('siteBuilder.php');
$constructor = new SiteBuilder\SiteBuilder();
if (!empty($argv[1])) {
    $path_to = $argv[1];
} else {
    $path_to = getenv('WWW_PATH');
}
if (!empty($argv[2])) {
    $id = $argv[2];
} else {
    $id = getenv('SITE_ID');
}
$path_tmpl = getenv('APP_PATH') . 'template/';
$time = microtime(true);
@delDir($path_tmpl);
@delDir($path_to);
try {
    $constructor->setPathTo($path_to);
    $res = $constructor->copyTemplate($id);//здесь копируется шаблон и настройки
    if ($res!==true) {
        die($res);
    }
    $templateFiles = $constructor->getTemplateFiles($path_tmpl);//здесь они забираются для билдера
    $constructor->build(
        $templateFiles['site.settings.json'],
        $templateFiles,
        $templateFiles['template.json']
    );//а это собирает
    $timeEnd = microtime(true) - $time;
    echo 'Сайт собран за ', round($timeEnd, 3), ' секунд';
    $databaseLock = __DIR__ . '/database.lock';
//    if (!file_exists($databaseLock)) {
        //////////////////СБОРКА БАЗЫ////////////////////////////////////////////
        $time = microtime(true);
        classes\BackActions::getInstance()->initPublications($constructor->getSiteData());
//        file_put_contents($databaseLock, '');
        $timeEnd = microtime(true) - $time;
        echo "\nБаза собрана за ", round($timeEnd, 3), ' секунд';
//    }
} catch (Exception $e) {
    die($e->getMessage());
}
//header("Location: ../app/index.php");//local
function delDir($dir) {
    $files = array_diff(scandir($dir), ['.','..']);
    foreach ($files as $file) {
        (is_dir($dir.'/'.$file)) ? delDir($dir.'/'.$file) : unlink($dir.'/'.$file);
    }
    return rmdir($dir);
}
