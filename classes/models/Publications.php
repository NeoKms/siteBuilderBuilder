<?php


namespace classes\models;

use classes\database\Database;

class Publications
{

    private $page = [
        'current' => '1',
        'row' => 12,
        'order' => ['id', 'ASC']
    ];
    private $db;
    private $url;
    private $wwwPath;
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->url = getenv('API_HOST_NAME');
        $this->wwwPath = getenv('WWW_PATH');
    }

    public function getList($param)
    {
        $table = 'publication';

        $sql = $this->db->selectValue(
            (empty($param['select']) ? [] : $param['select']),
            empty($param['filter']) ? [] : $param['filter'],
            $this->db->getMapAs($table),
            empty($param['additional']) ? [] : $param['additional']
        );
        $q = "SELECT " . $sql['select'] . " FROM " . $table . $sql['where'];
        $dopRes = [];
        $this->queryPage($q, $dopRes);
        $res = $this->db->query($q);
        $result = $res;
        if (isset($sql['has']['object'])) {
            $objectIds = [];
            foreach ($res as $onePubl) {
                $objectIds[] = $onePubl['object_id'];
            }
            $objects = (new Objects())->getList([
                'filter' => ['&id' => $objectIds],
                'select' => ['name', 'subway', 'address'],
                'additional' => ['keys']
            ]);
            foreach ($result as $key => $oneObj) {
                $result[$key]['object'] = $objects[$oneObj['object_id']];
            }
        }
        if (isset($sql['has']['liter'])) {
            $literIds = [];
            foreach ($res as $onePubl) {
                $literIds[] = $onePubl['liter_id'];
            }
            $liters = (new Liters())->getList([
                'filter' => ['&id' => $literIds],
                'select' => ['name', 'class', 'lifts'],
                'additional' => ['keys']
            ]);
            foreach ($result as $key => $oneObj) {
                $result[$key]['liter'] = $liters[$oneObj['liter_id']];
            }
        }
        foreach ($result as $ind => $data) {
            $result[$ind] = $this->__openArrays($result[$ind]);
            if (isset($sql['has']['group_props'])) {
                $result[$ind] = $this->groupProp($result[$ind]);
            }
        }
        return ['data' => $result, 'page' => $dopRes['page']];
    }

    public function set($param, $is_one = true)
    {
        $table = 'publication';
        if ($is_one) {
            $param = [$param];
        }
        $map = $this->db->getMapDef($table);
        $q1 = 'insert into ' . $table . ' (' . implode(',', array_keys($map)) . ') values ';
        $f1 = false;
        $this->db->begin();
        try {
            foreach ($param as $oneObj) {
                $oneObj['id'] = (int)$oneObj['id'];
                $id = $oneObj['id'];
                if (!$id) {
                    continue;
                }
                if (!empty($this->getOnlyIds(['filter' => ['id' => $id]]))) {
                    $this->delPhotos($id, $oneObj['photos'] ?? []);
                    $this->delSchemes($id, $oneObj['schemes'] ?? []);
                    $q2 = 'update ' . $table . ' SET ' . $this->db->updateQueryData($map, $oneObj) .
                        ' where id=' . $this->db->escape($id);
                    $this->db->query($q2);
                } else {
                    $f1 = true;
                    $q1 .= $this->db->setQueryData($map, $oneObj) . ',';
                }
            }
            if ($f1) {
                $q1 = substr($q1, 0, -1);
                $this->db->query($q1);
            }
        } catch (\Exception $e) {
            $this->db->rollback();
            die($e->getMessage() . ' --//-- db rollback'.print_r($this->db->errorInfo(),true));
        }
        $this->db->commit();
    }

    private function delPhotos($id, $newPhotos)
    {
        $oldPhotos = json_decode($this->db->query('select photos from publication where id=' . $id)[0]['photos'], true);
        if (is_string($newPhotos)) {
            $newPhotos = json_decode($newPhotos, true);
        }
        $delPhotos = array_diff($oldPhotos, $newPhotos);
        foreach ($delPhotos as $onePhoto) {
            @unlink($this->wwwPath . $onePhoto);
        }
    }

    public function delSchemes($id, $newPhotos)
    {
        $oldPhotos = json_decode(
            $this->db->query('select schemes from publication where id=' . $id)[0]['schemes'],
            true
        );
        if (is_string($newPhotos)) {
            $newPhotos = json_decode($newPhotos, true);
        }
        $delPhotos = array_diff($oldPhotos, $newPhotos);
        foreach ($delPhotos as $onePhoto) {
            @unlink($this->wwwPath . $onePhoto);
        }
    }

    private function groupProp($data)
    {
        $include_rent = [
            'taxAddedValue',
            'utilities'
        ];
        $additional = [
            'parking',
            'accessFromObj',
            'security24',
            'cafe'
        ];
        $services = [
            'legalAddress',
            'repair',
            'internet'
        ];
        $data['include_rent'] = $data['additional'] = $data['services'] = [];
        $metaCheckbox = json_decode(
           "{\"checkbox\":{\"accessFromObj\":{\"name\":\"\u0414\u043e\u0441\u0442\u0443\u043f \u043d\u0430 \u043e\u0431\u044a\u0435\u043a\u0442\"},\"advertising\":{\"name\":\"\u0420\u0430\u0437\u043c\u0435\u0449\u0435\u043d\u0438\u0435 \u0440\u0435\u043a\u043b\u0430\u043c\u043d\u043e\u0439 \u0432\u044b\u0432\u0435\u0441\u043a\u0438 \u0438 \u0434\u0440. \u0440\u0435\u043a\u043b\u0430\u043c\u043d\u044b\u0435 \u0443\u0441\u043b\u0443\u0433\u0438\"},\"atm\":{\"name\":\"\u0411\u0430\u043d\u043a\u043e\u043c\u0430\u0442\"},\"cafe\":{\"name\":\"\u041a\u0430\u0444\u0435\"},\"cleaning\":{\"name\":\"\u041a\u043b\u0438\u043d\u0438\u043d\u0433\"},\"column\":{\"name\":\"\u041a\u043e\u043b\u043e\u043d\u043d\u044b\"},\"conditioning\":{\"name\":\"\u041a\u043e\u043d\u0434\u0438\u0446\u0438\u043e\u043d\u0438\u0440\u043e\u0432\u0430\u043d\u0438\u0435\"},\"conferenceHall\":{\"name\":\"\u041a\u043e\u043d\u0444\u0435\u0440\u0435\u043d\u0446-\u0437\u0430\u043b\"},\"electrification\":{\"name\":\"\u042d\u043d\u0435\u0440\u0433\u043e\u0441\u043d\u0430\u0431\u0436\u0435\u043d\u0438\u0435\"},\"farmacy\":{\"name\":\"\u0410\u043f\u0442\u0435\u043a\u0430\"},\"firefighting\":{\"name\":\"\u0421\u0438\u0441\u0442\u0435\u043c\u0430 \u043f\u043e\u0436\u0430\u0440\u043e\u0442\u0443\u0448\u0435\u043d\u0438\u044f\"},\"firstline\":{\"name\":\"\u041f\u0435\u0440\u0432\u0430\u044f \u043b\u0438\u043d\u0438\u044f\"},\"heating\":{\"name\":\"\u041e\u0442\u043e\u043f\u043b\u0435\u043d\u0438\u0435\"},\"height\":{\"name\":\"\u0412\u044b\u0441\u043e\u0442\u0430 \u043f\u043e\u0442\u043e\u043b\u043a\u0430\"},\"heightFreightTransport\":{\"name\":\"\u041f\u043e\u0434\u044a\u0435\u0437\u0434 \u0434\u043b\u044f \u0433\u0440\u0443\u0437\u043e\u0432\u043e\u0433\u043e \u0442\u0440\u0430\u043d\u0441\u043f\u043e\u0440\u0442\u0430\"},\"internet\":{\"name\":\"\u0418\u043d\u0442\u0435\u0440\u043d\u0435\u0442, \u0442\u0435\u043b\u0435\u0444\u043e\u043d\u0438\u044f\"},\"legalAddress\":{\"name\":\"\u042e\u0440\u0438\u0434\u0438\u0447\u0435\u0441\u043a\u0438\u0439 \u0430\u0434\u0440\u0435\u0441\"},\"liftCountCargo\":{\"name\":\"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u0433\u0440\u0443\u0437\u043e\u0432\u044b\u0445 \u043b\u0438\u0444\u0442\u043e\u0432\"},\"liftCountPass\":{\"name\":\"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u043f\u0430\u0441\u0441\u0430\u0436\u0438\u0440\u0441\u043a\u0438\u0445 \u043b\u0438\u0444\u0442\u043e\u0432\"},\"manufacture\":{\"name\":\"\u041f\u0440\u043e\u0438\u0437\u0432\u043e\u0434\u0441\u0442\u0432\u043e\"},\"office\":{\"name\":\"\u041e\u0444\u0438\u0441\"},\"panoramic\":{\"name\":\"\u041f\u0430\u043d\u043e\u0440\u0430\u043c\u043d\u044b\u0435 \u043e\u043a\u043d\u0430\"},\"parking\":{\"name\":\"\u041f\u0430\u0440\u043a\u0438\u043d\u0433\"},\"passSystem\":{\"name\":\"\u041f\u0440\u043e\u043f\u0443\u0441\u043a\u043d\u0430\u044f \u0441\u0438\u0441\u0442\u0435\u043c\u0430\"},\"productShop\":{\"name\":\"\u041f\u0440\u043e\u0434\u0443\u043a\u0442\u043e\u0432\u044b\u0439 \u043c\u0430\u0433\u0430\u0437\u0438\u043d\"},\"ramp\":{\"name\":\"\u041f\u0430\u043d\u0434\u0443\u0441\"},\"redevelopment\":{\"name\":\"\u041f\u0435\u0440\u0435\u043f\u043b\u0430\u043d\u0438\u0440\u043e\u0432\u043a\u0430\"},\"repair\":{\"name\":\"\u0420\u0435\u043c\u043e\u043d\u0442\"},\"security24\":{\"name\":\"\u041a\u0440\u0443\u0433\u043b\u043e\u0441\u0443\u0442\u043e\u0447\u043d\u0430\u044f \u043e\u0445\u0440\u0430\u043d\u0430\"},\"shop\":{\"name\":\"\u041c\u0430\u0433\u0430\u0437\u0438\u043d\"},\"shopwindow\":{\"name\":\"\u0412\u0438\u0442\u0440\u0438\u043d\u044b\"},\"subway\":{\"name\":\"\u041c\u0435\u0442\u0440\u043e\"},\"supermarket\":{\"name\":\"\u0421\u0443\u043f\u0435\u0440\u043c\u0430\u0440\u043a\u0435\u0442\"},\"taxAddedValue\":{\"name\":\"\u041d\u0414\u0421\"},\"utilities\":{\"name\":\"\u041a\u043e\u043c\u043c\u0443\u043d\u0430\u043b\u044c\u043d\u044b\u0435 \u0443\u0441\u043b\u0443\u0433\u0438\"},\"ventilation\":{\"name\":\"\u0412\u0435\u043d\u0442\u0438\u043b\u044f\u0446\u0438\u044f\"},\"videovision\":{\"name\":\"\u0412\u0438\u0434\u0435\u043e\u043d\u0430\u0431\u043b\u044e\u0434\u0435\u043d\u0438\u0435\"},\"warehouse\":{\"name\":\"\u0421\u043a\u043b\u0430\u0434\"},\"window\":{\"name\":\"\u041d\u0430\u043b\u0438\u0447\u0438\u0435 \u043e\u043a\u043e\u043d\"}},\"input\":{\"columnPitch\":{\"name\":\"\u0428\u0430\u0433 \u043a\u043e\u043b\u043e\u043d\u043d, \u043c\u043c\"},\"comment\":{\"name\":\"\u041d\u0435\u043f\u0443\u0431\u043b\u0438\u043a\u0443\u0435\u043c\u044b\u0439 \u043a\u043e\u043c\u043c\u0435\u043d\u0442\u0430\u0440\u0438\u0439\"},\"craneBeam\":{\"name\":\"\u041a\u0440\u0430\u043d-\u0431\u0430\u043b\u043a\u0430\"},\"date\":{\"name\":\"\u0414\u0430\u0442\u0430\"},\"description\":{\"name\":\"\u041e\u043f\u0438\u0441\u0430\u043d\u0438\u0435\"},\"floorName\":{\"name\":\"\u042d\u0442\u0430\u0436 \u0442\u0435\u043a\u0441\u0442\u043e\u043c\"},\"gatesCount\":{\"name\":\"\u041a\u043e\u043b\u0438\u0447\u0435\u0441\u0442\u0432\u043e \u0432\u043e\u0440\u043e\u0442\"},\"pol\":{\"name\":\"\u041f\u043e\u043b\"},\"polLoad\":{\"name\":\"\u041d\u0430\u0433\u0440\u0443\u0437\u043a\u0430 \u043d\u0430 \u043f\u043e\u043b, \u043a\u0433\"},\"rate\":{\"name\":\"\u0421\u0442\u0430\u0432\u043a\u0430\"},\"sqr\":{\"name\":\"\u041f\u043b\u043e\u0449\u0430\u0434\u044c\"},\"wall\":{\"name\":\"\u0421\u0442\u0435\u043d\u044b\"}},\"select\":{\"condition\":{\"name\":\"\u0421\u043e\u0441\u0442\u043e\u044f\u043d\u0438\u0435 \u043f\u043e\u043c\u0435\u0449\u0435\u043d\u0438\u044f\",\"variables\":[{\"id\":\"23\",\"name\":\"\u0422\u0440\u0435\u0431\u0443\u0435\u0442 \u0440\u0435\u043c\u043e\u043d\u0442\u0430\"},{\"id\":\"34\",\"name\":\"\u041e\u0442\u043b\u0438\u0447\u043d\u043e\u0435\"},{\"id\":\"35\",\"name\":\"\u0425\u043e\u0440\u043e\u0448\u0435\u0435\"}],\"default\":\"23\"},\"destination\":{\"name\":\"\u041d\u0430\u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435\",\"variables\":[{\"id\":\"38\",\"name\":\"\u041e\u0444\u0438\u0441\"},{\"id\":\"39\",\"name\":\"\u0422\u043e\u0440\u0433\u043e\u0432\u043b\u044f\"},{\"id\":\"40\",\"name\":\"\u041f\u0440\u043e\u0438\u0437\u0432\u043e\u0434\u0441\u0442\u0432\u043e\"},{\"id\":\"41\",\"name\":\"\u0421\u043a\u043b\u0430\u0434\"},{\"id\":\"42\",\"name\":\"\u0417\u0435\u043c\u0435\u043b\u044c\u043d\u044b\u0439 \u0443\u0447\u0430\u0441\u0442\u043e\u043a\"},{\"id\":\"43\",\"name\":\"\u041e\u0431\u0449. \u043f\u0438\u0442\u0430\u043d\u0438\u0435\"}],\"default\":\"38\"},\"layout\":{\"name\":\"\u041f\u043b\u0430\u043d\u0438\u0440\u043e\u0432\u043a\u0430\",\"variables\":[{\"id\":\"22\",\"name\":\"\u041a\u0430\u0431\u0438\u043d\u0435\u0442\u043d\u0430\u044f\"},{\"id\":\"32\",\"name\":\"\u041e\u0444\u0438\u0441\u043d\u0430\u044f\"},{\"id\":\"33\",\"name\":\"\u041f\u0440\u043e\u0438\u0437\u0432\u043e\u0434\u0441\u0442\u0432\u0435\u043d\u043d\u0430\u044f\"}],\"default\":\"22\"},\"typeOfContract\":{\"name\":\"\u0422\u0438\u043f \u0434\u043e\u0433\u043e\u0432\u043e\u0440\u0430\",\"variables\":[{\"id\":\"24\",\"name\":\"\u0417\u0434\u0430\u043d\u0438\u0435\"},{\"id\":\"36\",\"name\":\"\u0423\u0447\u0430\u0441\u0442\u043e\u043a\"},{\"id\":\"37\",\"name\":\"\u041b\u0435\u0441\"}],\"default\":\"24\"}}}",
            true
        )['checkbox'];
        foreach ($include_rent as $oneitem) {
            if (isset($data[$oneitem]) && $data[$oneitem] && isset($metaCheckbox[$oneitem])) {
                $data['include_rent'][] = $metaCheckbox[$oneitem]['name'];
            }
        }
        foreach ($services as $oneitem) {
            if (isset($data[$oneitem]) && $data[$oneitem] && isset($metaCheckbox[$oneitem])) {
                $data['services'][] = $metaCheckbox[$oneitem]['name'];
            }
        }
        foreach ($additional as $oneitem) {
            if (isset($data[$oneitem]) && $data[$oneitem] && isset($metaCheckbox[$oneitem])) {
                $data['additional'][] = $metaCheckbox[$oneitem]['name'];
            }
        }

        return $data;
    }

    public function getOnlyIds($param)
    {
        $table = 'publication';
        $sql = $this->db->selectValue(
            (empty($param['select']) ? [] : $param['select']),
            empty($param['filter']) ? [] : $param['filter'],
            $this->db->getMapAs($table),
            empty($param['additional']) ? [] : $param['additional']
        );
        $q = "SELECT id FROM " . $table . $sql['where'];
        $res = $this->db->query($q);
        $ids = [];
        foreach ($res as $oneId) {
            $ids[] = $oneId['id'];
        }
        return $ids;
    }

    public function dump($data)
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

    private function alert($msg)
    {
        echo '<script>alert("' . $msg . '")</script>';
    }

    public function setPage($page = [])
    {
        if (!empty($page)) {
            foreach ($this->page as $k => $v) {
                if (isset($page[$k])) {
                    $this->page[$k] = $page[$k];
                }
            }
            if (!is_array($this->page['order'])) {
                $this->page['order'] = [$this->page['order'], 'ASC'];
            }
            $this->page['order'][0] = trim($this->page['order'][0]);
            if ($this->page['order'][0] == '') {
                $this->page['order'][0] = 'id';
            }
        }
    }

    public function queryPage(&$q, &$resultDop)
    {
        $resultDop['page'] = [];
        $limit = '';
        if ($this->page['current'] != 'all') {
            $sq = "select count(*) as count from (" . $q . ") z ";
            $res = $this->db->query($sq);
            $count = (count($res) == 0 ? 0 : $res[0]['count']) + 0;
            $pages = ceil($count / $this->page['row']);
            $pages = $pages == 0 ? 1 : $pages;
            $this->page['current'] = mb_strtolower($this->page['current']) == 'last' ||
            (int)$this->page['current'] > $pages
                ? $pages
                : ((int)$this->page['current'] < 1
                    ? 1
                    : (int)$this->page['current']);
            $st = ($this->page['current'] - 1) * $this->page['row'];
            $limit = " limit " . $st . "," . $this->page['row'];

            $resultDop['page']['allPage'] = $pages;
            $resultDop['page']['itemCount'] = $count;
            $resultDop['page']['current'] = $this->page['current'];
            $resultDop['page']['row'] = $this->page['row'];
        }
        $order = " ORDER BY " . $this->page['order'][0] . " " . $this->page['order'][1];
        $resultDop['page']['order'] = $this->page['order'];
        $q = "select * from (" . $q . ") z " . $order . $limit;
    }

    private function __openArrays($data)
    {
        foreach ($data as $ind => $prop) {
            if (is_string($prop) && (strpos($prop, '}') || strpos($prop, ']'))) {
                $data[$ind] = json_decode($prop, true);
            }
        }
        if (!isset($data['photos'])) {
            $data['photos'] = [];
        }
        if (!isset($data['schemes'])) {
            $data['schemes'] = [];
        }
        return $data;
    }

    public function jsonToBase($data)
    {
        $publications = $objects = $liters = [];
        $metaQ = $this->db->query('select * from meta');
        $meta = [];
        foreach ($metaQ as $oneMeta) {
            $meta[$oneMeta['gist']] = $oneMeta['data']['select'] ?? [];
        }
        foreach ($data as $onePubl) {
            $this->prepareDataForDB($publications, $objects, $liters, $onePubl, $meta);
        }
        $fl_was = false;
        if (!empty($objects)) {
            (new Objects())->set($objects, false);
            $fl_was = true;
        }
        if (!empty($liters)) {
            (new Liters())->set($liters, false);
            $fl_was = true;
        }
        if (!empty($publications)) {
            $this->set($publications, false);
            $fl_was = true;
        }
        if ($fl_was) {
            (new Site())->setMainProps('БЦ');
        } else {
            throw new \Exception('empty write data');
        }
    }

    public function delPublications($id)
    {
        $this->db->query('delete from publication where id=' . $this->db->escape($id));
    }

    ///по метаданным подставляет значения для пропов, которые являются селектами
    private function reinitProps($data, $meta)
    {
        foreach ($data as $nameProp => $valProp) {
            if (isset($meta[$nameProp])) {
                foreach ($meta[$nameProp]['variables'] as $val) {
                    if ($val['id'] == $valProp) {
                        $data[$nameProp] = $val['name'];
                        break;
                    }
                }
            }
        }
        return $data;
    }








    //ToDo delete
    public function prepareDataForDB(&$publications, &$objects, &$liters, $data, $meta)
    {
        if (isset($data['liter']) && is_array($data['liter'])) {
            $tmp = $data['liter'];
            if (!isset($liters[$data['liter']['id']])) {
                $liters[$data['liter']['id']] = $tmp;
            }
            $tmp['id'] = (int)$tmp['id'];
            $data['liter_id'] = $tmp['id'];
            unset($data['liter']);
        } elseif (isset($data['liter'])) {
            $data['liter_id'] = (int)$data['liter'];
        }
        if (isset($data['object']) && is_array($data['object'])) {
            $tmp = $data['object'];
            if (isset($tmp['properties'])) {
                $tmp['properties'] = $this->reinitProps($tmp['properties'], $meta['objects']);
                $tmp = array_merge($tmp, $tmp['properties']);
                unset($tmp['properties']);
            }
            if (!isset($objects[$data['object']['id']])) {
                $objects[$data['object']['id']] = $tmp;
            }
            $tmp['id'] = (int)$tmp['id'];
            $data['object_id'] = $tmp['id'];
            unset($data['object']);
        } elseif (isset($data['object'])) {
            $data['object_id'] = (int)$data['object'];
        }
        if (isset($data['properties'])) {
            $data['properties'] = $this->reinitProps($data['properties'], $meta['publications']);
            $data = array_merge($data, $data['properties']);
            unset($data['properties']);
        }
        if (!empty($data['schemes'])) {
            $dest = $this->wwwPath.'upload/photos/' . $data['id'].'/';
            @mkdir($dest, 0755, true);
            $schemes = $data['schemes'];
            $data['schemes'] = [];
            foreach ($schemes as $oneScheme) {
                $oneScheme = $oneScheme['original'];
                $filename = pathinfo($oneScheme)['filename'] . '.png';
                if (!file_exists($dest . $filename)) {
                    copy($this->url.$oneScheme, $dest . $filename);
                }
                $data['schemes'][] = 'upload/photos/' . $data['id'] . '/' . $filename;
            }
            $data['schemes'] = json_encode($data['schemes']);
        }
        if (isset($data['id'])) {
            $data['id'] = (int)$data['id'];
        }
        if (!empty($data['floor_names'])) {
            $tmp = $data['floor_names'];
            $data['floor_names'] = [];
            foreach ($tmp as $oneName) {
                $data['floor_names'][] = $oneName['name'];
            }
        }
        $publications[$data['id']] = $data;
    }
}
