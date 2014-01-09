<?php

include_once("../db.inc");

class daemon_train {

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
        print "get ".json_encode($row)."\n";

        if (empty($row["cookie"])) return false;
        $this->_cookie = $row["cookie"];
        print "cookie ok!\n";
        // var_dump($row["info"]);

        $info = json_decode(strtr($row["info"], "'", '"'), true);
        // $info = json_decode($row["info"], true);
        // var_dump($info);
        if (empty($info)) return false;
        print "info ok!\n";

        $ext = json_decode($row["info_ext"], true);
        if (empty($ext)) return false;
        print "info_ext ok!\n";

        if (empty($row["code"])) return false;
        $arrCodeStatus = json_decode($row["code_status"], true);
        if ($arrCodeStatus["ok"] == false) return false;
        print "code ok!\n";

        return $row;
    }

    public function getPassengerTicketStr($strRaw, $chrSeatType) {
        $arrRet = array();
        foreach (explode(",", $strRaw) as $strPassenger) {
            list($strName, $strSecureId) = explode(":", $strPassenger);
            $arrRet[] = sprintf("%s,0,1,%s,1,%s,,N", $chrSeatType, $strName, $strSecureId);
        }
        return implode("_", $arrRet);
    }

    public function getOldPassengerStr($strRaw) {
        $arrRet = array();
        foreach (explode(",", $strRaw) as $strPassenger) {
            list($strName, $strSecureId) = explode(":", $strPassenger);
            $arrRet[] = sprintf("%s,1,%s,1_", $strName, $strSecureId);
        }
        return join($arrRet);
    }

    /* {{{ protected function _getQueueCount($strRepeatSubmitToken)  */
    protected function _getQueueCount($strRepeatSubmitToken) {
        $strUrl = "https://kyfw.12306.cn/otn/confirmPassenger/queryOrderWaitTime?random=".time().sprintf("%03d", rand(0,1000))."&tourFlag=dc&_json_att=&REPEAT_SUBMIT_TOKEN=".$strRepeatSubmitToken;
        $h = curl_init();
        curl_setopt($h, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($h, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($h, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($h, CURLOPT_HEADER, 0);
        curl_setopt($h, CURLOPT_VERBOSE, 0);
        curl_setopt($h, CURLOPT_URL, $strUrl);
        curl_setopt($h, CURLOPT_REFERER, "https://kyfw.12306.cn/otn/confirmPassenger/initDc");
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

    public function monitor($row) {

        $arrExt  = json_decode($row["info_ext"], true);

        while (1) {

            print "monitoring ~\n";

            $output = $this->_getQueueCount($arrExt["REPEAT_SUBMIT_TOKEN"]);
            print "get {$output} ~\n";
            /* log http */file_put_contents("queryOrderWaitTime.".uniqid(), $output);

            $arrOutput = json_decode($output, true);

            if ($arrOutput["status"] == true && $arrOutput["data"]["waitTime"] < 0) {
                if ($arrOutput["orderId"]) {
                    return true;
                } else {
                    return false;
                }
            }

            $intWait = min($arrOutput["data"]["waitTime"], 5);
            sleep($intWait);

        }

    }
    
    /* {{{ protected function _checkOrderInfo($passengerTicketStr, $oldPassengerStr, $randCode)  */
    protected function _checkOrderInfo($passengerTicketStr, $oldPassengerStr, $randCode) {

        $arrData = array();
        $arrData["cancel_flag"]         = "2";
        $arrData["bed_level_order_num"] = "000000000000000000000000000000";
        $arrData["passengerTicketStr"]  = $passengerTicketStr;
        $arrData["oldPassengerStr"]     = $oldPassengerStr;
        $arrData["tour_flag"]           = "dc";
        $arrData["randCode"]            = $randCode;

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
        curl_setopt($h, CURLOPT_URL, "https://kyfw.12306.cn/otn/confirmPassenger/checkOrderInfo");
        curl_setopt($h, CURLOPT_REFERER, "https://kyfw.12306.cn/otn/confirmPassenger/initDc");
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

        return $output;

    }
    /* }}} */

    /* {{{ protected function _confirmSingleForQueue ($passengerTicketStr */
    protected function _confirmSingleForQueue ($passengerTicketStr,
                $oldPassengerStr, $randCode, $purpose_codes, $key_check_isChange,
                $leftTicketStr, $train_location, $REPEAT_SUBMIT_TOKEN) {

        $arrData = array();
        $arrData["passengerTicketStr"]  = $passengerTicketStr;
        $arrData["oldPassengerStr"]     = $oldPassengerStr;
        $arrData["randCode"]            = $randCode;
        $arrData["purpose_codes"]       = $purpose_codes;
        $arrData["key_check_isChange"]  = $key_check_isChange;
        $arrData["leftTicketStr"]       = $leftTicketStr;
        $arrData["train_location"]      = $train_location;
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
        curl_setopt($h, CURLOPT_URL, "https://kyfw.12306.cn/otn/confirmPassenger/confirmSingleForQueue");
        curl_setopt($h, CURLOPT_REFERER, "https://kyfw.12306.cn/otn/confirmPassenger/initDc");
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

        return $output;

    }

    public function exec(array $row) {

        print "exec ~\n";

        $arrInfo = json_decode(strtr($row["info"], "'", '"'), true);
        $arrExt  = json_decode($row["info_ext"], true);

        $chrSeatType = $arrExt["SEAT_TYPE"];

        print "preparing ~\n";
        $output = $this->_checkOrderInfo($this->getPassengerTicketStr($row["passenger"], $chrSeatType),
                    $this->getOldPassengerStr($row["passenger"]),
                    $row["code"]
                );

        print "prepared {$output} ~\n";

        if ($output) {
            $arrOutput = json_decode($output, true);
            if (isset($arrOutput["url"]) && $arrOutput["url"]) {
                return false;
            }
            if ($arrOutput["status"] == true && $arrOutput["data"]["submitStatus"] == true) {
                print "ready\n";
            } else {
                return false;
            }
        }

        $this->_db->exec("UPDATE `12306` SET `status` = 'commiting', `status_time` = NOW() WHERE `id` = {$this->_id}");
        print "commiting ~\n";

        $output =$this->_confirmSingleForQueue(
                    $this->getPassengerTicketStr($row["passenger"], $chrSeatType),
                    $this->getOldPassengerStr($row["passenger"]),
                    $row["code"],
                    $arrInfo["purpose_codes"],
                    $arrInfo["key_check_isChange"],
                    $arrInfo["leftTicketStr"],
                    $arrInfo["train_location"],
                    $arrExt["REPEAT_SUBMIT_TOKEN"]
                );

        /* log http */file_put_contents("confirmSingleForQueue.".uniqid(), $output);
        print "commited {$output} \n";

        if ($output) {
            $arrOutput = json_decode($output, true);
            if ($arrOutput["status"] == true && $arrOutput["data"]["submitStatus"] == true) {
                $this->_db->exec("UPDATE `12306` SET `status` = 'commited', `status_time` = NOW() WHERE `id` = {$this->_id}");
                print "succeed ~\n";
                if ($this->monitor($row) == true) {
                    // got the ticket, halt all
                    $this->_db->exec("UPDATE `12306` SET `status` = 'pay', `status_time` = NOW() WHERE `id` = {$this->_id}");
                    return true;
                } else {
                    print "failed ~\n";
                    return false;
                }
            }
        }
        print "failed ~\n";

        return  false;

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
        curl_setopt($h, CURLOPT_URL, "https://kyfw.12306.cn/otn/confirmPassenger/getPassCodeNew?module=passenger&rand=randp");
        curl_setopt($h, CURLOPT_REFERER, "https://kyfw.12306.cn/otn/confirmPassenger/initDc");
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

    public function erase() {
        $this->_db->exec("UPDATE `12306` SET `status` = 'reset', `status_time` = NOW() WHERE `id` = {$this->_id}");
    }

    public function loop() {
        while ($this->_run) {
            print "loop ~\n";
            // polling
            $row = $this->valid();
            if ($row && $row["status"] != "pay") {
                // try 1 min, no matter
                $t1min = time()+60;
                $got = false;
                while (time() < $t1min) {
                    $got = $this->exec($row);
                    sleep(1);
                    if ($got) break;
                }
                if ($got == false) {
                    $this->erase();
                }
            }
            sleep(1);
        }
    }

}

if ($argc>1) {
    new daemon_train($argv[1]);
} else {
    print "Usage: {$argv[0]} [job_id]\n";
}
