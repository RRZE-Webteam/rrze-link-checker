<?php

define('RRZE_LC_VERSION', '1.1.3');
define('RRZE_LC_VERSION_OPTION_NAME', 'rrze_lc_version');

define('RRZE_LC_OPTION_NAME', 'rrze_lc');

define('RRZE_LC_CRON_INTERVAL', HOUR_IN_SECONDS);
define('RRZE_LC_OPTION_NAME_CRON_TIMESTAMP', 'rrze_lc_cron_timestamp');
define('RRZE_LC_OPTION_NAME_SCAN_TIMESTAMP', 'rrze_lc_scan_timestamp');

define('RRZE_LC_POSTS_TABLE', 'rrze_lc_posts');
define('RRZE_LC_ERRORS_TABLE', 'rrze_lc_errors');

define('RRZE_LC_TEXTDOMAIN', 'rrze-link-checker');

define('RRZE_LC_ALLOWED_POST_STATUS', 'publish, future, draft, pending, private, trash');

define('RRZE_LC_MIN_PHP_VERSION', '5.5');
define('RRZE_LC_MIN_WP_VERSION', '4.7');

define('RRZE_LC_ROOT', dirname(__FILE__));
define('RRZE_LC_FILE_PATH', RRZE_LC_ROOT . '/' . basename(__FILE__));
define('RRZE_LC_URL', plugins_url('/', __FILE__));
