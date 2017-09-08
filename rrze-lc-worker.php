<?php

class RRZE_LC_Worker {
    
    public static function scan() {
        global $wpdb;
                
        RRZE_LC::setup_db_tables();
        
        $timestamp = current_time('timestamp');
        
        update_option(RRZE_LC_OPTION_NAME_SCAN_TIMESTAMP, $timestamp);
        
        $where = $wpdb->prepare("WHERE checked < %s", date('Y-m-d H:i:s', $timestamp));
        
        $options = RRZE_LC::$options;

        $post_types = $options->post_types;
        
        if(!empty($post_types)) {
            $where .= sprintf(' AND post_type IN (%s)', implode(',', array_map(create_function('$a', 'return "\'$a\'";'), $post_types)));
        }
        
        $post_status = $options->post_status;

        if(!empty($post_status)) {
            $where .= sprintf(' AND post_status IN (%s)', implode(',', array_map(create_function('$a', 'return "\'$a\'";'), $post_status)));
        }
        
        $posts = $wpdb->get_results(sprintf("SELECT ID FROM %s %s ORDER BY checked ASC", $wpdb->prefix . RRZE_LC_POSTS_TABLE, $where));
        
        foreach ($posts as $post) {       
            self::check_urls($post->ID);           
        }

    }
     
    public static function update_settings() {
        RRZE_LC::truncate_db_tables();
        RRZE_LC::setup_db_tables();
        self::scan();
    }
            
    public static function rescan_post($post_id = NULL) {
        self::check_urls($post_id);
    }
    
    private static function check_urls($post_id = NULL) {
        global $wpdb;
        
        if(empty($post_id)) {
            return;
        }
        
        $wpdb->update( 
            $wpdb->prefix . RRZE_LC_POSTS_TABLE, 
            array( 
                'checked' => current_time('mysql')
            ), 
            array(
                'ID' => $post_id
            ), 
            array( 
                '%s'
            ), 
            array(
                '%d'
            )
        );

        $wpdb->delete(
            $wpdb->prefix . RRZE_LC_ERRORS_TABLE,
            array(
                'post_id' => $post_id,
                'error_status' => NULL
            ),
            array(
                '%d',
                '%s'
            )
        );
        
        $errors = RRZE_LC_Helper::check_urls($post_id);

        if(empty($errors)) {
            return;
        }

        foreach($errors as $error) {
            $error = (object) $error;

            $status_query = $wpdb->prepare("SELECT 1 FROM " . $wpdb->prefix . RRZE_LC_ERRORS_TABLE . " WHERE url = %s AND error_status IS NOT NULL", $error->url);
            if ($wpdb->get_row($status_query)) {
                continue;
            }

            $wpdb->insert( 
                $wpdb->prefix . RRZE_LC_ERRORS_TABLE, 
                array( 
                    'post_id' => $post_id, 
                    'post_title' => $error->post_title,
                    'url' => $error->url,
                    'text' => $error->text,
                    'http_status_code' => $error->http_status_code,
                    'error_status' => $error->error_status
                ), 
                array( 
                    '%d', 
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%s'
                ) 
            );               
        }        
        
    }
    
}
