<?php

namespace classes;

use classes\database\Database;
use classes\database\Singleton;
use classes\models\Feedback;
use classes\models\Publications;
use classes\models\Site;

class FrontController
{
    use Singleton;

    /////////////////////////////// public ////////////////////////////////////////////
    public function randomPubl($action, $countView)
    {
        $action=0;
        $publ = new Publications();
        try {
            $ids = $publ->getOnlyIds(['filter' => ['action' => $action]]);
        } catch (\Exception $e) {
            $this->alert('Простите, произошла ошибка: ' . $e->getMessage());
        }
        $noActionWas = false;
        $countNeed = $countView - count($ids);
        if ($countNeed > 0 && $action) {
            $noActionPubl = $this->randomPubl(0, $countNeed);
            $countView = $countView - $countNeed;
            $noActionWas = true;
        } elseif ($countNeed > 0) {
            $countView = $countView - $countNeed;
        }
        if (empty($ids)) {
            return [];
        }
        $idsRand = array_rand($ids, $countView);
        $idsForQuery = [];
        for ($i = 0; $i < $countView; $i++) {
            $idsForQuery[] = $ids[$idsRand[$i]];
        }
        ///list
        $param = ['filter' => ['&id' => $idsForQuery]];
        $param['select'] = ['floor_names', 'name', 'sqr', 'rate', 'pred_rate', 'id','photos'];
        if (!$noActionWas) {
            $param['filter']['action'] = $action;
        }
        $param['additional'] = ['liter'];
        $param['select'][] = 'liter_id';
        try {
            $res = $publ->getList($param);
        } catch (\Exception $e) {
            $this->alert('Простите, произошла ошибка: ' . $e->getMessage());
            return [];
        }
        ///
        $res = $res['data'];
        if ($noActionWas) {
            $res = array_merge($res, $noActionPubl['data']);
        }
        foreach ($res as $ind => $oneRes) {
            $res[$ind]['name'] = substr($oneRes['name'], 0, 8);
            if (!empty($oneRes['floor_names'])) {
                $res[$ind]['floor_names'] = str_replace(['этаж'], '', $oneRes['floor_names'][0]);
            } else {
                $res[$ind]['floor_names'] = '-';
            }
            if (empty($oneRes['photos'])) {
                $res[$ind]['photos'][0] = rand(1,100)>50?"img/image1_web_ex.jpeg":"img/image2_web_ex.jpeg";
            }
        }
        return $res;
    }

    public function publList($action, &$page, $filter)
    {
        $publ = new Publications();
        ///list
        $param['select'] = ['floor_names', 'name', 'sqr', 'rate', 'pred_rate', 'id', 'photos','description'];
        //        $param['filter']['action'] = $action;
        $param['filter'] = $filter;
        $param['additional'] = ['liter'];
        $param['select'][] = 'liter_id';
        try {
            $publ->setPage($page);
            $res = $publ->getList($param);
        } catch (\Exception $e) {
            $this->alert('Простите, произошла ошибка: ' . $e->getMessage());
            return [];
        }
        ///
        $page = $res['page'];
        $res = $res['data'];
        foreach ($res as $ind => $oneRes) {
            if (!empty($oneRes['floor_names'])) {
                $res[$ind]['floor_names'] = str_replace(
                    ['этаж'],
                    '',
                    implode(',', $oneRes['floor_names'])
                );
            } else {
                $res[$ind]['floor_names'] = '-';
            }
            if (empty($oneRes['photos'])) {
                $res[$ind]['photos'][] = "img/image2_web_ex.jpeg";
            }
        }
        return $res;
    }

    public function getMainProps()
    {
        $site = new Site();
        try {
            return $site->getMainProps();
        } catch (\Exception $e) {
            $this->alert('Простите, произошла ошибка: ' . $e->getMessage());
            return [];
        }
    }

    public function getPubl($id)
    {
        if (!isset($_REQUEST['id']) || $_REQUEST['id'] <= 0) {
            die('Не выбран объект');
        }
        $publ = new Publications();
        $param['additional'] = ['liter', 'object', 'dest', 'group_props'];
        $param['filter']=['id'=>$_REQUEST['id']];
        try {
            $res = $publ->getList($param);
        } catch (\Exception $e) {
            $this->alert('Простите, произошла ошибка: ' . $e->getMessage());
            return [];
        }
        $res = $res['data'][0];
        if (empty($res['photos'])) {
            if (defined('IS_TEST_DATABASE') && IS_TEST_DATABASE) {
                $res['photos'][] = getenv("API_HOST_NAME")."upload/images/image2_web_ex.jpeg";
                $res['photos'][] = getenv("API_HOST_NAME")."upload/images/image1_web_ex.jpeg";
            } else {
                $res['photos'][] = "img/image2_web_ex.jpeg";
                $res['photos'][] = "img/image1_web_ex.jpeg";
            }
        }
        if (empty($res['schemes'])) {
            if (defined('IS_TEST_DATABASE') && IS_TEST_DATABASE) {
                $res['schemes'][] = getenv("API_HOST_NAME")."upload/images/sheme_ex.png";
            } else {
                $res['schemes'][] = "img/sheme_ex.png";
            }
        }
        return $res;
    }

    public function checkRequest($data)
    {
        $result = [];
        if (isset($data['feedback'])) {
            $feedback = [
                'name' => $data['name'] ?? '',
                'phone' => $data['phone'] ?? '',
                'email' => $data['email'] ?? '',
                'city' => $data['city'] ?? '',
                'org' => $data['org'] ?? '',
            ];
            try {
                $this->alert((new Feedback())->sendFeedback($feedback));
            } catch (\Exception $e) {
                $this->alert('Простите, произошла ошибка: ' . $e->getMessage());
            }
        }
        if (isset($data['filter'])) {
            $result['filter'] = $this->setFilter($data);
        }
        $result['page'] = [
            'current' => $data['PAGE_1'] ?? 1,
            'row' => $data['PAGE_row'] ?? 12,
            'order' => [$data['PAGE_order'] ?? 'id', $data['PAGE_order_as'] ?? 'ASC']
        ];
        return $result;
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

    /////////////////////////////// private ////////////////////////////////////////////
    private function setFilter($data)
    {
        $db = Database::getInstance();
        $map = $db->getMapAs('publication');
        $filter = ['query' => [], 'form' => []];
        foreach ($map as $oneProp) {
            if (isset($data[$oneProp]) && !empty($data[$oneProp])) {
                if ($oneProp == 'name') {
                    $filter['query']['%name'] = $data[$oneProp];
                } elseif (is_array($data[$oneProp]) &&
                    isset($data[$oneProp]['from']) && isset($data[$oneProp]['to'])) {
                    if (!empty($data[$oneProp]['from'])) {
                        $filter['query']['>=' . $oneProp] = $data[$oneProp]['from'];
                    }
                    if (!empty($data[$oneProp]['to'])) {
                        $filter['query']['<=' . $oneProp] = $data[$oneProp]['to'];
                    }
                } else {
                    $filter['query'][$oneProp] = $data[$oneProp];
                }
                $filter['form'][$oneProp] = $data[$oneProp];
            }
        }
        return $filter;
    }

    private function alert($msg)
    {
        echo '<script>alert("' . $msg . '")</script>';
    }
}
