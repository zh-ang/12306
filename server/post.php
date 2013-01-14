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




$intPid = intval($_POST["id"]);

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
$strToken = $arrRow["token"];
$strDate = $arrRow["date"];
$strCode = $_POST["code"];
$strPassenger = $arrRow["passenger"];

$req = explode("#", $arrRow["request"]);

// Get token and save
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
//        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
$url = 'https://dynamic.12306.cn/otsweb/order/confirmPassengerAction.do?method=confirmPassengerInfoSingle';
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_COOKIE, $strCookie);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_REFERER, 'https://dynamic.12306.cn/otsweb/order/querySingleAction.do?method=init');
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

$post[] = 'org.apache.struts.taglib.html.TOKEN=' . urlencode($strToken);
$post[] = 'orderRequest.reserve_flag=A';
$post[] = 'orderRequest.train_date=' . urlencode($strDate);
$post[] = 'orderRequest.train_no=' . urlencode($req[3]); # 240000Z13304';
$post[] = 'orderRequest.station_train_code=' . urlencode($req[0]); #Z133';
$post[] = 'orderRequest.from_station_telecode=' . urlencode($req[4]); #BXP';
$post[] = 'orderRequest.to_station_telecode=' . urlencode($req[5]); #JJG';
$post[] = 'orderRequest.seat_type_code=';
$post[] = 'orderRequest.ticket_type_order_num=';
$post[] = 'orderRequest.bed_level_order_num=000000000000000000000000000000';
$post[] = 'orderRequest.start_time=' . urlencode($req[2]); #19%3A45';
$post[] = 'orderRequest.end_time=' . urlencode($req[6]); #06%3A09';
$post[] = 'orderRequest.from_station_name=' . urlencode($req[7]); #%E5%8C%97%E4%BA%AC%E8%A5%BF';
$post[] = 'orderRequest.to_station_name=' . urlencode($req[8]); #%E4%B9%9D%E6%B1%9F';
$post[] = 'orderRequest.cancel_flag=1';
$post[] = 'orderRequest.id_mode=Y';
$post[] = 'randCode=' . $strCode; 

$post[] = 'checkbox0=0';
$post[] = 'textfield=%E4%B8%AD%E6%96%87%E6%88%96%E6%8B%BC%E9%9F%B3%E9%A6%96%E5%AD%97%E6%AF%8D';
$i = 0;
foreach (explode("/", $strPassenger) as $s) {
    ++$i;
    $post[] = 'passengerTickets='.urlencode($s);
    list($seat, $ticket, $name, $cardtype, $cardno, $mobile, $save) = explode(",", $s);
    $post[] = 'oldPassengers=' . urlencode("{$name},{$cardtype},{$cardno}");
    $post[] = "passenger_{$i}_seat={$seat}";
    $post[] = "passenger_{$i}_ticket={$ticket}";
    $post[] = "passenger_{$i}_name=" . urlencode($name);
    $post[] = "passenger_{$i}_cardtype={$cardtype}";
    $post[] = "passenger_{$i}_cardno={$cardno}";
    $post[] = "passenger_{$i}_mobileno={$mobile}";
}
/*
# 4,1,测试,2,110110800101123,13011112222,N
$post[] = 'passengerTickets=4%2C1%2C%E6%B5%8B%E8%AF%95%2C2%2C110110800101123%2C13011112222%2CN';
$post[] = 'oldPassengers=';
$post[] = 'passenger_2_seat=4';
$post[] = 'passenger_2_ticket=1';
$post[] = 'passenger_2_name=%E6%B5%8B%E8%AF%95';
$post[] = 'passenger_2_cardtype=2';
$post[] = 'passenger_2_cardno=110110800101123';
$post[] = 'passenger_2_mobileno=13011112222';
 */
for ($c=0; $c<5; $c++) {
    if ($c >= $i) {
        $post[] = 'oldPassengers=';
    }
    $post[] = 'checkbox9=Y';
}

$postfields = implode('&', $post);
error_log($postfields);

curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);

$s = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code == 200 && strpos($s, '席位已成功锁定')) {
    echo <<<HTML
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title></title>
</head>
<body style="background-color: #8F8;">
HTML;
    list($train, , , , , , , $ststart, $stend) = explode("#", $arrRow["request"]);
    printf("%s %s %s-%s", $date, $train, $ststart, $stend); 
    echo "<br />";
    foreach (explode("/", $strPassenger) as $s) {
        list($seat, $ticket, $name, $cardtype, $cardno, $mobile, $save) = explode($s);
        printf("%s %d张<br />", $name, $ticket);
    }
    echo <<<HTML
</body>
</html>
HTML;
} else {
    if ($code != 200) {
        $error = "提交错误: HTTP$code";
    }
    $m = array();
    if (preg_match('#var message = "(.+)";#', $s, $m)) {
        $strMsg = $m[1];
        $error = "提交错误: $strMsg";
    } else {
        if (strpos($s, "系统忙")) {
            $error = "提交错误: 系统忙";
        } else if (strlen($s) == 0) {
            $error = "提交错误: 无响应";
        } else {
            $error = "提交错误: 未知错误";
            file_put_contents("/home/zhangzy/tmp/12306/error.html", $s);
        }
    }
    $objDb->exec("UPDATE `12306` SET `lasterror` = '$error' WHERE `pid` = $intPid");
    $url = "/12306/get.php?id=$intPid";
    echo <<<HTML
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<META HTTP-EQUIV="Refresh" CONTENT="2;URL=$url">
<title></title>
</head>
<body style="background-color: #F88;">
$error
</body>
</html>
HTML;
}


