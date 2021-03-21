<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('max_execution_time', '1200');

header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);

try {
    include_once('autoload.php');
    include_once('siteBuilder.php');

    if (empty($input) || !isset($input['auth']) || $input['auth'] != getenv('AUTH_KEY')) {
        print(json_encode('no auth'));
        exit;
    }
    if (!isset($input['template_id']) || !isset($input['site_id']) || !isset($input['page_name'])) {
        print(json_encode('no data'));
        exit;
    }

    $templateId = $input['template_id'];
    $siteId = $input['site_id'];
    $pageName = $input['page_name'];
    $tmpl = 'template' . time() . rand(0, 1000) . '/';
    $tmp = '/tmp' . time() . rand(0, 1000);
    //$tmp = '/tmp';
    @mkdir(__DIR__ . '/' . $tmpl);
    @mkdir(__DIR__ . $tmp);

    $constructor = new SiteBuilder\SiteBuilder([
        'preview' => true,
        'dist' => __DIR__ . '/',
        'cmsPath' => __DIR__ . '/',
        'asstesUrl' => getenv('API_HOST_NAME') . 'upload/templates/' . $templateId . '/assets/',
        'pageName' => $pageName,
        'tmpl' => $tmpl,
        'blockId' => -1,
    ]);
    $constructor->setPathTo(__DIR__ . $tmp);
    $res = $constructor->copyTemplate($templateId);
    $templateFiles = $constructor->getTemplateFiles(__DIR__ . '/' . $tmpl);
    $html = $constructor->build($templateFiles['site.settings.json'], $templateFiles, [], $pageName);
    file_put_contents(__DIR__ . $tmp . "/tmpHtml.php", $html);
//    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] . '/siteBuilderBuilder/builder';
    $protocol = stripos($_SERVER['SERVER_PROTOCOL'], 'https') === 0 ? 'https://' : 'http://';
    $html = file_get_contents($protocol . $_SERVER['HTTP_HOST'] . '/' . $tmp . '/tmpHtml.php');
    echo json_encode(['message' => 'ok', 'html' => $html]);
} catch (Exception $e) {
    echo json_encode(['message' => 'error', 'error' => $e->getMessage()]);
}
@delDir(__DIR__.'/'.$tmpl);
@delDir(__DIR__.$tmp);

function delDir($dir) {
    $files = array_diff(scandir($dir), ['.','..']);
    foreach ($files as $file) {
        (is_dir($dir.'/'.$file)) ? delDir($dir.'/'.$file) : unlink($dir.'/'.$file);
    }
    return rmdir($dir);
}
