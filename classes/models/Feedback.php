<?php


namespace classes\models;

use classes\database\Database;

class Feedback
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function sendFeedback($data)
    {
        $q = "insert into feedback_data (" . implode(',', array_keys($data)) . ",date) values (";
        foreach ($data as $prop) {
            $q.=$this->db->escape($prop).',';
        }
        $q.=$this->db->escape(date('d.m.Y H:i:s')).')';
        $this->db->query($q);
        return 'Форма отправлена.';
    }
}
