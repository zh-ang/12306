CREATE TABLE `12306` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `pid` int(11) NOT NULL,

    `train_date` varchar(16) COLLATE ascii NOT NULL,
    `from_station` varchar(16) COLLATE ascii NOT NULL,
    `to_station` varchar(16) COLLATE ascii NOT NULL,
    `purpose_codes` varchar(16) COLLATE ascii NOT NULL,
    `prefer_train` varchar(16) COLLATE ascii NOT NULL,
    `passenger` varchar(1024) COLLATE utf8_unicode_ci NOT NULL,

    `secret` varchar(2048) CHARACTER SET utf8_unicode_ci NOT NULL,
    `secret_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',

    `info` varchar(16384) CHARACTER SET ascii NOT NULL,
    `info_ext` varchar(1024) CHARACTER SET ascii NOT NULL,
    `info_time` timestamp NULL DEFAULT NULL,

    `cookie` varchar(1024) COLLATE ascii NOT NULL,
    `cookie_time` timestamp NULL DEFAULT NULL,

    `image` varchar(4096) COLLATE ascii NOT NULL,
    `code` varchar(16) COLLATE ascii DEFAULT NULL,
    `code_status` varchar(1024) CHARACTER SET ascii NOT NULL,
    `code_time` timestamp NULL DEFAULT NULL,

    `error` varchar(1024) COLLATE utf8_unicode_ci NOT NULL,
    `error_time` timestamp NULL DEFAULT NULL,
    `status` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
    `status_time` timestamp NULL DEFAULT NULL,

    `addtime` timestamp NULL DEFAULT NULL,
    `modtime` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci

CREATE TABLE `12306_login_pool` (
    `id` int(11) NOT NULL AUTO_INCREMENT,

    `cookie` varchar(1024) COLLATE ascii NOT NULL,
    `cookie_time` timestamp NULL DEFAULT NULL,

    `image` varchar(4096) COLLATE ascii NOT NULL,
    `code` varchar(16) COLLATE ascii DEFAULT NULL,
    `code_time` timestamp NULL DEFAULT NULL,

    `addtime` timestamp NULL DEFAULT NULL,
    `modtime` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
