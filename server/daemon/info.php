<?php

include_once("../db.inc");

class daemon_info {

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

    /* {{{ protected function _initDc()  */
    protected function _initDc() {

        $h = curl_init();
        curl_setopt($h, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($h, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($h, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($h, CURLOPT_HEADER, 0);
        curl_setopt($h, CURLOPT_VERBOSE, 0);
        curl_setopt($h, CURLOPT_URL, "https://kyfw.12306.cn/otn/confirmPassenger/initDc");
        curl_setopt($h, CURLOPT_COOKIE, $this->_cookie);
        curl_setopt($h, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($h, CURLOPT_TIMEOUT, 20);
        $output = curl_exec($h);
        $code = curl_getinfo($h, CURLINFO_HTTP_CODE);
        curl_close($h);

        if ($code != 200) {
            $this->_db->exec("UPDATE `12306` SET `error` = 'HTTP{$code}', `error_time` = NOW() WHERE `id` = {$this->_id}");
        }

        return $output;

    }
    /* }}} */

    /* {{{ protected function _submitOrderRequest($secretStr, $train_date, $back_train_date, $tour_flag, $purpose_codes, $query_from_station_name, $query_to_station_name)  */
    protected function _submitOrderRequest($secretStr, $train_date, $back_train_date, $tour_flag, $purpose_codes, $query_from_station_name, $query_to_station_name) {

        $arrData = array();
        $arrData["secretStr"]               = $secretStr;
        $arrData["train_date"]              = $train_date;
        $arrData["back_train_date"]         = $back_train_date;
        $arrData["tour_flag"]               = $tour_flag;
        $arrData["purpose_codes"]           = $purpose_codes;
        $arrData["query_from_station_name"] = $query_from_station_name;
        $arrData["query_to_station_name"]   = $query_to_station_name;

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
        curl_setopt($h, CURLOPT_URL, "https://kyfw.12306.cn/otn/leftTicket/submitOrderRequest");
        curl_setopt($h, CURLOPT_REFERER, "https://kyfw.12306.cn/otn/leftTicket/init");
        curl_setopt($h, CURLOPT_COOKIE, $this->_cookie);
        curl_setopt($h, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($h, CURLOPT_TIMEOUT, 20);
        curl_setopt($h, CURLOPT_POSTFIELDS, $strData);
        $output = curl_exec($h);
        $code = curl_getinfo($h, CURLINFO_HTTP_CODE);
        curl_close($h);

        if ($code != 200) {
            $this->_db->exec("UPDATE `12306` SET `error` = 'HTTP{$code}', `error_time` = NOW() WHERE `id` = {$this->_id}");
        }

        print "output ".$output."\n";

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

        if (empty($row["secret"])) return false;
        print "secret ok!\n";

        return $row;

    }

    public function register($row) {
        $secret = $row["secret"];
        // 2014-01-28#00#T337#12:50#01:10#240000T33700#BXP#HKN#14:00#北京西#汉口#01#14#10152530861015250000#P3#1389274768530#BA8AD194629CFA6B96637F8B10E14CB5E88947491A3977218768B4FD
        list (
            $train_date, // 2014-01-28
            $purpose_codes, // 00
            , // T337
            , // 12:50
            , // 01:10
            $train_code,// 240000T33700
            $from_station, // BJP
            $to_station, // HKN
            , // 14:00
            $from_station_name, //北京西
            $to_station_name, //汉口
            ,
            ,
            , // ...
        ) = explode("#", $secret);

        $output = $this->_submitOrderRequest(base64_encode($secret), $train_date, $train_date, "dc", "ADULT", $from_station_name, $to_station_name);

        if ($output) {
            $arrRet = json_decode($output, true);
            if ($arrRet && $arrRet["status"] == true) {
                // success to register
                $this->_db->exec("UPDATE `12306` SET `info` = '', `info_time` = NOW() WHERE `id` = {$this->_id}");
                return true;
            }
        }

        return false;
    }

    public function exec(array $row) {
        $last = strtotime($row["info_time"]);
        if ($last < time() - 30) {
            // register
            $ret = $this->register($row);
            if ($ret == false) {
                return;
            }
        }

        $html = $this->_initDc();

        $globalRepeatSubmitToken = null;
        if (preg_match("/var globalRepeatSubmitToken = '(\w+)';/", $html, $match)) {
            $globalRepeatSubmitToken = $match[1];
        }

        $ticketInfoForPassengerForm = null;
        if (preg_match("/var ticketInfoForPassengerForm=([^\s]+);/", $html, $match)) {
            $ticketInfoForPassengerForm= $match[1];
        }

        if ($globalRepeatSubmitToken && $ticketInfoForPassengerForm) {
            $arrExt = (array) json_decode($row["info_ext"], true);
            $arrExt["REPEAT_SUBMIT_TOKEN"] = $globalRepeatSubmitToken;

            $this->_db->exec("UPDATE `12306` SET `info` = '".addslashes($ticketInfoForPassengerForm)."', `info_ext` = '".addslashes(json_encode($arrExt))."', `info_time` = NOW() WHERE `id` = {$this->_id}");

            return true;
        }

        return false;

    }

    public function loop() {
        while ($this->_run) {
            print "loop ~\n";
            // polling
            $row = $this->valid();
            if ($row["info"]) {
                print "info exist!\n";
            } else {
                $this->exec($row);
            }
            sleep(1);
        }
    }

}

if ($argc>1) {
    new daemon_info($argv[1]);
} else {
    print "Usage: {$argv[0]} [job_id]\n";
}

