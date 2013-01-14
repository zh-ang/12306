<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title></title>
</head>
<?php

$strHost = "localhost";
$strPort = "8106";
$strUser = "test_w";
$strPass = "123456";
$strDatabase = "test";
$strDsn = sprintf("mysql:dbname=%s;host=%s;port=%s",
            $strDatabase, $strHost, $strPort);
$objDb = new PDO($strDsn, $strUser, $strPass);
$objDb->exec("SET NAMES " . "UTF-8");
$objDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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

?>

<body>
<?php
if (empty($_GET["id"])) {
    die(
<<<HTML
    <form action="" method="GET">
        <input type="text" name="id" value="" />
        <input type="submit" value="GO" />
    </form>
HTML
    );
}

$intPid = intval($_GET["id"]);

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

$pid = $arrRow["pid"];
$img = $arrRow["image"];
$err = $arrRow["lasterror"];
if ($err) {
    $objDb->exec("UPDATE `12306` SET `lasterror` = '' WHERE `pid` = $pid");
}
list($train, , , , , , , $ststart, $stend) = explode("#", $arrRow["request"]);
$date = $arrRow["date"];
?>

<a href="/12306/?id=<?php echo $pid; ?>">
<span style="font-size: 32px"><?php echo $pid; ?></span>
</a>
<a href="/12306/get.php?id=<?php echo $pid; ?>">
<span style="font-size: 12px"><?php printf("%s %s %s-%s", $date, $train, $ststart, $stend); ?></span>
</a>
<form action="/12306/post.php" method="POST">
    <img src="<?php echo $img; ?>" />
    <input type="text" name="code" value="" size="5" />
    <input type="hidden" name="id" value="<?php echo $pid; ?>" />
    <input type="submit" value="GO" />
</form>
<div style="color: red"><?php echo $err; ?></div>
<?php
    $s = intval(date("s"));
    $r = intval(240 * max(0, 20-min(abs($s-0),abs($s-60))) / 20);
    $g = intval(240 * max(0, 20-abs($s-20)) / 20);
    $b = intval(240 * max(0, 20-abs($s-40)) / 20);
    $rgb = sprintf("rgb(%d, %d, %d)", $r, $g, $b);
?>
<div style="background-color: <?php echo $rgb; ?>; color: #FFF">@ <?php echo date("Y-m-d H:i:s"); ?></div>
</body>
</html>
