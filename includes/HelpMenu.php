<?php

namespace RRZE\LinkChecker;

defined('ABSPATH') || exit;

class HelpMenu
{
    /**
     * [protected description]
     * @var string
     */
    protected $screenMenuId;

    /**
     * [__construct description]
     * @param string $screenMenuId [description]
     */
    public function __construct($screenMenuId = '')
    {
        $this->screenMenuId = $screenMenuId;
        $this->setMenu();
    }

    /**
     * [setMenu description]
     * @return mixed [description]
     */
    protected function setMenu()
    {
        $content = [
             '<p>' . __('Here comes the Context Help content.', 'rrze-link-checker') . '</p>',
         ];
        $help_tab = [
             'id' => $this->screenMenuId,
             'title' => __('Overview', 'rrze-link-checker'),
             'content' => implode(PHP_EOL, $content),
         ];
        $help_sidebar = sprintf(
            '<p><strong>%1$s:</strong></p><p><a href="https://blogs.fau.de/webworking">RRZE-Webworking</a></p><p><a href="https://github.com/RRZE-Webteam">%2$s</a></p>',
            __('For more information', 'rrze-link-checker'),
            __('RRZE Webteam on Github', 'rrze-link-checker')
         );

        $screen = get_current_screen();

        if ($screen->id != $this->screenMenuId) {
            return;
        }

        $screen->add_help_tab($help_tab);
        $screen->set_help_sidebar($help_sidebar);
    }
}
