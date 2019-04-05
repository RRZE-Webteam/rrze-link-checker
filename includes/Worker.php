<?php

namespace RRZE\LinkChecker;

defined('ABSPATH') || exit;

use RRZE\LinkChecker\Options;
use RRZE\LinkChecker\DB;

class Worker
{
    /**
     * [scan description]
     */
    public static function scan()
    {
        global $wpdb;

        $options = Options::getOptions();

        DB::setupDbTables();

        $timestamp = current_time('timestamp');

        update_option(Options::getScanTimestampOptionName(), $timestamp);

        $where = $wpdb->prepare("WHERE checked < %s", date('Y-m-d H:i:s', $timestamp));

        $postTypes = $options->post_types;

        if (!empty($postTypes)) {
            $where .= sprintf(' AND post_type IN (%s)', implode(',', array_map(create_function('$a', 'return "\'$a\'";'), $postTypes)));
        }

        $postStatus = $options->post_status;

        if (!empty($postStatus)) {
            $where .= sprintf(' AND post_status IN (%s)', implode(',', array_map(create_function('$a', 'return "\'$a\'";'), $postStatus)));
        }

        $posts = $wpdb->get_results(sprintf("SELECT ID FROM %s %s ORDER BY checked ASC", $wpdb->prefix . DB::getPostsTableName(), $where));

        foreach ($posts as $post) {
            self::checkUrls($post->ID);
        }
    }

    /**
     * [rescan description]
     */
    public static function rescan()
    {
        DB::truncateDbTables();
        DB::setupDbTables();
        self::scan();
    }

    /**
     * [rescanPost description]
     * @param integer $postId [description]
     */
    public static function rescanPost($postId = null)
    {
        self::checkUrls($postId);
    }

    /**
     * [checkUrls description]
     * @param  integer $postId [description]
     */
    protected static function checkUrls($postId = null)
    {
        global $wpdb;

        if (empty($postId)) {
            return;
        }

        $wpdb->update(
            $wpdb->prefix . DB::getPostsTableName(),
            [
                'checked' => current_time('mysql')
            ],
            [
                'ID' => $postId
            ],
            [
                '%s'
            ],
            [
                '%d'
            ]
        );

        $wpdb->delete(
            $wpdb->prefix . DB::getErrorsTableName(),
            [
                'post_id' => $postId,
                'error_status' => null
            ],
            [
                '%d',
                '%s'
            ]
        );

        $errors = Util::checkUrls($postId);

        if (empty($errors)) {
            return;
        }

        foreach ($errors as $error) {
            $error = (object) $error;

            $status_query = $wpdb->prepare("SELECT 1 FROM " . $wpdb->prefix . DB::getErrorsTableName() . " WHERE url = %s AND error_status IS NOT NULL", $error->url);
            if ($wpdb->get_row($status_query)) {
                continue;
            }

            $wpdb->insert(
                $wpdb->prefix . DB::getErrorsTableName(),
                [
                    'post_id' => $postId,
                    'post_title' => $error->post_title,
                    'url' => $error->url,
                    'text' => $error->text,
                    'http_status_code' => $error->http_status_code,
                    'error_status' => $error->error_status
                ],
                [
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%s'
                ]
            );
        }
    }
}
