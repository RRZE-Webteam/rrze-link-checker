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

    public function column_url($item) {
        $page = $_REQUEST['page'];
        $id = $item['id'];
        // Build row actions
        $actions = array(
            'delete' => sprintf('<a href="?page=%1$s&action=%2$s&id=%3$s">%4$s</a>', $page, 'delete', $id, __('Löschen', 'rrze-link-checker')),
        );

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
    
    function get_columns() {
        $columns = array(
            'cb' => '<input type="checkbox" />', // Render a checkbox instead of text
            'url' => __('Link-Url', 'rrze-link-checker'),
            'text' => __('Fehlermeldung', 'rrze-link-checker'),
            'source' => __('Dokument', 'rrze-link-checker')
        );
        return $columns;
    }

    function get_sortable_columns() {
        $sortable_columns = array(
            'text' => array('text', false)
        );
        return $sortable_columns;
    }

    public function get_bulk_actions() {
        $actions = array(
            'delete' => __('Löschen', 'rrze-link-checker')
        );
        return $actions;
    }
    
    public function process_bulk_action() {
        global $wpdb;

        if ('delete' === $this->current_action()) {
            $links = isset($_GET['link']) ? (array) $_GET['link'] : array();
            if (!empty($links)) {
                foreach ($links as $id) {
                    $wpdb->delete($wpdb->prefix . RRZE_LC_ERRORS_TABLE, array('error_id' => $id), array('%d'));
                }
                wp_redirect(admin_url('admin.php?page=' . $_REQUEST['page']));
                exit;
            }
        }
    }
    
    function prepare_items() {
        global $wpdb;
        
        $per_page = $this->get_items_per_page('rrze_lc_per_page', 20);

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->process_bulk_action();

        $current_page = $this->get_pagenum();
        
        $search = isset($_GET['s']) ? $_GET['s'] : '';
               
        $where = $search ? $wpdb->prepare("WHERE post_title LIKE %s OR url LIKE %s OR text LIKE %s", '%' . $search . '%', '%' . $search . '%', '%' . $search . '%') : '';
        
        $total_items = $wpdb->get_var(sprintf("SELECT COUNT(*) FROM %s %s", $wpdb->prefix . RRZE_LC_ERRORS_TABLE, $where));
        $total_pages = ceil($total_items / $per_page);
        
        $this->set_pagination_args(array(
            'total_items' => $total_items, // Gesamtzahl der Elemente
            'per_page' => $per_page, // Wie viele Elemente werden auf einer Seite angezeigt
            'total_pages' => $total_pages   // Gesamtzahl der Seiten
        ));
                
        $order_by = isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'text';
        $order = isset($_REQUEST['order']) ? $_REQUEST['order'] : 'ASC';
        
        $query = sprintf("SELECT * FROM %s %s ORDER BY %s %s LIMIT %s, %s", $wpdb->prefix . RRZE_LC_ERRORS_TABLE, $where, $order_by, strtoupper($order), ($current_page - 1) * $per_page, $per_page);
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
