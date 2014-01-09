<?php

$db = new PDO(
    sprintf("mysql:dbname=%s;host=%s;port=%s",
        "test", // daname
        "127.0.0.1", // host
        "3306"  // port
    ),
    "root", // username
    "" // password
 );
$db->exec("SET NAMES " . "UTF-8");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
