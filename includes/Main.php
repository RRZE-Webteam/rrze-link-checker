<?php

namespace RRZE\LinkChecker;

defined('ABSPATH') || exit;

use RRZE\LinkChecker\Options;
use RRZE\LinkChecker\Worker;
use RRZE\LinkChecker\ScanTask;
use RRZE\LinkChecker\RescanTask;
use RRZE\LinkChecker\SavePostTask;

class Main
{
    /**
     * [protected description]
     * @var integer
     */
    protected static $cronInterval = HOUR_IN_SECONDS;

    /**
     * Main-Klasse wird instanziiert.
     */
    public function __construct()
    {
        $this->updateVersion();

        new Settings();

        $this->asyncTask();

        $this->dashboardSetup();

        add_action('admin_init', array($this, 'checkLinks'));
        add_action('delete_post', array($this, 'deletePost'));
    }

    protected function asyncTask()
    {
        new ScanTask();
        new RescanTask();
        new SavePostTask();

        add_action('wp_async_rrze_lc_scan_task', [$this, 'scanTask']);
        add_action('wp_async_rrze_lc_rescan_task', [$this, 'rescanTask']);
        add_action('wp_async_save_post', [$this, 'savePostTask']);

        add_filter('http_request_timeout', [$this, 'httpRequestTimeout']);
    }

    public function scanTask()
    {
        Worker::scan();
    }

    public function rescanTask()
    {
        Worker::rescan();
    }

    public function savePostTask($postId)
    {
        Model::savePost($postId);
    }

    public function httpRequestTimeout($timeout)
    {
        return Options::getOptions()->http_request_timeout;
    }

    public function dashboardSetup()
    {
        new Dashboard();
    }

    public function checkLinks()
    {
        $timestamp = time();

        if (get_option(Options::getCronTimestampOptionName()) < $timestamp) {
            update_option(Options::getCronTimestampOptionName(), $timestamp + self::$cronInterval);
            do_action('rrze_lc_scan_task');
        }
    }

    public function deletePost($postId)
    {
        Model::deletePost($postId);
    }

    public static function updateVersion()
    {
        global $wpdb;

        if (version_compare(get_option(Options::getVersionOptionName(), 0), '1.3.0', '<')) {
            update_option(Options::getVersionOptionName(), RRZE_PLUGIN_VERSION);
            $wpdb->query(
                sprintf(
                    'ALTER TABLE %s ADD error_status VARCHAR(20) NULL DEFAULT NULL AFTER text, ADD INDEX error_status (error_status)',
                    $wpdb->prefix . DB::getErrorsTableName()
                )
            );

            $wpdb->query(
                sprintf(
                    'ALTER TABLE %s ADD http_status_code INT UNSIGNED NULL DEFAULT NULL AFTER text, ADD INDEX http_status_code (http_status_code)',
                    $wpdb->prefix . DB::getErrorsTableName()
                )
            );

            DB::truncateDbTables();

            delete_option(Options::getCronTimestampOptionName());
            delete_option(Options::getScanTimestampOptionName());
        }
    }
}
