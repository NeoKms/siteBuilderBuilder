<?php

namespace SiteBuilder;

class SiteBuilder
{
    public $log = true;
    private $twig = null;
    private $feedback = [];
    private $style = [];
    private $site = [];
    private $pathTo = null;
    private $dist = null;
    private $preview = null;
    private $headerIncludeArea = '';
    private $url = null;
    private $apiHostName = null;
    private $assetsUrl = null;
    private $siteData = null;
    
    public function __construct($options = [])
    {
        $this->preview = false;
        if (!empty($options)) {
            if (isset($options['preview'])
                && isset($options['dist'])
                && isset($options['cmsPath'])
                && isset($options['asstesUrl'])) {
                $this->preview = true;
                $this->assetsUrl = $options['asstesUrl'];
            } else {
                throw new \Exception('нет всех данных для генерации превью');
            }
        }
        if ($this->preview) {
            $this->dist = $options['dist'];
            @mkdir($this->dist, 0777, true);

        } else {
            $this->dist = getenv('APP_PATH');
            @mkdir($this->dist . 'template/', 0777, true);
        }
        $this->url = getenv('API_HOST_NAME');
        $this->apiHostName = getenv('API_HOST_NAME');
        if ($this->preview) {
            $this->headerIncludeArea = '<?php 
       $dotenvDir = "'.$options['cmsPath'].'";
       include_once(\'' . $options['cmsPath'] . 'autoload.php\'); 
       use classes\FrontController; 
       $fa = FrontController::getInstance(); 
       $mainProps = $fa->getMainProps();
       $req = $fa->checkRequest($_REQUEST); 
       $_REQUEST["id"]=2522;
       ?>';
        } else {
            $this->headerIncludeArea = '<?php 
       include_once(\'' . $this->dist . 'autoload.php\'); 
       use classes\FrontController; 
       $fa = FrontController::getInstance(); 
       $mainProps = $fa->getMainProps();
       $req = $fa->checkRequest($_REQUEST); 
       ?>';
        }
        try {
            if ($this->preview) {
                $loader = new \Twig\Loader\FilesystemLoader($this->dist);
            } else {
                $loader = new \Twig\Loader\FilesystemLoader($this->dist . 'template/');
            }
            $this->twig = new \Twig\Environment($loader);
        } catch (Exception $e) {
            die('ERROR: ' . $e->getMessage());
        }
    }

    private function copyAssets($path)
    {
        $dir = @opendir($path);
        if (!$dir) {
            return [];
        }
        @mkdir($this->pathTo);
        $notInclude = ['.', '..'];
        while ($file = readdir($dir)) {
            if (!in_array($file, $notInclude)) {
                $this->recurseCopy($path . '/' . $file, $this->pathTo . '/' . $file, true);
            }
        }
        closedir($dir);
    }

    private function recurseCopy($src, $dst, $is_assets = false)
    {
        $dir = opendir($src);
        @mkdir($dst);
        $notInclude = ['.', '..', 'original', 'skip'];
        while (false !== ($file = readdir($dir))) {
            if (!in_array($file, $notInclude)) {
                if (is_dir($src . '/' . $file)) {
                    $this->recurseCopy($src . '/' . $file, $dst . '/' . $file, $is_assets);
                } else {
                    if ($file == 'index.php' && $is_assets) {
                        $content = file_get_contents($src . '/' . $file);
                        $content = str_replace('{{PATH}}', getenv('APP_PATH'), $content);
                        file_put_contents($dst . '/' . $file, $content);
                    } else {
                        copy($src . '/' . $file, $dst . '/' . $file);
                    }
                }
            }
        }
        closedir($dir);
    }

    public function setPathTo($path)
    {
        @mkdir($path);
        $this->pathTo = $path;
    }

    public function getTemplateFiles($path)
    {
        $dir = @opendir($path);
        if (!$dir) {
            return [];
        }
        $arFiles = [];
        $notInclude = ['assets', '.', '..', 'original', 'skip'];
        while ($file = readdir($dir)) {
            if ($file == 'assets' && !$this->preview) {
                $this->copyAssets($path . $file);
            }
            if (!in_array($file, $notInclude)) {
                if (is_file($path . $file)) {
                    $content = '';
                    if (strpos($file, '.json')) {
                        $content = file_get_contents($path . $file);
                        $content = json_decode($content, true);
                    }
                    $arFiles[$file] = $content;
                } else {
                    $arFiles[$file] = $this->getTemplateFiles($path . $file . '/');
                }
            }
        }
        closedir($dir);
        return $arFiles;
    }

    public function setStyles($style)
    {
        try {
            foreach ($style as $oneStyle) {
                if ($oneStyle['name'] == 'Обратная связь') {
                    foreach ($oneStyle['elements'] as $oneProp) {
                        $opt = [];
                        switch ($oneProp['name']) {
                            case 'Тема':
                                $name = 'theme';
                                break;
                            case 'Данные':
                                $name = 'inputs';
                                $opt = $oneProp['data']['options'];
                                break;
                        }
                        if (!empty($opt)) {
                            $this->feedback[$name] = $opt;
                        } else {
                            $this->feedback[$name]['id'] = $oneProp['data']['value'];
                        }
                    }
                } elseif ($oneStyle['name'] == 'Основные настройки шаблона') {
                    foreach ($oneStyle['elements'] as $oneProp) {
                        switch ($oneProp['name']) {
                            case 'Цветовая схема':
                                $name = 'scheme';
                                break;
                            case 'Шрифт':
                                $name = 'font';
                                break;
                            case 'Формы':
                                $name = 'forms';
                                break;
                            case 'Цвет подложек':
                                $name = 'back';
                                break;
                        }
                        $this->style[$name] = $oneProp['data']['value'];
                    }
                }
            }
        } catch (Exception $e) {
            $this->log("Ошибка установки настроек: " . $e->getMessage(), 2);
            return false;
        }
        return true;
    }

    private function setSiteProps($props)
    {
        try {
            $this->site['name'] = $props['name'];
            $this->site['address'] = $props['address'];
            $this->site['contacts'] = is_array($props['contacts']) ? $props['contacts'] : json_decode($props['contacts']);
        } catch (Exception $e) {
            $this->log("Ошибка установки значений сайта: " . $e->getMessage(), 2);
            return false;
        }
    }

    public function build($options, $templateFiles, $templateSettins = [], $pageName = '')
    {
        $site = [];
        if (empty($options) || !isset($options['pages']) || empty($options['pages'])
            || !isset($options['siteProps']) || empty($options['siteProps'])) {
            $this->log("Нет настроек или страниц сайта", 2);
            $this->log(array_keys($templateFiles), 2);
            return false;
        }
        if ((!isset($options['style']) || empty($options['style'])) &&
            (!isset($templateSettins['style']) || !empty($templateSettins['style']))
        ) {
            $this->log("Нет стилей сайта. Взято из шаблона", 2);
            $this->log(array_keys($templateFiles), 2);
            $options['style'] = $templateSettins['style'];
        }
        if ($this->setStyles($options['style']) === false) {
            return false;
        }
        if ($this->setSiteProps($options['siteProps']) === false) {
            return false;
        }
        //заменяем стандартные штуки
        $arrNavBar = ['navList' => []];
        foreach ($options['pages'] as $onePage) {
            if ($pageName!=='' && $pageName!==$onePage['name'] && $this->preview){
                $page = 'empty';
            } else {
                $page = $this->getPage($onePage, $templateFiles);
            }
            if (!empty($page)) {
                $fl = true;
                if ($onePage['name'] == "Главная") {
                    $nameEn = 'index';
                } elseif ($onePage['name']=="Страница объекта") {
                    $onePage['name'] = 'detail';
                    $nameEn = 'detail';
                    $fl = false;
                } else {
                    $nameEn = $this->transliterate($onePage['name']);
                }
                if ($fl) {
                    $arrNavBar['navList'][] = ['ru' => $onePage['name'], 'en' => $nameEn];
                }
                $site[$onePage['name']] = $page;
            }
        }
        //стандартные данные для сборки страниц
        $data = [
            'siteProps'=> $this->site,
            'navList' => $arrNavBar['navList'],
            'headerIncludeArea'=>$this->headerIncludeArea,
            'style' => $this->style,
        ];
        //добавляем страницы вне основного меню
        if (isset($templateFiles['other_pages'])) {
            foreach ($templateFiles['other_pages'] as $name => $values) {
                $site[$name]['other'] = $this->twig->render('other_pages/' . $name . '/' . $name . '.tmpl', $data);
            }
        }
        $preview = '';
        //сборка сайта в одно целое
        foreach ($site as $name => $onePage) {
            if ($this->preview && $onePage === 'empty') {
                continue;
            }
            //если это страница не из основного меню, то дополняем путь
            if (isset($onePage['other'])) {
                $dopPath = 'other_pages/';
            } else {
                $dopPath = '';
            }
            //берем хедер
            $page = ($this->preview?'<? define(\'IS_TEST_DATABASE\',true);?>':'').$this->twig->render('header.tmpl', $data);
            //вставляем блоки
            foreach ($onePage as $oneBlock) {
                $page .= $oneBlock;
            }
            //завершаем футером
            $page .= $this->twig->render('footer.tmpl', $data);
            //сохряняем
            if ($page !== '' && !$this->preview) {
                if ($name == 'Главная') {
                    $filename = "index.php";
                } else {
                    $filename = $this->transliterate($name) . '.php';
                }
                @mkdir($this->pathTo);
                file_put_contents($this->pathTo . $filename, $page);
            } elseif ($this->preview) {
                $preview = $this->replaceLinks($page);
            }
        }
        if ($this->preview) return $preview;
    }

    public function replaceLinks($page)
    {
        return str_replace(
            [
                'src="js/',
                'href="css/',
//                'img/',
            ],
            [
                'src="'.$this->assetsUrl.'js/',
                'href="'.$this->assetsUrl.'css/'
            ],
            $page
        );
    }
    public function getPage($pageOptions, $templateFiles)
    {
        if (!$pageOptions['active']) {
            return [];
        }
        if (!isset($templateFiles[$pageOptions['name']])) {
            $this->log("В шаблоне нет страницы {$pageOptions['name']}", 2);
            return [];
        }
        if (!isset($pageOptions['blockList'])) {
            $this->log("У страницы {$pageOptions['name']} нет элементов", 2);
            return [];
        }
        $page = [];
        //идем по блокам страницы
        $page_tmpl = $templateFiles[$pageOptions['name']];
        $i=0;//для тестов
        foreach ($pageOptions['blockList'] as $oneBlock) {
//            if ($pageOptions['name']!='Публикации') {continue;};
//            if ($i!=0) {$i++;continue;}//для тестов
            $oneBlock['siteProps'] = $this->site;
            if (!isset($page_tmpl[$oneBlock['id'] . '.tmpl'])) {
                $this->log("У страницы {$pageOptions['name']} нет шаблона блока {$oneBlock['name']}", 2);
                continue;
            }
            $blockName = $oneBlock['id'] . '.tmpl';
            $oneBlock = $this->rebaseOpt($oneBlock);
            $blockHTML = $this->twig->render($pageOptions['name'] . '/' . $blockName, $oneBlock);
            //готовый блок. для тестов
//             echo $blockHTML;
//            echo "<pre>";
//            var_dump($oneBlock);
//            echo "</pre>";exit;
            $page[$oneBlock['order']] = $blockHTML;
        }
        return $page;
    }

    private function rebaseOpt($oneBlock)
    {
        $res = $oneBlock;
        $res['elements'] = [];
        foreach ($oneBlock['elements'] as $oneElem) {
            if (isset($oneElem['data']['img'])) {
                if (stripos($oneElem['data']['img'], 'http') === false) {
                    $oneElem['data']['img'] = $this->apiHostName.$oneElem['data']['img'];
                }
                $name = md5(pathinfo($oneElem['data']['img'])['filename']) . '.jpeg';
                if (!file_exists($this->pathTo . 'img/' . $name) && !$this->preview) {
                    copy($oneElem['data']['img'], $this->pathTo . 'img/' . $name);
                }
                if ($this->preview) {
                    if (strpos($oneElem['data']['img'],'http')===false) {
                        $oneElem['data']['img'] = $this->assetsUrl . str_replace('./', '', $oneElem['data']['img']);
                    }
                } else {
                    $oneElem['data']['img'] = 'img/' . $name;
                }
            }
            if (isset($oneElem['data']['block'])) {
                foreach ($oneElem['data']['block'] as $key => $val) {
                    if (isset($val['img'])) {
                        if (stripos($val['img'], 'http') === false) {
                            $val['img'] = $this->apiHostName.$val['img'];
                        }
                        $name = md5(pathinfo($val['img'])['filename']) . '.jpeg';
                        $photoPath = $this->pathTo . 'img/' . $name;
                        if (!file_exists($photoPath) && !$this->preview && !empty($val['img'])) {
                            copy($val['img'], $photoPath);
                        }
                        if ($this->preview) {
                            if (strpos($oneElem['data']['block'][$key]['img'],'http')===false) {
                                $oneElem['data']['block'][$key]['img'] = $this->assetsUrl . str_replace('./', '', $oneElem['data']['block'][$key]['img']);
                            }
                        } else {
                            $oneElem['data']['block'][$key]['img'] = 'img/' . $name;
                        }
                    }
                }
            }
            //textAreaSimple со словом ИСТОРИЯ разбиваем на части и на абзацы ~ |
            if (isset($oneElem['data']) && isset($oneElem['data']['value']) &&
                $oneElem['type'] == 'textAreaSimple' &&
                (
                    strpos($oneElem['name'], 'история')!==false ||
                    strpos($oneElem['name'], 'История')!==false
                )
            ) {
                $oneElem['data']['value'] = explode('~', $oneElem['data']['value']);
                if (count($oneElem['data']['value']) > 3) {
                    for ($i = 3; $i < count($oneElem['data']['value']); $i++) {
                        $oneElem['data']['value'][2] .= $oneElem['data']['value'][$i];
                    }
                }
                $oneElem['data']['width'] = 100 / count($oneElem['data']['value']);
            }
            //selectMultiple переводим к уже включенным велью
            if (isset($oneElem['type']) && $oneElem['type'] == 'selectMultiple') {
                $tmp = [];
                foreach ($oneElem['data']['options'] as $oneVal) {
                    if (in_array($oneVal['value'], $oneElem['data']['value'])) {
                        $tmp[] = $oneVal['label'];
                    }
                }
                $oneElem['data']['value'] = $tmp;
            }
            $res['elements'][str_replace(' ', '_', $oneElem['name'])] = $oneElem;
        }
        return $res;
    }

    private function log($str, $type = 1)
    {
        if ($this->log) {
            if (is_array($str)) {
                $str = print_r($str, true);
            }
            if ($type == 2) {
                $str = 'ERROR: ' . $str;
            }
            $output = '[' . date('d.m.Y H:i:s') . '] ' . $str . "\n";
            if ($this->preview) {
                throw new \Exception($output);
            } else {
                file_put_contents('builder.log', $output, FILE_APPEND);
            }
        }
    }

    public function dump($value, $exit = true)
    {
        echo "<div style='text-align: left;padding-left: 60px; font-size: 10px;'><pre>";
        var_dump($value);
        echo "</pre></div><br>";
        if ($exit) {
            exit;
        }
    }

    private function transliterate($string)
    {
        $converter = [
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'e',
            'ж' => 'zh',
            'з' => 'z',
            'и' => 'i',
            'й' => 'y',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'h',
            'ц' => 'c',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'sch',
            'ь' => '\'',
            'ы' => 'y',
            'ъ' => '\'',
            'э' => 'e',
            'ю' => 'yu',
            'я' => 'ya',
            'А' => 'A',
            'Б' => 'B',
            'В' => 'V',
            'Г' => 'G',
            'Д' => 'D',
            'Е' => 'E',
            'Ё' => 'E',
            'Ж' => 'Zh',
            'З' => 'Z',
            'И' => 'I',
            'Й' => 'Y',
            'К' => 'K',
            'Л' => 'L',
            'М' => 'M',
            'Н' => 'N',
            'О' => 'O',
            'П' => 'P',
            'Р' => 'R',
            'С' => 'S',
            'Т' => 'T',
            'У' => 'U',
            'Ф' => 'F',
            'Х' => 'H',
            'Ц' => 'C',
            'Ч' => 'Ch',
            'Ш' => 'Sh',
            'Щ' => 'Sch',
            'Ь' => '\'',
            'Ы' => 'Y',
            'Ъ' => '\'',
            'Э' => 'E',
            'Ю' => 'Yu',
            'Я' => 'Ya',
        ];
        return strtr($string, $converter);
    }

    public function copyTemplate($id)
    {
        $info = ["username" => getenv('API_LOGIN'), "password" => getenv('API_PASS')];
        $res = $this->__curlQuery($info, $this->url."auth/login");
        if (!isset($res['message']) || $res['message']!=='ok') {
            throw new \Exception('no auth');
        }
        $res = $this->__curlGet($this->url."sites/$id/build");
        if (!isset($res['message']) || $res['message']!=='ok') {
            throw new \Exception('no data');
        }
        $res = $res['result'];
        $this->siteData = $res;
        $idTemplate = $res['template']['id'];
        $error = 0;
        $error_file = [];
//        echo "<pre>";
//        var_dump($arTemplates['files']);
//        echo "</pre>";
//        exit;
        @mkdir($this->dist.'template/', 0777, true);
        foreach ($res['download'] as $file) {
            $filename = $file['name'];
            $filepath = $file['path'];
            $src = $this->apiHostName . (str_replace(['%2F'],'/',rawurlencode($filepath)));
            $dst = $this->dist;
            if (stripos($filepath, 'upload/sites') !== false) {
                $dst = $dst . 'template/' . $filename;
            } else {
                $path = explode('templates/' . $idTemplate, $filepath);
                $dst = $dst . 'template' . $path[1];
                $directoria = str_replace($filename,'',$dst);
                @mkdir($directoria, 0777, true);
            }
            $newFile = copy($src, $dst);
            if (empty($newFile)) {
                $error_file[] = $filepath;
                $error++;
            } elseif (strpos($filename,'.php')!==false) {
                $content = file_get_contents($src);
                file_put_contents($dst, str_replace('{{APP_PATH}}', getenv('APP_PATH'),$content));
            }
        }
        if ($error == 0) {
            return true;
        } else {
            $this->log($error, 2);
            return "Ошибка в этих файлах:" . print_r($error_file,true) . ".  Не удалось сохранить файлы";
        }
    }
    private function __curlQuery($data, $url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch,CURLOPT_COOKIEJAR,'myCookie');
        curl_setopt($ch,CURLOPT_COOKIEFILE ,'myCookie');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_USERAGENT, 'sitebuilder');
        $out = curl_exec($ch);
        curl_close($ch);
        return json_decode($out, true);
    }
    private function __curlGet($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch,CURLOPT_COOKIEJAR,'myCookie');
        curl_setopt($ch,CURLOPT_COOKIEFILE ,'myCookie');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_USERAGENT, 'sitebuilder');
        $out = curl_exec($ch);
        curl_close($ch);
        return json_decode($out, true);
    }
    public function getSiteData()
    {
        return $this->siteData;
    }
}

