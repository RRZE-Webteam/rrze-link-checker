<?php

namespace RRZE\LinkChecker;

defined('ABSPATH') || exit;

require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');

use \WP_List_Table;
use RRZE\LinkChecker\DB;

class ListTable extends WP_List_Table {

    function __construct() {

        parent::__construct(array(
            'singular' => 'lclink',
            'plural' => 'lclinks',
            'ajax' => false
        ));
    }

    protected function get_views() {
        global $wpdb, $pagenow;

        $page = $_REQUEST['page'];
        $view = isset($_REQUEST['view']) ? $_REQUEST['view'] : '';
        $all_current = !$view ? 'class="current "' : '';
        $ignore_current = $view == 'ignore' ? 'class="current "' : '';
        $all_items = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->prefix . DB::getErrorsTableName() . " WHERE error_status IS NULL");
        $ignore_items = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->prefix . DB::getErrorsTableName() . " WHERE error_status = 'ignore'");

        $status_links = array(
            'all' => '<a ' . $all_current . 'href="' . add_query_arg(array('page' => $page), admin_url($pagenow)) . '">' . __('All', 'rrze-link-checker') .' <span class="count">(' . ($all_items ? $all_items : 0) . ')</span></a>',
            'ignore' => '<a ' . $ignore_current . 'href="' . add_query_arg(array('page' => $page, 'view' =>'ignore'), admin_url($pagenow)) . '">' . __('Ignored', 'rrze-link-checker') .' <span class="count">(' . ($ignore_items ? $ignore_items : 0) . ')</span></a>'
        );
        return $status_links;
    }

    protected function extra_tablenav($which) {
        global $wpdb;

        $view = isset($_REQUEST['view']) ? $_REQUEST['view'] : '';
        $code_filter = isset($_GET['lc_code_filter']) ? $_GET['lc_code_filter'] : '';

        if ($which == "top") {
            ?>
            <div class="alignleft actions bulkactions">
            <?php
            $http_status_codes = Util::httpStatusCodes();
            if($http_status_codes) :
                ?>
                <select name="lc_code_filter" class="lc-code-filter">
                    <option value=""><?php _e('All Errors', 'rrze-link-checker'); ?></option>
                    <?php
                    foreach($http_status_codes as $code => $title) {
                        $selected = '';
                        if($code_filter == $code ) {
                            $selected = ' selected = "selected"';
                        }
                    ?>
                    <option value="<?php echo $code; ?>" <?php echo $selected; ?>><?php printf('%s - %s', $code, $title); ?></option>
                    <?php
                    }
                    ?>
                </select>
                <?php if ($view) : ?>
                <input type="hidden" name="view" value="<?php echo $view; ?>">
                <?php endif; ?>
                <input name="lc_filter_action" id="lc-filter-submit" class="button" value="<?php _e('Filter', 'rrze-link-checker'); ?>" type="submit">
                <?php
            endif;
            ?>
            </div>
            <?php
        }
    }

    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'url':
            case 'text':
            case 'source':
                return $item[$column_name];
            default:
                return '';
        }
    }

    public function column_url($item) {
        global $pagenow;

        $view = isset($_REQUEST['view']) ? $_REQUEST['view'] : '';
        $page = $_REQUEST['page'];
        $id = $item['id'];

        // Build row actions
        if ($view == 'ignore') {
            $actions['unignore'] = '<a href="' . add_query_arg(array('page' => $page, 'action' =>'unignore', 'id' => $id, 'view' => 'ignore'), admin_url($pagenow)) . '">' . __('Unignore', 'rrze-link-checker') .'</a>';
        } else {
            $actions['ignore'] = '<a href="' . add_query_arg(array('page' => $page, 'action' =>'ignore', 'id' => $id), admin_url($pagenow)) . '">' . __('Ignore', 'rrze-link-checker') .'</a>';
        }

        // Return the title contents
        return sprintf('%1$s %2$s',
                /* $1%s */ $item['url'],
                /* $2%s */ $this->row_actions($actions)
        );
    }

    public function column_cb($item) {
        return sprintf(
                '<input type="checkbox" name="%1$s[]" value="%2$s" />',
                /* $1%s */ $this->_args['singular'], // Let's simply repurpose the table's singular label
                /* $2%s */ $item['id']                // The value of the checkbox should be the items's id
        );
    }

    public function get_columns() {
        $columns = array(
            'cb' => '<input type="checkbox" />', // Render a checkbox instead of text
            'url' => __('Link Url', 'rrze-link-checker'),
            'text' => __('Error message', 'rrze-link-checker'),
            'source' => __('Document', 'rrze-link-checker')
        );
        return $columns;
    }

    protected function get_sortable_columns() {
        $sortable_columns = array(
            'text' => array('text', false)
        );
        return $sortable_columns;
    }

    protected function get_bulk_actions() {
        $view = isset($_REQUEST['view']) ? $_REQUEST['view'] : '';

        // Build drop-down bulk actions list
        if ($view == 'ignore') {
            $actions['unignore'] = __('Unignore', 'rrze-link-checker');
        } else {
            $actions['ignore'] = __('Ignore', 'rrze-link-checker');
        }

        return $actions;
    }

    public function process_bulk_action() {
        global $wpdb, $pagenow;

        $page = $_REQUEST['page'];
        $links = isset($_GET['lclink']) ? (array) $_GET['lclink'] : array();

        if ('ignore' === $this->current_action()) {
            if (!empty($links)) {
                foreach ($links as $id) {
                    $wpdb->update($wpdb->prefix . DB::getErrorsTableName(), array('error_status' => 'ignore'), array('error_id' => $id), array('%s'), array('%d'));
                }
                wp_redirect(add_query_arg(array('page' => $page), admin_url($pagenow)));
                exit;
            }
        } elseif ('unignore' === $this->current_action()) {
            if (!empty($links)) {
                foreach ($links as $id) {
                    $wpdb->update($wpdb->prefix . DB::getErrorsTableName(), array('error_status' => NULL), array('error_id' => $id), array('%s'), array('%d'));
                }
                wp_redirect(add_query_arg(array('page' => $page, 'view' =>'ignore'), admin_url($pagenow)));
                exit;
            }
        }
    }

    public function prepare_items() {
        global $wpdb;

        $per_page = $this->get_items_per_page('rrze_lc_per_page', 20);

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->process_bulk_action();

        $current_page = $this->get_pagenum();

        $where = "WHERE 1";

        $search = isset($_GET['s']) ? $_GET['s'] : '';
        $where .= $search ? $wpdb->prepare(" AND post_title LIKE %s OR url LIKE %s OR text LIKE %s", '%' . $search . '%', '%' . $search . '%', '%' . $search . '%') : '';

        $view = isset($_REQUEST['view']) ? $_REQUEST['view'] : '';
        $where .= $view ? $wpdb->prepare(" AND error_status = %s", $view) : " AND error_status IS NULL";

        $code_filter = isset($_GET['lc_code_filter']) ? absint($_GET['lc_code_filter']) : '';
        $where .= $code_filter ? $wpdb->prepare(" AND http_status_code = %d", $code_filter) : '';

        $total_items = $wpdb->get_var(sprintf("SELECT COUNT(*) FROM %s %s", $wpdb->prefix . DB::getErrorsTableName(), $where));
        $total_pages = ceil($total_items / $per_page);

        $this->set_pagination_args(array(
            'total_items' => $total_items, // Gesamtzahl der Elemente
            'per_page' => $per_page, // Wie viele Elemente werden auf einer Seite angezeigt
            'total_pages' => $total_pages   // Gesamtzahl der Seiten
        ));

        $order_by = isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'text';
        $order = isset($_REQUEST['order']) ? $_REQUEST['order'] : 'ASC';

        $query = sprintf("SELECT * FROM %s %s ORDER BY %s %s LIMIT %s, %s", $wpdb->prefix . DB::getErrorsTableName(), $where, $order_by, strtoupper($order), ($current_page - 1) * $per_page, $per_page);
        $results = $wpdb->get_results($query);

        $this->items = array();
        foreach ($results as $error) {
            $this->items[] = array(
                'id' => $error->error_id,
                'url' => '<a href="' . $error->url . '" title="' . $error->url . '">' . $error->url . '</a>',
                'text' => $error->text,
                'source' => '<a href="' . get_permalink($error->post_id) . '">' . $error->post_title . '</a>'
            );
        }

    }

}
