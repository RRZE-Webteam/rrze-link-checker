<?php

namespace RRZE\LinkChecker;

defined('ABSPATH') || exit;

use RRZE\LinkChecker\HelpMenu;
use RRZE\LinkChecker\ListTable;
use RRZE\LinkChecker\Util;

class Settings
{
    /**
     * [protected description]
     * @var string
     */
    protected $optionName;

    /**
     * [protected description]
     * @var object
     */
    protected $options;

    /**
     * [protected description]
     * @var string
     */
    protected $settingsScreenId;

    /**
     * [__construct description]
     */
    public function __construct()
    {
        $this->optionName = Options::getOptionName();
        $this->options = Options::getOptions();

        add_action(
            'admin_menu',
            [$this, 'adminMenuPage']
        );

        add_action(
            'admin_init',
            [$this, 'adminSettings']
        );

        add_action(
            'admin_init',
            [$this, 'adminSubmit']
        );

        add_filter(
            'set-screen-option',
            [$this, 'setScreenOption'],
            10,
            3
        );

        add_filter(
            'plugin_action_links_' . plugin_basename(RRZE_PLUGIN_FILE),
            [$this, 'pluginActionLink']
        );
    }

    /**
     * [adminMenuPage description]
     */
    public function adminMenuPage()
    {
        $this->settingsScreenId = add_menu_page(
            __('Link-Checker', 'rrze-link-checker'),
            __('Link-Checker', 'rrze-link-checker'),
            'publish_posts',
            'rrze-link-checker',
            [
                $this,
                'listTablePage'
            ],
            'dashicons-editor-unlink'
        );

        add_submenu_page(
            'rrze-link-checker',
            __('Fehlerhafte Links', 'rrze-link-checker'),
            __('Fehlerhafte Links', 'rrze-link-checker'),
            'publish_posts',
            'rrze-link-checker',
            [
                $this,
                'listTablePage'
            ]
        );

        add_submenu_page(
            'rrze-link-checker',
            __('Einstellungen', 'rrze-link-checker'),
            __('Einstellungen', 'rrze-link-checker'),
            'manage_options',
            'rrze-link-checker-settings',
            [
                $this,
                'settingsPage'
            ]
        );

        add_action(
            'load-' . $this->settingsScreenId,
            [
                $this,
                'adminHelpMenu'
            ]
        );

        add_action(
            'admin_notices',
            [
                $this,
                'adminNotices'
            ],
            99
        );
    }

    public function listTablePage()
    {
        global $wpdb, $pagenow;

        $page = $_REQUEST['page'];
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        $id = isset($_GET['id']) ? absint($_GET['id']) : '';

        if ($action == 'ignore' && $id) {
            $wpdb->update(
                $wpdb->prefix . DB::getErrorsTableName(),
                ['error_status' => 'ignore'],
                ['error_id' => $id],
                ['%s'],
                ['%d']
            );
        } elseif ($action == 'unignore' && $id) {
            $wpdb->update(
                $wpdb->prefix . DB::getErrorsTableName(),
                ['error_status' => null],
                ['error_id' => $id],
                ['%s'],
                ['%d']
            );
            wp_redirect(
                add_query_arg(
                    [
                        'page' => $page,
                        'view' =>'ignore'
                    ],
                    admin_url($pagenow)
                )
            );
            exit;
        }

        $listTable = new ListTable();
        $listTable->prepare_items(); ?>
        <div class="wrap">
            <h2><?php _e('Fehlerhafte Links', 'rrze-link-checker'); ?></h2>
            <form method="get">
                <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>">
                <?php
                $listTable->search_box(__('Suche', 'rrze-link-checker'), 's'); ?>
            </form>
            <?php $listTable->views(); ?>
            <form method="get">
                <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
                <?php $listTable->display(); ?>
            </form>
        </div>
        <?php
    }

