<?php

include_once("../db.inc");

class daemon_secret {

    protected $_id;
    protected $_db;
    protected $_cookie;
    protected $_run = 1;

    public function __construct($id) {
        global $db;
        $this->_id = (int) $id;
        $this->_db = $db;
        $this->loop();
    }

    public function valid() {
        $row = array();
        $stmt = $this->_db->query("SELECT * FROM `12306` WHERE `id` = {$this->_id}");
        if ($stmt) {
            $stmt->setFetchMode(PDO::FETCH_NAMED);
            $row = $stmt->fetch();
        }

        if (empty($row["cookie"])) return false;
        $this->_cookie = $row["cookie"];
        print "cookie ok!\n";

        if (empty($row["secret"])) return false;
        print "secret ok!\n";

        return $row;

    }

    public function exec(array $row) {

        return false;

    }

    public function loop() {
        while ($this->_run) {
            print "loop ~\n";
            // polling
            $row = $this->valid();
            if ($row) {
            }
            sleep(1);
        }
    }

}

if ($argc>1) {
    new daemon_secret($argv[1]);
} else {
    print "Usage: {$argv[0]} [job_id]\n";
}

