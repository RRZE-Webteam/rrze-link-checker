<?php

class RRZE_LC_List_Table extends WP_List_Table {

    private $data;

    function __construct() {
        global $wpdb, $status, $page;

        parent::__construct(array(
            'singular' => 'link',
            'plural' => 'links',
            'ajax' => false
        ));

        $this->data = array();
        $errors = $wpdb->get_results(sprintf("SELECT * FROM %s", $wpdb->prefix . RRZE_LC_ERRORS_TABLE));

        foreach ($errors as $error) {
            $this->data[] = array(
                'url' => '<a href="' . $error->url . '" title="' . $error->url . '">' . $error->url . '</a>',
                'text' => $error->text,
                'source' => '<a href="' . get_permalink($error->post_id) . '">' . $error->post_title . '</a>'
            );
        }
    }

    function column_default($item, $column_name) {
        switch ($column_name) {
            case 'url':
            case 'text':
            case 'source':
                return $item[$column_name];
            default:
                return '';
        }
    }

    function get_columns() {
        $columns = array(
            'url' => __('Link-Url', RRZE_LC_TEXTDOMAIN),
            'text' => __('Fehlermeldung', RRZE_LC_TEXTDOMAIN),
            'source' => __('Quelle', RRZE_LC_TEXTDOMAIN)
        );
        return $columns;
    }

    function get_sortable_columns() {
        $sortable_columns = array(
            'url' => array('url', false),
            'text' => array('text', false),
            'source' => array('source', false)
        );
        return $sortable_columns;
    }

    function prepare_items() {
        $this->_column_headers = $this->get_column_info();

        function usort_reorder($a, $b) {
            $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'text';
            $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc';
            $result = strcmp($a[$orderby], $b[$orderby]);
            return ($order === 'asc') ? $result : -$result;
        }

        usort($this->data, 'usort_reorder');

        $per_page = $this->get_items_per_page('links_per_page', 20);
        $current_page = $this->get_pagenum();
        $total_items = count($this->data);

        $this->items = array_slice($this->data, (($current_page - 1) * $per_page), $per_page);

        $this->set_pagination_args(array(
            'total_items' => $total_items, // Total number of items
            'per_page' => $per_page, // How many items to show on a page
            'total_pages' => ceil($total_items / $per_page)   // Total number of pages
        ));
    }

}
