<?php

namespace RRZE\LinkChecker;

defined('ABSPATH') || exit;

use RRZE\LinkChecker\Options;
use RRZE\LinkChecker\DB;

class Dashboard
{
    public function __construct()
    {
        add_action('wp_dashboard_setup', [$this, 'dashboardSetup']);
    }

    public function dashboardSetup()
    {
        if (current_user_can('upload_files')) {
            wp_add_dashboard_widget(
                '_rrze_link_checker_dashboard_widget',
                __('Link-Checker', 'rrze-link-checker'),
                [$this, 'dashboardWidget']
            );
        }
    }

    public function dashboardWidget()
    {
        global $wpdb;

        $options = Options::getOptions();

        $output = '';

        $timestamp = get_option(Options::getScanTimestampOptionName());
        $where = $wpdb->prepare(
            'WHERE checked < %s',
            date('Y-m-d H:i:s', $timestamp)
        );

        $postTypes = $options->post_types;

        if (!empty($postTypes)) {
            $where .= sprintf(
                ' AND post_type IN (%s)',
                implode(',', array_map(create_function('$a', 'return "\'$a\'";'), $postTypes))
            );
        }

        $postStatus = $options->post_status;

        if (!empty($postStatus)) {
            $where .= sprintf(
                ' AND post_status IN (%s)',
                implode(',', array_map(create_function('$a', 'return "\'$a\'";'), $postStatus))
            );
        }

        $queueCount = (int) $wpdb->get_var(
            sprintf(
                'SELECT COUNT(*) FROM %1$s %2$s',
                $wpdb->prefix . DB::getPostsTableName(),
                $where
            )
        );

        $errorsCount = (int) $wpdb->get_var(
            sprintf(
                'SELECT COUNT(*) FROM %s WHERE error_status IS NULL',
                $wpdb->prefix . DB::getErrorsTableName()
            )
        );

        if ($errorsCount > 0) {
            $output .= sprintf(
                '<p><a href="%1$s" title="' . __('Fehlerhafte Links anschauen', 'rrze-link-checker') . '"><strong>%2$s</strong></a></p>',
                admin_url('admin.php?page=rrze-link-checker'),
                sprintf(
                    _n('%d fehlerhafte Links gefunden.', '%d fehlerhafte Links gefunden.',
                    $errorsCount,
                    'rrze-link-checker'
                    ),
                    number_format_i18n($errorsCount)
                )
            );
        } else {
            $output .= sprintf(
                '<p>%s</p>',
                __('Keine fehlerhaften Links gefunden.', 'rrze-link-checker')
            );
        }

        if ($queueCount > 0) {
            $output .= sprintf(
                '<p>%s</p>',
                sprintf(
                    _n(
                        '%d Dokument in der Warteschlange.',
                        '%d Dokumente in der Warteschlange.',
                        $queueCount,
                        'rrze-link-checker'
                    ),
                    number_format_i18n($errorsCount)
                )
            );
        } else {
            $output .= sprintf(
                '<p>%s</p>',
                __('Keine Dokumente in der Warteschlange.', 'rrze-link-checker')
            );
        } ?>
        <div class="link-checker">
            <p><?php echo $output; ?></p>
        </div>
        <?php
    }
}
