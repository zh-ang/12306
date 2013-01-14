<?php

/*
$objDb->beginTransaction();
$objDb->commit();
$objDb->rollBack();
$objDb->lastInsertId();
$mixRet = $objDb->exec($sql);
$objStmt = $objDb->query($sql);
$objStmt->setFetchMode(PDO::FETCH_NAMED);
$mixRet = $objStmt->fetchAll();
*/




if (empty($_GET["id"])) {
    header("Location: /12306/");
    die();
}

$intPid = intval($_GET["id"]);

header("Location: /12306/?id=$intPid");

$strHost = "localhost";
$strPort = "8106";
$strUser = "test_w";
$strPass = "123456";
$strDatabase = "test";
$strDsn = sprintf("mysql:dbname=%s;host=%s;port=%s", $strDatabase, $strHost, $strPort);
$objDb = new PDO($strDsn, $strUser, $strPass);
$objDb->exec("SET NAMES " . "UTF-8");
$objDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$objStmt = $objDb->query("SELECT * FROM `12306` WHERE `pid`=$intPid");
$objStmt->setFetchMode(PDO::FETCH_NAMED);
$arrRow = $objStmt->fetch();

if (empty($arrRow)) {
    $forward = $_SERVER["SCRIPT_NAME"];
    die("Record is not exist, click <a href=\"$forward\">here</a> to restart");
}

$strCookie = $arrRow["cookie"];
if ($arrRow["request"] == "") {
    $objDb->exec("UPDATE `12306` SET `lasterror` = '空请求' WHERE `pid` = $intPid");
    exit();
}

// Get token and save
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
//        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
$url = 'https://dynamic.12306.cn/otsweb/order/querySingleAction.do?method=submutOrderRequest';
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_COOKIE, $strCookie);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_REFERER, 'https://dynamic.12306.cn/otsweb/order/querySingleAction.do?method=init');
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

$req = explode("#", $arrRow["request"]);
$strDate= $arrRow["date"];

$postfields = '';
$postfields .= 'station_train_code=' . urlencode($req[0]);#Z65
$postfields .= '&train_date=' . $strDate;#2011-11-24
$postfields .= '&seattype_num=' ;#
$postfields .= '&from_station_telecode=' . urlencode($req[4]);#BXP
$postfields .= '&to_station_telecode=' . urlencode($req[5]);#JJG
$postfields .= '&include_student=00';#00
$postfields .= '&from_station_telecode_name=' . urlencode($req[7]);#%E5%8C%97%E4%BA%AC%E8%A5%BF
$postfields .= '&to_station_telecode_name=' . urlencode($req[8]);#%E4%B9%9D%E6%B1%9F
$postfields .= '&round_train_date=' . urlencode(date("Y-m-d"));#2011-11-24
$postfields .= '&round_start_time_str=' . urlencode("00:00--24:00");#00%3A00--24%3A00
$postfields .= '&single_round_type=1'; #1
$postfields .= '&train_pass_type=QB'; #QB
$postfields .= '&train_class_arr=' . urlencode("QB#D#Z#T#K#QT#");#QB#D#Z#T#K#QT#
$postfields .= '&start_time_str=' . urlencode("00:00--24:00");#00:00--24:00
$postfields .= '&lishi=' . urlencode($req[1]);#624
$postfields .= '&train_start_time=' . urlencode($req[2]);#19%3A45'
$postfields .= '&trainno=' . urlencode($req[3]);#240000Z13304
$postfields .= '&arrive_time=' . urlencode($req[6]);# 06:15
$postfields .= '&from_station_name=' . urlencode($req[7]);#%E5%8C%97%E4%BA%AC%E8%A5%BF
$postfields .= '&to_station_name=' . urlencode($req[8]);#%E4%B9%9D%E6%B1%9F
$postfields .= '&ypInfoDetail=' . urlencode($req[9]);# 302910002440458000026084200001

curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
$o = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (preg_match('#var message = "(.+)";#', $o, $m)) {
    $strMsg = $m[1];
    $objDb->exec("UPDATE `12306` SET `lasterror` = '查询错误: $strMsg' WHERE `pid` = $intPid");
} else if ($code == 200 && strpos($o, $req[0])) {
    $m = array();
    if (preg_match('#<input type="hidden" name="org.apache.struts.taglib.html.TOKEN" value="(.+)">#', $o, $m)) {
        $strToken = $m[1];
        $objDb->exec("UPDATE `12306` SET `token` = '$strToken' WHERE `pid` = $intPid");
    }
} else {
    if ($code == 200) {
        if (strpos($o, "系统忙")) {
            $objDb->exec("UPDATE `12306` SET `lasterror` = '查询错误: 系统忙' WHERE `pid` = $intPid");
        } else if (strlen($o) == 0) {
            $objDb->exec("UPDATE `12306` SET `lasterror` = '查询错误: 响应为空' WHERE `pid` = $intPid");
        } else {
            $objDb->exec("UPDATE `12306` SET `lasterror` = '查询错误: 未知错误' WHERE `pid` = $intPid");
            file_put_contents("/home/zhangzy/tmp/12306/error.html", $o);
        }
    } else {
        $objDb->exec("UPDATE `12306` SET `lasterror` = '查询错误: HTTP$code' WHERE `pid` = $intPid");
    }
}

// Get rand image & save

$h = curl_init();
curl_setopt($h, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($h, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($h, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($h, CURLOPT_HEADER, 0);
curl_setopt($h, CURLOPT_VERBOSE, 0);
curl_setopt($h, CURLOPT_URL, "https://dynamic.12306.cn/otsweb/passCodeAction.do?rand=randp");
curl_setopt($h, CURLOPT_REFERER, 'https://dynamic.12306.cn/otsweb/order/querySingleAction.do?method=init');
curl_setopt($h, CURLOPT_COOKIE, $strCookie);
curl_setopt($h, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($h, CURLOPT_TIMEOUT, 10);
$output = curl_exec($h);
$code = curl_getinfo($h, CURLINFO_HTTP_CODE);
curl_close($h);
$strImg = addslashes("data:image/jpeg;base64,".base64_encode($output));
if ($code == 200) {
    $objDb->exec("UPDATE `12306` SET `image` = '$strImg' WHERE `pid` = $intPid");
} else {
    $objDb->exec("UPDATE `12306` SET `image` = '', `lasterror` = '验证码: HTTP{$code}' WHERE `pid` = $intPid");
}


