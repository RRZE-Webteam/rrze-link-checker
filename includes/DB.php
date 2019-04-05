<?php

namespace RRZE\LinkChecker;

defined('ABSPATH') || exit;

use RRZE\LinkChecker\Options;

class DB
{
    /**
     * [protected description]
     * @var string
     */
    protected static $postsTableName = 'rrze_lc_posts';

    /**
     * [protected description]
     * @var string
     */
    protected static $errorsTableName = 'rrze_lc_errors';

    /**
     * [createDbTables description]
     */
    public static function createDbTables()
    {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();

        dbDelta(
            sprintf(
                'CREATE TABLE IF NOT EXISTS %1$s (
                ID bigint(20) unsigned NOT NULL,
                post_type varchar(20) NOT NULL,
                post_status varchar(20) NOT NULL,
                checked datetime DEFAULT \'0000-00-00 00:00:00\' NOT NULL,
                PRIMARY KEY  (ID)) %2$s;',
                $wpdb->prefix . self::$postsTableName,
                $charset_collate
            )
        );

        dbDelta(
            sprintf(
                'CREATE TABLE IF NOT EXISTS %1$s (
                error_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                post_id bigint(20) unsigned NOT NULL,
                post_title text NOT NULL,
                url text(250) DEFAULT \'\' NOT NULL,
                text text(250) DEFAULT \'\' NOT NULL,
                http_status_code int(10) UNSIGNED DEFAULT NULL,
                error_status varchar(20) DEFAULT NULL,
                PRIMARY KEY  (error_id),
                KEY post_id (post_id),
                KEY http_status_code (http_status_code),
                KEY error_status (error_status)) %2$s;',
                $wpdb->prefix . self::$errorsTableName,
                $charset_collate
            )
        );
    }

    /**
     * [setupDbTables description]
     * @return boolean
     */
    public static function setupDbTables()
    {
        global $wpdb;

        $row = $wpdb->get_row(
            sprintf(
                'SELECT ID FROM %s',
                $wpdb->prefix . self::$postsTableName
            )
        );

        if (!empty($row)) {
            return false;
        }

        $options = Options::getOptions();
        $postTypes = $options->post_types;
        $postStatus = $options->post_status;

        if (empty($postTypes) || empty($postStatus)) {
            return false;
        }

        $postTypeArray = [];
        foreach ($postTypes as $value) {
            $postTypeArray[] = $wpdb->prepare("post_type = %s", $value);
        }

        $postTypeSql = implode(' OR ', $postTypeArray);

        $postStatusArray = [];
        foreach ($postStatus as $value) {
            $postStatusArray[] = $wpdb->prepare("post_status = %s", $value);
        }

        $postStatusSql = implode(' OR ', $postStatusArray);

        $wpdb->query(
            sprintf(
                'INSERT INTO %1$s (ID, post_type, post_status)
                SELECT ID, post_type, post_status
                FROM %2$s
                WHERE (%3$s) AND (%4$s) ORDER BY ID ASC',
                $wpdb->prefix . self::$postsTableName,
                $wpdb->posts,
                $postTypeSql,
                $postStatusSql
            )
        );

        return true;
    }

    /**
     * [truncateDbTables description]
     */
    public static function truncateDbTables()
    {
        global $wpdb;

        $wpdb->query(
            sprintf(
                'TRUNCATE TABLE %s;',
                $wpdb->prefix . self::$postsTableName
            )
        );

        $wpdb->query(
            sprintf(
                'TRUNCATE TABLE %s;',
                $wpdb->prefix . self::$errorsTableName
            )
        );
    }

    /**
     * [dropDbTables description]
     */
    public static function dropDbTables()
    {
        global $wpdb;

        $wpdb->query(
            sprintf(
                'DROP TABLE IF EXISTS %s;',
                $wpdb->prefix . self::$postsTableName
            )
        );

        $wpdb->query(
            sprintf(
                'DROP TABLE IF EXISTS %s;',
                $wpdb->prefix . self::$errorsTableName
            )
        );
    }

    /**
     * [public description]
     * @var string
     */
    public static function getPostsTableName()
    {
        return self::$postsTableName;
    }

    /**
     * [public description]
     * @var string
     */
    public static function getErrorsTableName()
    {
        return self::$errorsTableName;
    }
}
