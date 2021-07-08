<?php


namespace classes;

use classes\database\Database;
use classes\database\Singleton;
use classes\models\Publications;
use classes\models\Liters;
use classes\models\Objects;
use classes\models\Site;
use Exception;

class BackActions
{
    use Singleton;
    private $query = [];
    private $apiHostName = null;
    private $url = null;
    private $www = null;

    private function __construct()
    {
        $this->url = getenv('API_HOST_NAME');
        $this->apiHostName = getenv('API_HOST_NAME');
        $this->www = getenv('WWW_PATH');
    }

    ///снести базу и загрузить все публикации заново
    public function initPublications($siteData)
    {
        /////////////////getData/////////////////////////
        $info = ["username" => getenv('API_LOGIN'), "password" => getenv('API_PASS')];
        $res = $this->__curlQuery($info, $this->url."auth/login", true);
        if (!isset($res['message']) || $res['message']!=='ok') {
            throw new \Exception('no auth');
        }
        $ids = [];
        foreach ($siteData['publications'] as $publ) {
            $ids[] = $publ['id'];
        }
        $publications = $objects = $liters = [];
        $this->getPubl($ids,$publications, $objects, $liters);
        /////////////init db//////////////
        $db = Database::getInstance();
        $db->reinitDb();
        echo "\nБудет добавлено: публикаций ", count($publications),
        ', объектов ', count($objects), ', литер ', count($liters);
        /////////////add data in DB////////////
        if (!empty($objects)) {
            (new Objects())->set($objects, false);
        }
        if (!empty($liters)) {
            (new Liters())->set($liters, false);
        }
        if (!empty($publications)) {
            (new Publications())->set($publications, false);
        }
        (new Site())->setMainProps('БЦ');
    }
    ///получает публикации
    private function getPubl($ids,&$publications, &$objects, &$liters)
    {
        $res = $this->__curlQuery(['ids'=>$ids,'build'=>1], $this->url . 'publications/byFilter/');
        if (!isset($res['message']) || $res['message']!=='ok') {
            throw new \Exception('no publ');
        }
        $data = $res['result'];
        $publications = $res['result'];
        $litersIds = $ObjectIds = [];
        foreach ($data as $onePubl) {
            $litersIds[] = $onePubl['liter_id'];
            $ObjectIds[] = $onePubl['object_id'];
        }
        $litersIds = array_values(array_unique($litersIds));
        $ObjectIds = array_values(array_unique($ObjectIds));
        $liters = $this->getLiters($litersIds);
        $objects = $this->getObjects($ObjectIds);
    }
    ///получает литеры
    private function getLiters($ids)
    {
        $res = $this->__curlQuery(['ids' => $ids], $this->url . 'liters/byIds/');
        if (!isset($res['message']) || $res['message']!=='ok') {
            throw new \Exception('no liter');
        }
        return $res['result'];
    }
    ///получает объекты
    private function getObjects($ids)
    {
        $res = $this->__curlQuery(['ids' => $ids], $this->url . 'objects/byIds/');
        if (!isset($res['message']) || $res['message']!=='ok') {
            throw new \Exception('no objects');
        }
        return $res['result'];
    }
    ///сделать курл запрос
    private function __curlQuery($data, $url, $auth = false)
    {
        if ($auth) {
            file_put_contents('myCookie','');
        }
        $cookie = file_get_contents('myCookie');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_USERAGENT, 'siteBuilder');
        curl_setopt($ch, CURLOPT_HEADER, true);
        $out = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($out, 0, $header_size);
        if ($auth) {
            if (preg_match_all('/Set-Cookie:[\s]([^;]+)/', $header, $matches)) {
                $cookies = $matches[1];
            }
            file_put_contents('myCookie', print_r($cookies[0], true));
        }
        $body = substr($out, $header_size);
        curl_close($ch);
        return json_decode($body, true);
    }
    ///красивый вывод
    private function dump($data)
    {
        if (is_array($data)) {
            print "<pre>-----------------------\n";
            print_r($data);
            print "-----------------------</pre>";
        } elseif (is_object($data)) {
            print "<pre>==========================\n";
            var_dump($data);
            print "===========================</pre>";
        } else {
            print "=========&gt; ";
            var_dump($data);
            print " &lt;=========";
        }
    }
}
