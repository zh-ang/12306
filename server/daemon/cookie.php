<?php

include_once("../db.inc");

class daemon_rand {

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

    /* {{{ protected function _checkRandCodeAnsyn($randCode, $REPEAT_SUBMIT_TOKEN)  */
    protected function _checkRandCodeAnsyn($randCode, $REPEAT_SUBMIT_TOKEN) {
        $arrData = array();
        $arrData["randCode"]            = $randCode;
        $arrData["rand"]                = "randp";
        $arrData["_json_att"]           = "";
        $arrData["REPEAT_SUBMIT_TOKEN"] = $REPEAT_SUBMIT_TOKEN;

        $arrTemp = array();
        foreach ($arrData as $strKey => $strVal) {
            $arrTemp[] = urlencode($strKey)."=".urlencode($strVal);
        }
        $strData = join("&", $arrTemp);

        print "post ".$strData."\n";

        $h = curl_init();
        curl_setopt($h, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($h, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($h, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($h, CURLOPT_HEADER, 0);
        curl_setopt($h, CURLOPT_VERBOSE, 0);
        curl_setopt($h, CURLOPT_URL, "https://kyfw.12306.cn/otn/passcodeNew/checkRandCodeAnsyn");
        curl_setopt($h, CURLOPT_REFERER, "https://kyfw.12306.cn/otn/confirmPassenger/initDc");
        curl_setopt($h, CURLOPT_COOKIE, $this->_cookie);
        curl_setopt($h, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($h, CURLOPT_TIMEOUT, 20);
        curl_setopt($h, CURLOPT_POSTFIELDS, $strData);
        $output = curl_exec($h);
        $code = curl_getinfo($h, CURLINFO_HTTP_CODE);
        curl_close($h);

        if ($code != 200) {
            $this->_db->exec("UPDATE `12306` SET `error` = 'HTTP{$code} on ".__METHOD__."', `error_time` = NOW() WHERE `id` = {$this->_id}");
        }

        print "output ".$output."\n";

        return $output;
    }
    /* }}} */

    /* {{{ protected function _getPassCodeNew()  */
    protected function _getPassCodeNew() {

        $h = curl_init();
        curl_setopt($h, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($h, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($h, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($h, CURLOPT_HEADER, 0);
        curl_setopt($h, CURLOPT_VERBOSE, 0);
        curl_setopt($h, CURLOPT_URL, "https://kyfw.12306.cn/otn/passcodeNew/getPassCodeNew?module=passenger&rand=randp");
        curl_setopt($h, CURLOPT_COOKIE, $this->_cookie);
        curl_setopt($h, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($h, CURLOPT_TIMEOUT, 20);
        $output = curl_exec($h);
        $code = curl_getinfo($h, CURLINFO_HTTP_CODE);
        curl_close($h);

        if ($code != 200) {
            $this->_db->exec("UPDATE `12306` SET `error` = 'HTTP{$code} on ".__METHOD__."', `error_time` = NOW() WHERE `id` = {$this->_id}");
        }

        return $output;

    }
    /* }}} */

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

        $this->_status = (array) json_decode($row["code_status"], true);
        if (empty($this->_status)) {
            $this->_status["last"] = "";
            $this->_status["count"] = 0;
            $this->_status["ok"] = false;
        }

        return $row;

    }

    public function get() {

        $output = $this->_getPassCodeNew();
        if ($output) {
            $data = "data:image/png;base64,".base64_encode($output);
            $this->_db->exec("UPDATE `12306` SET `image` = '".addslashes($data)."', `code` = '', `code_status` = '', `code_time` = NOW() WHERE `id` = {$this->_id}");
            return true;
        }

        return false;

    }

    public function verify($code, $token) {
        $output = $this->_checkRandCodeAnsyn($code, $token);
        if ($output) {
            $arrOutput = json_decode($output, true);
            if ($arrOutput && $arrOutput["status"] == true && $arrOutput["data"] == "Y") {
                return true;
            }
        }
        return false;
    }

    public function erase() {
        $this->_db->exec("UPDATE `12306` SET `image` = '', `code` = '', `code_status` = '', `code_time` = '0000-00-00 00:00:00' WHERE `id` = {$this->_id}");
    }

    public function loop() {
        while ($this->_run) {
            print "loop ~\n";
            // polling
            $row = $this->valid();
            $ext = (array) json_decode($row["info_ext"], true);
            $token = isset($ext["REPEAT_SUBMIT_TOKEN"]) ? $ext["REPEAT_SUBMIT_TOKEN"] : "";
            if ($row) {
                if ($row["image"]) {
                    if (strlen($row["code"]) == 4) {
                        if ($this->verify($row["code"], $token) == true) {
                            $this->_db->exec("UPDATE `12306` SET `code_time` = NOW() WHERE `id` = {$this->_id}");
                            print "verified!\n";
                        } else {
                            print "fail test.\n";
                            $this->erase();
                        }
                    }
                } else {
                    $this->get();
                    print "got new ~\n";
                }
            }
            sleep(1);
        }
    }

}

if ($argc>1) {
    new daemon_rand($argv[1]);
} else {
    print "Usage: {$argv[0]} [job_id]\n";
}

