<?php


namespace classes\models;

use classes\database\Database;

class Site
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getMainProps()
    {
        $this->db = Database::getInstance();
        $q = 'select * from main_props';
        $res = $this->db->query($q) ?? [];
        return $this->renameProps('БЦ', $res);
    }


    private function renameProps($typeSite, $props)
    {
        $data = [];
        if ($typeSite == 'БЦ') {
            $change = [
                'liftCountCargo' => [3, 'Грузовые лифты'],
                'liftCountPass' => [3, 'Пассажирские лифты'],
                'parking' => [1, 'Паркинг'],
                'security24' => [1, 'Круглосуточная охрана'],
                'accessFromObj' => [1, 'Доступ на объект'],
                'cafe' => [1, 'Кафе'],
                'heightFreightTransport' => [1, 'Грузовой подъезд'],
            ];
            foreach ($props as $key => $oneProp) {
                if (isset($change[$oneProp['name']])) {
                    $data[$change[$oneProp['name']][1]] = [$change[$oneProp['name']][0], $oneProp['value']];
                } else {
                    $data[$oneProp['name']] = $oneProp['value'];
                }
            }
        }
        return $data;
    }

    public function setMainProps($siteType)
    {
        $table = 'main_props';
        $this->db->query('delete from ' . $table);
        $props = [];
        if ($siteType == 'БЦ') {
            $props = [
                'liftCountCargo' => 0,
                'liftCountPass' => 0,
                'heightFreightTransport' => 0,
                'address' => '',
                'cafe' => 0,
                'parking' => 0,
                'accessFromObj' => 0,
                'security24' => 0,
                'min_rate' => 0
            ];
            $liters = (new Liters())->getList([
                'select' => [
                    'id',
                    'liftCountCargo',
                    'liftCountPass',
                    'heightFreightTransport'
                ]
            ]);
            foreach ($liters as $oneObj) {
                $props['liftCountCargo'] += (int)$oneObj['liftCountCargo'];
                $props['liftCountPass'] += (int)$oneObj['liftCountPass'];
                if ((int)$oneObj['heightFreightTransport'] > 0) {
                    $props['heightFreightTransport'] = 1;
                }
            }
            $objects = (new Objects())->getList([
                'select' => [
                    'id',
                    'cafe',
                    'parking',
                    'accessFromObj',
                    'security24',
                    'address'
                ]
            ]);
            foreach ($objects as $oneObj) {
                if ($props['address'] == '') {
                    $addr = $oneObj['address'];
                    if (!empty($addr)) {
                        $props['address'] = ($addr['city']['name'] ?? '-') .
                            ', ул.' . ($addr['street']['name'] ?? '-') .
                            ', д.' . ($addr['house']['name'] ?? '-');
                    }
                }
                if ($oneObj['cafe']) {
                    $props['cafe'] = 1;
                }
                if ($oneObj['parking']) {
                    $props['parking'] = 1;
                }
                if ($oneObj['accessFromObj']) {
                    $props['accessFromObj'] = 1;
                }
                if ($oneObj['security24']) {
                    $props['security24'] = 1;
                }
            }
            $props['min_rate'] = $this->db->query('select min(rate) as rate from publication')[0]['rate'] ?? 0;
        }
        if (!empty($props)) {
            $q = 'insert into ' . $table . ' (name,value) values ';
            foreach ($props as $name => $val) {
                $q .= '(' . $this->db->escape($name) . ',' . $this->db->escape($val) . '),';
            }
            $q = substr($q, 0, -1);
            $this->db->query($q);
        }
    }

    public function setMeta($gist, $data)
    {
        $this->db->query(
            "insert into meta (gist, data) VALUES (" .
            $this->db->escape($gist) . "," .
            $this->db->escape(json_encode($data)) .
            ")"
        );
    }

    public function setAnalytic($key, $action, $data)
    {
        $this->db->query(
            "insert into analytic (key, action, data, date) VALUES (" .
            $this->db->escape($key) . "," .
            $this->db->escape($action) . "," .
            $this->db->escape(json_encode($data, 271)) . "," .
            $this->db->escape(time()) .
            ")"
        );
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
}
