<?php


namespace classes\models;

use classes\database\Database;

class Objects
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getList($param)
    {
        $table = 'object';
        $sql = $this->db->selectValue(
            (empty($param['select']) ? [] : $param['select']),
            empty($param['filter']) ? [] : $param['filter'],
            $this->db->getMapAs($table),
            empty($param['additional']) ? [] : $param['additional']
        );
        $q = "SELECT " . $sql['select'] . " FROM " . $table . $sql['where'];
        $res = $this->db->query($q);
        $result = $res;
        if (isset($sql['has']['keys'])) {
            $result = [];
            foreach ($res as $oneObj) {
                $result[$oneObj['id']] = $oneObj;
            }
        }
        foreach ($result as $ind => $data) {
            $result[$ind] = $this->__openArrays($data);
        }
        return $result;
    }

    public function set($param, $is_one = true)
    {
        $table = 'object';
        if ($is_one) {
            $param = [$param];
        }
        $map = $this->db->getMapDef($table);
        $q1 = 'insert into ' . $table . ' (' . implode(',', array_keys($map)) . ') values ';
        $f1 = false;
        $this->db->begin();
        try {
            foreach ($param as $oneObj) {
                $id = $oneObj['id'];
                if (!$id) {
                    continue;
                }
                if (!empty($this->getOnlyIds(['filter' => ['id' => $id]]))) {
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
            die($e->getMessage() . ' --//-- db rollback');
        }
        $this->db->commit();
    }

    public function getOnlyIds($param)
    {
        $table = 'object';
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

    private function __openArrays($data)
    {
        foreach ($data as $ind => $prop) {
            if (is_string($prop) && (strpos($prop, '}') || strpos($prop, ']'))) {
                $data[$ind] = json_decode($prop, true);
            }
        }
        return $data;
    }
}
