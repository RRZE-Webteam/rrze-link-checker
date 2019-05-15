<?php

namespace RRZE\LinkChecker;

defined('ABSPATH') || exit;

use RRZE\LinkChecker\DB;
use RRZE\LinkChecker\Worker;
use RRZE\LinkChecker\Options;

class Model
{
    /**
     * [savePost description]
     * @param integer $postId [description]
     */
    public static function savePost($postId = null)
    {
        global $wpdb;

        if (
            (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || (defined('DOING_CRON') && DOING_CRON)
            || (defined('DOING_AJAX') && DOING_AJAX)
            || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)
        ) {
            return;
        }

        $options = Options::getOptions();
        $post_type = get_post_type($postId);

        $wpdb->delete(
            $wpdb->prefix . DB::getErrorsTableName(),
            array(
                'post_id' => $postId
            ),
            array(
                '%d'
            )
        );

        if (in_array($post_type, $options->post_types)) {
            $wpdb->replace(
                $wpdb->prefix . DB::getPostsTableName(),
                [
                    'ID' => $postId,
                    'checked' => '0000-00-00 00:00:00'
                ],
                [
                    '%d',
                    '%s'
                ]
            );

            Worker::rescanPost($postId);
        } else {
            $wpdb->delete(
                $wpdb->prefix . DB::getPostsTableName(),
                array(
                    'ID' => $postId
                ),
                array(
                    '%d'
                )
            );
        }
    }

    /**
     * [deletePost description]
     * @param integer $postId [description]
     */
    public static function deletePost($postId = null)
    {
        global $wpdb;

        $wpdb->delete(
            $wpdb->prefix . DB::getPostsTableName(),
            [
                'ID' => $postId
            ],
            [
                '%d'
            ]
        );

        $wpdb->delete(
            $wpdb->prefix . DB::getErrorsTableName(),
            [
                'post_id' => $postId
            ],
            [
                '%d'
            ]
        );
    }
}
