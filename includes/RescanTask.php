<?php

namespace RRZE\LinkChecker;

defined('ABSPATH') || exit;

use RRZE\LinkChecker\AsyncTask;

class RescanTask extends AsyncTask
{
    /**
     * [protected description]
     * @var string
     */
    protected $action = 'rrze_lc_rescan_task';

    /**
     * Prepare any data to be passed to the asynchronous postback.
     * @param  array $data The raw data received by the launch method
     */
    protected function prepareData($data)
    {
    }

    /**
     * Run the do_action function for the asynchronous postback.
     */
    protected function runAction()
    {
        do_action("wp_async_$this->action");
    }
}
