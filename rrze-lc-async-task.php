<?php

class RRZE_LC_Scan_Task extends WP_Async_Task {

    protected $action = 'rrze_lc_scan_task';

    protected function prepare_data($data) {}

    protected function run_action() {
        do_action("wp_async_$this->action");
    }

}

class RRZE_LC_Update_Settings_Task extends WP_Async_Task {

    protected $action = 'rrze_lc_update_settings_task';

    protected function prepare_data($data) {}

    protected function run_action() {
        do_action("wp_async_$this->action");
    }

}

class RRZE_LC_Save_Post_Task extends WP_Async_Task {

    protected $action = 'save_post';

    protected function prepare_data($data) {
        $post_id = $data[0];
        return array('post_id' => $post_id);
    }

    protected function run_action() {
        $post_id = $_POST['post_id'];
        $post = get_post($post_id);
        if ($post) {
            do_action("wp_async_$this->action", $post->ID);
        }
    }

}
