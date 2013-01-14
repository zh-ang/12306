CREATE TABLE `12306` (
 `pid` int(11) NOT NULL AUTO_INCREMENT,
 `cookie` varchar(1024) COLLATE utf8_unicode_ci NOT NULL,
 `request` varchar(1024) COLLATE utf8_unicode_ci NOT NULL,
 `date` date NOT NULL,
 `passenger` varchar(1024) COLLATE utf8_unicode_ci NOT NULL,
 `token` varchar(1024) COLLATE utf8_unicode_ci NOT NULL,
 `image` varchar(2048) COLLATE utf8_unicode_ci DEFAULT NULL,
 `touch` timestamp NULL DEFAULT NULL,
 `lasterror` varchar(1024) COLLATE utf8_unicode_ci NOT NULL,
 `status` int(11) NOT NULL,
 `addtime` timestamp NULL DEFAULT NULL,
 `modtime` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;