    public function settingsPage()
    {
        ?>
        <div class="wrap">
            <h2><?php echo esc_html(__('Link-Checker &rsaquo; Einstellungen', 'rrze-link-checker')); ?></h2>
            <form method="post">
                <?php
                settings_fields($this->optionName . '_settings');
        do_settings_sections($this->optionName . '_settings');
        submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function adminSettings()
    {
        add_settings_section(
            $this->optionName . '_settings_section',
            false,
            '__return_false',
            $this->optionName . '_settings'
        );

        add_settings_field(
            'post_types',
            __('Dokumentenart', 'rrze-link-checker'),
            [$this, 'post_types_field'],
            $this->optionName . '_settings',
            $this->optionName . '_settings_section'
        );

        add_settings_field(
            'post_status',
            __('Status', 'rrze-link-checker'),
            [$this, 'post_status_field'],
            $this->optionName . '_settings',
            $this->optionName . '_settings_section'
        );

        add_settings_field(
            'http_request_timeout',
            __('HTTP-Anfrage-Timeout', 'rrze-link-checker'),
            [$this, 'http_request_timeout_field'],
            $this->optionName . '_settings',
            $this->optionName . '_settings_section'
        );
    }

    public function post_types_field()
    {
        $postTypes = [];
        foreach ($this->getAvailablePostTypes() as $key => $postType) {
            $postTypes[$key] = $postType->labels->name;
        } ?>
        <?php foreach ($postTypes as $key => $name) : ?>
        <input <?php checked(in_array($key, $this->options->post_types), true); ?> type="checkbox" name="<?php echo $this->optionName; ?>[post_types][<?php echo $key; ?>]" id="rrze-lc-post-type-<?php echo $key; ?>" value="1" />
        <label for="rrze-lc-post-type-<?php echo $key; ?>"><?php echo $name; ?> (<code><?php echo $key; ?></code>)</label><br />
        <?php endforeach;
    }

    public function post_status_field()
    {
        $postStatus = $this->getAvailablePostStatus(); ?>
        <?php foreach ($postStatus as $status) : ?>
        <input <?php checked(in_array($status, $this->options->post_status), true); ?> type="checkbox" name="<?php echo $this->optionName; ?>[post_status][<?php echo $status; ?>]" id="rrze-lc-post-status-<?php echo $status; ?>" value="1" />
        <label for="rrze-lc-post-status-<?php echo $status; ?>"><?php echo $this->getPostStatusName($status); ?></label><br />
        <?php endforeach;
    }

    public function http_request_timeout_field()
    {
        ?>
        <label for="rrze-lc-http-request-timeout">
            <input type="number" min="5" max="30" step="1" name="<?php echo $this->optionName; ?>[http_request_timeout]" value="<?php echo $this->options->http_request_timeout; ?>" class="small-text">
            <?php echo esc_html(_nx('Sekunde', 'Sekunden', $this->options->http_request_timeout, 'rrze-lc-http-request-timeout', 'rrze-link-checker')) ?>
        </label>
        <?php
    }

    public function adminSubmit()
    {
        $page = $this->getParam('page');

        if ($page != 'rrze-link-checker-settings') {
            return;
        }

        $optionPage = $this->getParam('option_page');
        $nonce = $this->getParam('_wpnonce');

        if (!$nonce || !wp_verify_nonce($nonce, "$optionPage-options")) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        if ($this->validateSettings()) {
            do_action('rrze_lc_rescan_task');
            $updated = __('Einstellungen gespeichert.', 'rrze-link-checker');
            set_transient(
                $this->getTransientHash(),
                ['updated' => $updated],
                30
            );
        }

        wp_redirect($this->OptionsUrl(array('page' => $page)));
        exit();
    }

    public function validateSettings()
    {
        $errors = [];
        $input = (array) $this->getParam($this->optionName);

        if (empty($input['post_types'])) {
            $input['post_types'] = [];
        }

        $postTypes = $this->cleanPostTypesOption($input['post_types']);
        if (empty($postTypes)) {
            $errors[] = __('Der Dokumentenart-Feld ist erforderlich.', 'rrze-link-checker');
        }

        if (empty($input['post_status'])) {
            $input['post_status'] = [];
        }

        $postStatus = $this->cleanPostStatusOption($input['post_status']);
        if (empty($postStatus)) {
            $errors[] = __('Der Status-Feld ist erforderlich.', 'rrze-link-checker');
        }

        if (empty($input['http_request_timeout'])) {
            $input['http_request_timeout'] = 5;
        }

        $httpRequestTimeout = absint($input['http_request_timeout']);
        $httpRequestTimeout = $httpRequestTimeout < 5 || $httpRequestTimeout > 30 ? 5 : $httpRequestTimeout;

        if (!empty($errors)) {
            set_transient(
                $this->getTransientHash(),
                ['error' => $errors],
                30
            );
            return false;
        }

        $this->options->post_types = $postTypes;
        $this->options->post_status = $postStatus;
        $this->options->http_request_timeout = $httpRequestTimeout;

        update_option($this->optionName, $this->options);
        return true;
    }

    /**
     * [adminHelpMenu description]
     */
    public function adminHelpMenu()
    {
        new HelpMenu($this->settingsScreenId);
    }

    /**
     * [ScreenOptions description]
     */
    public function ScreenOptions()
    {
        $option = 'per_page';
        $args = [
            'label' => __('Number of items per page:', 'rrze-link-checker'),
            'default' => 20,
            'option' => 'rrze_lc_per_page'
        ];

        add_screen_option($option, $args);
    }

    /**
     * [setScreenOption description]
     * @param  string  $status [description]
     * @param  string  $option [description]
     * @param  integer $value  [description]
     * @return string          [description]
     */
    public function setScreenOption($status, $option, $value)
    {
        if ('links_per_page' == $option) {
            return $value;
        }
        return $status;
    }

    /**
     * [pluginActionLink description]
     * @param  array $links [description]
     * @return array        [description]
     */
    public function pluginActionLink($links)
    {
        if (! current_user_can('manage_options')) {
            return $links;
        }
        return array_merge(
            $links,
            [
                sprintf(
                    '<a href="%1$s">%2$s</a>',
                    admin_url('options-general.php?page=rrze-link-checker'),
                    __('Settings', 'rrze-link-checker')
                )
            ]
        );
    }

    protected function cleanPostTypesOption($postTypes = [], $postTypeSupport = null)
    {
        $normalizedPostTypesOption = [];
        $customPostTypes = wp_list_pluck($this->getCustomPostTypes(), 'name');

        $AllPostTypes = array_merge($customPostTypes, array_keys($postTypes));

        foreach ($AllPostTypes as $postType) {
            if (!empty($postTypes[$postType]) || post_type_supports($postType, $postTypeSupport)) {
                $normalizedPostTypesOption[] = $postType;
            }
        }
        return $normalizedPostTypesOption;
    }

    protected function cleanPostStatusOption($postStatus = [])
    {
        $normalizedPostStatusOption = [];
        $available_post_status = $this->getAvailablePostStatus();

        foreach ($available_post_status as $status) {
            if (!empty($postStatus[$status])) {
                $normalizedPostStatusOption[] = $status;
            }
        }
        return $normalizedPostStatusOption;
    }

    protected function getAvailablePostTypes($postType = null)
    {
        $AllPostTypes = [];

        $buildinPostTypes = $this->getBuildinPostTypes();
        $AllPostTypes['post'] = $buildinPostTypes['post'];
        $AllPostTypes['page'] = $buildinPostTypes['page'];

        $customPostTypes = $this->getCustomPostTypes();
        if (count($customPostTypes)) {
            foreach ($customPostTypes as $custom_post_type => $args) {
                $AllPostTypes[$custom_post_type] = $args;
            }
        }

        if (!is_null($postType) && isset($AllPostTypes[$postType])) {
            return $AllPostTypes[$postType];
        }

        return $AllPostTypes;
    }

    protected function getBuildinPostTypes()
    {
        $args = [
            '_builtin' => true,
            'public' => true,
        ];

        return get_post_types($args, 'objects');
    }

    protected function getCustomPostTypes()
    {
        $args = [
            '_builtin' => false,
            'public' => true,
        ];

        return get_post_types($args, 'objects');
    }

    protected function getAvailablePostStatus()
    {
        $allowedPostStatus = [
            'publish',
            'future',
            'draft',
            'pending',
            'private',
            'trash'
        ];
        $postStatus = apply_filters('rrze_lc_allowed_post_status', $allowedPostStatus);

        return (empty($postStatus) && !is_array($postStatus)) ? false : array_intersect($postStatus, $allowedPostStatus);
    }

    protected function getPostStatusName($status)
    {
        $statusName = __('Unbekannt', 'rrze-link-checker');

        $builtinStatus = [
            'publish' => __('Veröffentlicht', 'rrze-link-checker'),
            'draft' => __('Entwurf', 'rrze-link-checker'),
            'future' => __('Geplant', 'rrze-link-checker'),
            'private' => __('Privat', 'rrze-link-checker'),
            'pending' => __('Ausstehender Review', 'rrze-link-checker'),
            'trash' => __('Papierkorb', 'rrze-link-checker'),
        ];

        if (array_key_exists($status, $builtinStatus)) {
            $statusName = $builtinStatus[$status];
        }

        return $statusName;
    }

    public function adminNotices()
    {
        $page = $this->getParam('page');

        if ($page != 'rrze-link-checker-settings') {
            return;
        }

        $hash = $this->getTransientHash();

        if (!$notices = get_transient($hash)) {
            return;
        }

        delete_transient($hash);

        if (!empty($notices['error']) && is_array($notices['error'])) {
            echo '<div class="error">' . PHP_EOL;
            echo '<p>' . PHP_EOL;
            foreach ($notices['error'] as $error) {
                echo $error . '<br />' . PHP_EOL;
            }
            echo '</p>' . PHP_EOL;
            echo '</div>' . PHP_EOL;
        } elseif (!empty($notices['updated']) && is_string($notices['updated'])) {
            echo '<div class="updated">' . PHP_EOL;
            echo '<p>' . $notices['updated'] . '</p>' . PHP_EOL;
            echo '</div>' . PHP_EOL;
        }
    }

    protected function getTransientHash()
    {
        return md5(sprintf('RRZE_LC_%s_%s', get_the_ID(), get_current_user_id()));
    }

    protected function getParam($param, $default = '')
    {
        return Util::getParam($param, $default);
    }

    protected function OptionsUrl($atts = [])
    {
        return Util::OptionsUrl($atts);
    }

    protected function checkUrls($postId = null, $maxCheckUrls = null)
    {
        return Util::checkUrls($postId, $maxCheckUrls);
    }
}
