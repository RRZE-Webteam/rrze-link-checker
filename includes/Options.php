<?php

namespace RRZE\LinkChecker;

defined('ABSPATH') || exit;

class Options
{
    /**
     * [protected description]
     * @var string
     */
    protected static $optionName = 'rrze_lc';

    /**
     * [protected description]
     * @var string
     */
    protected static $versionOptionName = 'rrze_lc_version';

    /**
     * [protected description]
     * @var string
     */
    protected static $cronTimestampOptionName = 'rrze_lc_cron_timestamp';

    /**
     * [protected description]
     * @var string
     */
    protected static $scanTimestampOptionName = 'rrze_lc_scan_timestamp';

    /**
     * [defaultOptions description]
     * @return array [description]
     */
    protected static function defaultOptions()
    {
        $options = [
            'post_types' => ['post', 'page'],
            'post_status' => ['publish'],
            'http_request_timeout' => 5
        ];

        return $options;
    }

    /**
     * [getOptions description]
     * @return object [description]
     */
    public static function getOptions()
    {
        $defaults = self::defaultOptions();

        $options = (array) get_option(self::$optionName);
        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);

        return (object) $options;
    }

    /**
     * [getOptionName description]
     * @return string [description]
     */
    public static function getOptionName()
    {
        return self::$optionName;
    }

    /**
     * [getVersionOptionName description]
     * @return string [description]
     */
    public static function getVersionOptionName()
    {
        return self::$versionOptionName;
    }

    /**
     * [getCronTimestampOptionName description]
     * @return string [description]
     */
    public static function getCronTimestampOptionName()
    {
        return self::$cronTimestampOptionName;
    }

    /**
     * [getScanTimestampOptionName description]
     * @return string [description]
     */
    public static function getScanTimestampOptionName()
    {
        return self::$scanTimestampOptionName;
    }
}
