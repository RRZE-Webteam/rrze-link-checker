<?php

namespace RRZE\LinkChecker;

defined('ABSPATH') || exit;

use RRZE\LinkChecker\AsyncTask;

class SavePostTask extends AsyncTask {
    /**
     * [protected description]
     * @var string
     */
    protected $action = 'save_post';

    /**
     * Prepare any data to be passed to the asynchronous postback.
     * @param  array $data The raw data received by the launch method
     * @return array The prepared data
     */
    protected function prepareData($data) {
        $postId = $data[0];
        return array('post_id' => $postId);
    }

    /**
     * Run the do_action function for the asynchronous postback.
     */
    protected function runAction() {
        $postId = $_POST['post_id'];
        $post = get_post($postId);
        if ($post) {
            do_action("wp_async_$this->action", $post->ID);
        }
    }

}
