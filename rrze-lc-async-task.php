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
