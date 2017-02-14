<?php

class RRZE_LC_List_Table extends WP_List_Table {

    function __construct() {
        
        parent::__construct(array(
            'singular' => 'link',
            'plural' => 'links',
            'ajax' => false
        ));
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
            'source' => __('Dokument', RRZE_LC_TEXTDOMAIN)
        );
        return $columns;
    }

    function get_sortable_columns() {
        $sortable_columns = array(
            'text' => array('text', false)
        );
        return $sortable_columns;
    }

    function prepare_items() {
        global $wpdb;
        
        $this->_column_headers = $this->get_column_info();

        $per_page = $this->get_items_per_page('links_per_page', 20);
        $current_page = $this->get_pagenum();
        
        $total_items = $wpdb->get_var(sprintf("SELECT COUNT(*) FROM %s", $wpdb->prefix . RRZE_LC_ERRORS_TABLE));
        $total_pages = ceil($total_items / $per_page);
        
        $this->set_pagination_args(array(
            'total_items' => $total_items, // Gesamtzahl der Elemente
            'per_page' => $per_page, // Wie viele Elemente werden auf einer Seite angezeigt
            'total_pages' => $total_pages   // Gesamtzahl der Seiten
        ));
        
        $order_by = isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'text';
        $order = isset($_REQUEST['order']) ? $_REQUEST['order'] : 'ASC';
        
        $query = sprintf("SELECT * FROM %s ORDER BY %s %s LIMIT %s, %s", $wpdb->prefix . RRZE_LC_ERRORS_TABLE, $order_by, strtoupper($order), ($current_page - 1) * $per_page, $per_page);
        $results = $wpdb->get_results($query);
        
        $this->items = array();
        foreach ($results as $error) {
            $this->items[] = array(
                'url' => '<a href="' . $error->url . '" title="' . $error->url . '">' . $error->url . '</a>',
                'text' => $error->text,
                'source' => '<a href="' . get_permalink($error->post_id) . '">' . $error->post_title . '</a>'
            );
        }

    }

}
