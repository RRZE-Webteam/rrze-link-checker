<?php

/**
 * Plugin Name:     RRZE-Link-Checker
 * Plugin URI:      https://github.com/RRZE-Webteam/rrze-cf7-redirect
 * Description:     Überprüfung auf defekte Links.
 * Version:         1.3.0
 * Author:          RRZE-Webteam
 * Author URI:      https://blogs.fau.de/webworking/
 * License:         GNU General Public License v2
 * License URI:     http://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path:     /languages
 * Text Domain:     rrze-link-checker
 */

require_once(dirname(__FILE__) . '/rrze-lc-constants.php');

add_action('plugins_loaded', array('RRZE_LC', 'instance'));

register_activation_hook(__FILE__, array('RRZE_LC', 'activation'));
register_deactivation_hook(__FILE__, array('RRZE_LC', 'deactivation'));

class RRZE_LC {
    /*
     * Optionen des Plugins
     * object
     */
    public static $options;
    
    /*
     * Bezieht sich auf eine einzige Instanz dieser Klasse.
     * mixed
     */
    protected static $instance = null;

    /*
     * Erstellt und gibt eine Instanz der Klasse zurück.
     * Es stellt sicher, dass von der Klasse genau ein Objekt existiert (Singleton Pattern).
     * @return object
     */
    public static function instance() {

        if (null == self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /*
     * Initialisiert das Plugin, indem die Lokalisierung, Hooks und Verwaltungsfunktionen festgesetzt werden.
     * @return void
     */
    private function __construct() {
        // Sprachdateien werden eingebunden.
        self::load_textdomain();
        
        // Enthaltene Optionen.
        self::$options = self::get_options();
                
        require_once(dirname(__FILE__) . '/wp-async-task.php');
        require_once(dirname(__FILE__) . '/rrze-lc-async-task.php');
        require_once(dirname(__FILE__) . '/rrze-lc-worker.php');
        require_once(dirname(__FILE__) . '/rrze-lc-helper.php');
        
        // Aktualisierung des Plugins (ggf).
        self::update_version();
        
        $this->async_task();
        
        add_action('admin_init', array($this, 'check_links'));
        add_action('delete_post', array($this, 'delete_post'));
        
        //add_action('contextual_help', array($this, 'post_edit_screen'));
        
        add_action('admin_menu', array($this, 'links_menu'));
        add_action('admin_init', array($this, 'links_settings'));
        add_action('admin_init', array($this, 'submit_settings'));
        
        add_action('wp_dashboard_setup', array($this, 'dashboard_setup'));
        
        add_filter('set-screen-option', array($this, 'list_table_set_option'), 10, 3);
    }

    // Einbindung der Sprachdateien.
    private static function load_textdomain() {    
        load_plugin_textdomain('rrze-link-checker', FALSE, sprintf('%s/languages/', dirname(plugin_basename(__FILE__))));
    }
    
    /*
     * Wird durchgeführt wenn das Plugin aktiviert wird.
     * @return void
     */
    public static function activation($networkwide) {        
        self::load_textdomain();
        
        self::system_requirements($networkwide);
        
        // Datenbanktabellen erstellen.
        self::create_db_tables();
        
        update_option(RRZE_LC_VERSION_OPTION_NAME, RRZE_LC_VERSION);
    }

    /*
     * Wird durchgeführt wenn das Plugin deaktiviert wird.
     * @return void
     */
    public static function deactivation() {
        delete_option(RRZE_LC_OPTION_NAME);
        delete_option(RRZE_LC_VERSION_OPTION_NAME);
        delete_option(RRZE_LC_OPTION_NAME_CRON_TIMESTAMP);
        delete_option(RRZE_LC_OPTION_NAME_SCAN_TIMESTAMP);
        
        self::drop_db_tables();
    }
    
    /*
     * Überprüft die Systemanforderungen.
     * @return void
     */
    private static function system_requirements($networkwide = 1) {
        $error = '';

        // Überprüft die minimal erforderliche PHP-Version.
        if (version_compare(PHP_VERSION, RRZE_LC_MIN_PHP_VERSION, '<')) {
            $error = sprintf(__('Ihre PHP-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die PHP-Version %s.', 'rrze-link-checker'), PHP_VERSION, RRZE_LC_MIN_PHP_VERSION);
        }

        // Überprüft die minimal erforderliche WP-Version.
        elseif (version_compare($GLOBALS['wp_version'], RRZE_LC_MIN_WP_VERSION, '<')) {
            $error = sprintf(__('Ihre Wordpress-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die Wordpress-Version %s.', 'rrze-link-checker'), $GLOBALS['wp_version'], RRZE_LC_MIN_WP_VERSION);
        }

        elseif (is_multisite() && $networkwide) {
            $error = __('Dieses Plugin kann nicht netzwerkweit aktiviert werden.', 'rrze-link-checker');
        }
        
        // Wenn die Überprüfung fehlschlägt, dann wird das Plugin automatisch deaktiviert.
        if (!empty($error)) {
            deactivate_plugins(plugin_basename(__FILE__), FALSE, TRUE);
            wp_die($error);
        }
    }

    private static function create_db_tables() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();

        dbDelta(sprintf("CREATE TABLE IF NOT EXISTS %s (
                ID bigint(20) unsigned NOT NULL,
                post_type varchar(20) NOT NULL,
                post_status varchar(20) NOT NULL,
                checked datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                PRIMARY KEY  (ID)) %s;", $wpdb->prefix . RRZE_LC_POSTS_TABLE, $charset_collate));

        dbDelta(sprintf("CREATE TABLE IF NOT EXISTS %s (
                error_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                post_id bigint(20) unsigned NOT NULL,
                post_title text NOT NULL,               
                url text(250) DEFAULT '' NOT NULL,
                text text(250) DEFAULT '' NOT NULL,
                http_status_code int(10) UNSIGNED DEFAULT NULL,
                error_status varchar(20) DEFAULT NULL,
                PRIMARY KEY  (error_id),
                KEY post_id (post_id),
                KEY http_status_code (http_status_code),
                KEY error_status (error_status)) %s;", $wpdb->prefix . RRZE_LC_ERRORS_TABLE, $charset_collate));
    }

    public static function setup_db_tables() {
        global $wpdb;
                
        $row = $wpdb->get_row(sprintf("SELECT ID FROM %s", $wpdb->prefix . RRZE_LC_POSTS_TABLE));
        if(!empty($row)) {
            return;
        }
        
        $post_types = self::$options->post_types;
        $post_status = self::$options->post_status;
        
        if(empty($post_types) || empty($post_status)) {
            return;
        }
       
        $pt_arry = array();
        foreach ($post_types as $value) {
            $pt_arry[] = $wpdb->prepare("post_type = %s", $value);
        }

        $pt_sql = implode(' OR ', $pt_arry);

        $ps_arry = array();
        foreach ($post_status as $value) {
            $ps_arry[] = $wpdb->prepare("post_status = %s", $value);
        }

        $ps_sql = implode(' OR ', $ps_arry);
        
        $wpdb->query(sprintf("INSERT INTO %s (ID, post_type, post_status) SELECT ID, post_type, post_status FROM $wpdb->posts WHERE (%s) AND (%s) ORDER BY ID ASC", $wpdb->prefix . RRZE_LC_POSTS_TABLE, $pt_sql, $ps_sql));        
    }
    
    public static function truncate_db_tables() {
        global $wpdb;

        $wpdb->query(sprintf("TRUNCATE TABLE %s;", $wpdb->prefix . RRZE_LC_POSTS_TABLE));
        $wpdb->query(sprintf("TRUNCATE TABLE %s;", $wpdb->prefix . RRZE_LC_ERRORS_TABLE));
    }
    
    private static function drop_db_tables() {
        global $wpdb;

        $wpdb->query(sprintf("DROP TABLE IF EXISTS %s;", $wpdb->prefix . RRZE_LC_POSTS_TABLE));
        $wpdb->query(sprintf("DROP TABLE IF EXISTS %s;", $wpdb->prefix . RRZE_LC_ERRORS_TABLE));
    }
        
    public static function update_version() {
        global $wpdb;

        if (version_compare(get_option(RRZE_LC_VERSION_OPTION_NAME, 0), '1.3.0', '<')) {
            update_option(RRZE_LC_VERSION_OPTION_NAME, RRZE_LC_VERSION);
            $wpdb->query("ALTER TABLE " . $wpdb->prefix . RRZE_LC_ERRORS_TABLE . " ADD error_status VARCHAR(20) NULL DEFAULT NULL AFTER text, ADD INDEX error_status (error_status)");
            $wpdb->query("ALTER TABLE " . $wpdb->prefix . RRZE_LC_ERRORS_TABLE . " ADD http_status_code INT UNSIGNED NULL DEFAULT NULL AFTER text, ADD INDEX http_status_code (http_status_code)");           
            delete_option(RRZE_LC_OPTION_NAME_CRON_TIMESTAMP);
            delete_option(RRZE_LC_OPTION_NAME_SCAN_TIMESTAMP);
            self::truncate_db_tables();           
        }
    }

    /*
     * Standard Einstellungen werden definiert
     * @return array
     */
    private static function default_options() {
        $options = array(
            'post_types' => array('post', 'page'),
            'post_status' => array('publish'),
        );

        return apply_filters('rrze_lc_default_options', $options);
    }

    /*
     * Gibt die Einstellungen zurück.
     * @return object
     */
    private static function get_options() {
        $defaults = self::default_options();

        $options = (array) get_option(RRZE_LC_OPTION_NAME);
        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);

        return (object) $options;
    }
    
    public function check_links() {
        $timestamp = time();
        
        if (get_option(RRZE_LC_OPTION_NAME_CRON_TIMESTAMP) < $timestamp) {
            update_option(RRZE_LC_OPTION_NAME_CRON_TIMESTAMP, $timestamp + RRZE_LC_CRON_INTERVAL);
            do_action('rrze_lc_scan_task');
        }
    }

    public function async_task() {
        $scan_task = new RRZE_LC_Scan_Task();
        add_action('wp_async_rrze_lc_scan_task', array($this, 'scan_task'));
        
        $update_settings_task = new RRZE_LC_Update_Settings_Task();
        add_action('wp_async_rrze_lc_update_settings_task', array($this, 'update_settings_task'));
        
        $save_post_task = new RRZE_LC_Save_Post_Task();
        add_action('wp_async_save_post', array($this, 'save_post'));
    }
    
    public function scan_task() {
        RRZE_LC_Worker::scan();
    }

    public function update_settings_task() {
        RRZE_LC_Worker::update_settings();
    }
    
    public function post_types_setting() {
        $post_types = RRZE_LC_Helper::get_post_types();

        foreach($post_types as $key => $post_type) {
            echo '<label for="post_types_' . esc_attr($post_type) . '">';
            echo '<input id="post_types_' . esc_attr($post_type) . '" name="' . RRZE_LC_OPTION_NAME . '[post_types][' . esc_attr($post_type) . ']"';

            if ( isset(self::$options->post_types[$post_type])) {
                checked(self::$options->post_types[$post_type], true);
            }

            echo ' type="checkbox" />&nbsp;&nbsp;&nbsp;' . esc_html($args->label) . '</label>';			
            echo '<br>';
        }
        
    }
    
    public function options_link_checker() {
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2><?php echo esc_html(__('Einstellungen &rsaquo; Link-Checker', 'rrze-link-checker')); ?></h2>

            <form method="post" action="options.php">
                <?php
                settings_fields(RRZE_LC_OPTION_NAME . '_settings');
                do_settings_sections(RRZE_LC_OPTION_NAME . '_settings');
                submit_button();
                ?>
            </form>            
        </div>
        <?php
    }
    
    public function links_menu() {
        if (!class_exists('WP_List_Table')) {
            require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
        }

        require(RRZE_LC_ROOT . '/rrze-lc-list-table.php');
        
        $links_page = add_menu_page(__('Link-Checker', 'rrze-link-checker'), __('Link-Checker', 'rrze-link-checker'), 'manage_options', 'rrze-link-checker', array($this, 'links_page'), 'dashicons-editor-unlink');
        add_submenu_page('rrze-link-checker', __('Fehlerhafte Links', 'rrze-link-checker'), __('Fehlerhafte Links', 'rrze-link-checker'), 'manage_options', 'rrze-link-checker', array($this, 'links_page'));
        add_action("load-{$links_page}", array($this, 'load_links_page'));
        add_action("load-{$links_page}", array($this, 'links_screen_options'));

        add_submenu_page('rrze-link-checker', __('Einstellungen', 'rrze-link-checker'), __('Einstellungen', 'rrze-link-checker'), 'manage_options', 'rrze-link-checker-settings', array($this, 'settings_page'));
        
        add_action('admin_notices', array($this, 'settings_admin_notices'), 99);
    }

    public function links_page() {
        global $wpdb, $pagenow;
        
        $page = $_REQUEST['page'];
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        $id = isset($_GET['id']) ? absint($_GET['id']) : '';

        if ($action == 'ignore' && $id) {
            $wpdb->update($wpdb->prefix . RRZE_LC_ERRORS_TABLE, array('error_status' => 'ignore'), array('error_id' => $id), array('%s'), array('%d'));
        } elseif ($action == 'unignore' && $id) {
            $wpdb->update($wpdb->prefix . RRZE_LC_ERRORS_TABLE, array('error_status' => NULL), array('error_id' => $id), array('%s'), array('%d'));
            wp_redirect(add_query_arg(array('page' => $page, 'view' =>'ignore'), admin_url($pagenow)));
            exit;
        }

        $list_table = new RRZE_LC_List_Table();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h2><?php _e('Fehlerhafte Links', 'rrze-link-checker'); ?></h2>
            <form method="get">
                <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>">
                <?php
                $list_table->search_box(__('Suche', 'rrze-link-checker'), 's');
                ?>
            </form>
            <?php $list_table->views(); ?>
            <form method="get">
                <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php
    }
    
    public function settings_page() {
        ?>
        <div class="wrap">
            <h2><?php echo esc_html(__('Link-Checker &rsaquo; Einstellungen', 'rrze-link-checker')); ?></h2>
            <form method="post">
                <?php
                settings_fields(RRZE_LC_OPTION_NAME . '_settings');
                do_settings_sections(RRZE_LC_OPTION_NAME . '_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function load_links_page() {
        
    }

    public function links_screen_options() {
        new RRZE_LC_List_Table();

        $option = 'per_page';
        $args = array(
            'label' => __('Einträge pro Seite:', 'rrze-link-checker'),
            'default' => 20,
            'option' => 'rrze_lc_per_page'
        );

        add_screen_option($option, $args);
    }
    
    public function list_table_set_option($status, $option, $value) {
        if ('links_per_page' == $option ) {
            return $value;
        }
        return $status;
    }
    
    public function links_settings() {
        add_settings_section(RRZE_LC_OPTION_NAME . '_settings_section', FALSE, '__return_false', RRZE_LC_OPTION_NAME . '_settings');
        add_settings_field('post_types', __('Dokumentenart', 'rrze-link-checker'), array($this, 'post_types_field'), RRZE_LC_OPTION_NAME . '_settings', RRZE_LC_OPTION_NAME . '_settings_section');
        add_settings_field('post_status', __('Status', 'rrze-link-checker'), array($this, 'post_status_field'), RRZE_LC_OPTION_NAME . '_settings', RRZE_LC_OPTION_NAME . '_settings_section');
    }
    
    public function post_types_field() {
        $post_types = array();
        foreach ($this->get_available_post_types() as $key => $post_type) {
            $post_types[$key] = $post_type->labels->name;
        }
        ?>
        <?php foreach ($post_types as $key => $name) : ?>
        <input <?php checked(in_array($key, self::$options->post_types), TRUE); ?> type="checkbox" name="<?php echo RRZE_LC_OPTION_NAME; ?>[post_types][<?php echo $key; ?>]" id="rrze-lc-post-type-<?php echo $key; ?>" value="1" />
        <label for="rrze-lc-post-type-<?php echo $key; ?>"><?php echo $name; ?> (<code><?php echo $key; ?></code>)</label><br />
        <?php endforeach;
    }
    
    public function post_status_field() {
        $post_status = $this->get_available_post_status();
        ?>
        <?php foreach ($post_status as $status) : ?>
        <input <?php checked(in_array($status, self::$options->post_status), TRUE); ?> type="checkbox" name="<?php echo RRZE_LC_OPTION_NAME; ?>[post_status][<?php echo $status; ?>]" id="rrze-lc-post-status-<?php echo $status; ?>" value="1" />
        <label for="rrze-lc-post-status-<?php echo $status; ?>"><?php echo $this->get_post_status_name($status); ?></label><br />
        <?php endforeach;
    }

    public function submit_settings() {
        $page = $this->get_param('page');

        if($page != 'rrze-link-checker-settings') {
            return;
        }
        
        $option_page = $this->get_param('option_page');
        $nonce = $this->get_param('_wpnonce');

        if ($nonce) {
            if ($nonce && !wp_verify_nonce($nonce, "$option_page-options")) {
                wp_die(__('Schummeln, was?', 'rrze-link-checker'));
            }

            if (!current_user_can('manage_options')) {
                wp_die(__('Sie haben nicht die erforderlichen Rechte, um diese Aktion durchzuführen.', 'rrze-link-checker'));
            }

            if ($this->validate_settings()) {
                do_action('rrze_lc_update_settings_task');
                $updated = __('Einstellungen gespeichert.', 'rrze-link-checker');
                set_transient($this->transient_hash(), array('updated' => $updated), 30);
            }

            wp_redirect($this->options_url(array('page' => $page)));
            exit();
        }
        
    }
       
    public function validate_settings() {
        $errors = [];
        $input = (array) $this->get_param(RRZE_LC_OPTION_NAME);

        if (empty($input['post_types'])) {
            $input['post_types'] = array();
        }

        $input['post_types'] = $this->clean_post_type_options($input['post_types']);        
        if (empty($input['post_types'])) {
            $errors[] = __('Der Dokumentenart-Feld ist erforderlich.', 'rrze-link-checker');
        }

        if (empty($input['post_status'])) {
            $input['post_status'] = array();
        }

        $input['post_status'] = $this->clean_post_status_options($input['post_status']);        
        if (empty($input['post_status'])) {
            $errors[] = __('Der Status-Feld ist erforderlich.', 'rrze-link-checker');
        }
        
        if(!empty($errors)) {
            set_transient($this->transient_hash(), array('error' => $errors), 30);
            return FALSE;
        }

        self::$options->post_types = $input['post_types'];
        self::$options->post_status = $input['post_status'];
        
        update_option(RRZE_LC_OPTION_NAME, (array) self::$options);

        return TRUE;
    }
    
    public function dashboard_setup() {
        if (current_user_can('edit_others_posts')) {
            wp_add_dashboard_widget('_rrze_link_checker_dashboard_widget', __('Link-Checker', 'rrze-link-checker'), array($this, 'dashboard_widget'));
        }
    }

    public function post_edit_screen() {
        global $wpdb;
        
        $current_screen = get_current_screen();

        if ($current_screen->base != 'post' || $current_screen->parent_base != 'edit') {
            return;
        }
        
        $post = get_post();
        
        $errors = $wpdb->get_results(sprintf("SELECT url, text FROM %s WHERE post_id = %d", $wpdb->prefix . RRZE_LC_ERRORS_TABLE, $post->ID));

        if(!empty($errors)) {
            set_transient($this->transient_hash(), $errors, 30);
        }
        
        add_action('admin_notices', array($this, 'post_admin_notices'), 99);
    }
    
    public function save_post($post_id) {
        global $wpdb;

        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) OR (defined('DOING_CRON') && DOING_CRON) OR (defined('DOING_AJAX') && DOING_AJAX) OR (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) ) {
            return;
        }
        
        $post_type = get_post_type($post_id);

        $wpdb->delete(
            $wpdb->prefix . RRZE_LC_ERRORS_TABLE,
            array(
                'post_id' => $post_id
            ),
            array(
                '%d'
            )
        );

        if(in_array($post_type, self::$options->post_types)) {
            $wpdb->replace( 
                $wpdb->prefix . RRZE_LC_POSTS_TABLE, 
                array( 
                    'ID' => $post_id,
                    'checked' => '0000-00-00 00:00:00'
                ), 
                array( 
                    '%d',
                    '%s'
                ) 
            );
            
            RRZE_LC_Worker::rescan_post($post_id);
        } else {
            $wpdb->delete(
                $wpdb->prefix . RRZE_LC_POSTS_TABLE,
                array(
                    'ID' => $post_id
                ),
                array(
                    '%d'
                )
            );                
        }

    }
    
    public function delete_post($post_id) {
        global $wpdb;
        
        $wpdb->delete(
            $wpdb->prefix . RRZE_LC_POSTS_TABLE,
            array(
                'ID' => $post_id
            ),
            array(
                '%d'
            )
        );        
        
        $wpdb->delete(
            $wpdb->prefix . RRZE_LC_ERRORS_TABLE,
            array(
                'post_id' => $post_id
            ),
            array(
                '%d'
            )
        );        
    }

    public function dashboard_widget() {
        global $wpdb;

        $output = '';

        $timestamp = get_option(RRZE_LC_OPTION_NAME_SCAN_TIMESTAMP);
        $where = $wpdb->prepare("WHERE checked < %s", date('Y-m-d H:i:s', $timestamp));
        
        $post_types = self::$options->post_types;

        if(!empty($post_types)) {
            $where .= sprintf(' AND post_type IN (%s)', implode(',', array_map(create_function('$a', 'return "\'$a\'";'), $post_types)));
        }
        
        $post_status = self::$options->post_status;
        
        if(!empty($post_status)) {
            $where .= sprintf(' AND post_status IN (%s)', implode(',', array_map(create_function('$a', 'return "\'$a\'";'), $post_status)));
        }
        
        $queue_count = (int) $wpdb->get_var(sprintf("SELECT COUNT(*) FROM %s %s", $wpdb->prefix . RRZE_LC_POSTS_TABLE, $where));

        $errors_count = (int) $wpdb->get_var(sprintf("SELECT COUNT(*) FROM %s WHERE error_status IS NULL", $wpdb->prefix . RRZE_LC_ERRORS_TABLE));

        if ($errors_count > 0) {
            $output .= sprintf(
                "<p><a href='%s' title='" . __('Fehlerhafte Links anschauen', 'rrze-link-checker') . "'><strong>" .
                _n('%d fehlerhafte Links gefunden.', '%d fehlerhafte Links gefunden.', $errors_count, 'rrze-link-checker') .
                " </strong></a></p>", admin_url('admin.php?page=rrze-link-checker'), $errors_count
            );
        } else {
            $output .= sprintf('<p>%s</p>', __("Keine fehlerhaften Links gefunden.", 'rrze-link-checker'));
        }

        if ($queue_count > 0) {
            $output .= sprintf(
                "<p>" .
                _n('%d Dokument in der Warteschlange.', '%d Dokumente in der Warteschlange.', $queue_count, 'rrze-link-checker'), $queue_count) .
                "</p>";
        } else {
            $output .= sprintf('<p>%s</p>', __('Keine Dokumente in der Warteschlange.', 'rrze-link-checker'));
        }            
        ?>
        <div class="link-checker">
            <p><?php echo $output; ?></p>
        </div>
        <?php
    }

    private function clean_post_type_options($post_types = array(), $post_type_support = NULL) {
        $normalized_post_type_options = array();
        $custom_post_types = wp_list_pluck($this->get_custom_post_types(), 'name');

        $all_post_types = array_merge($custom_post_types, array_keys($post_types));

        foreach ($all_post_types as $post_type) {
            if (!empty($post_types[$post_type]) || post_type_supports($post_type, $post_type_support)) {
                $normalized_post_type_options[] = $post_type;
            }
        }
        return $normalized_post_type_options;
    }

    private function clean_post_status_options($post_status = array()) {
        $normalized_post_status_options = array();
        $available_post_status = $this->get_available_post_status();

        foreach ($available_post_status as $status) {
            if (!empty($post_status[$status])) {
                $normalized_post_status_options[] = $status;
            }
        }
        return $normalized_post_status_options;
    }
    
    private function get_available_post_types($post_type = NULL) {
        $all_post_types = array();

        $buildin_post_types = $this->get_buildin_post_types();
        $all_post_types['post'] = $buildin_post_types['post'];
        $all_post_types['page'] = $buildin_post_types['page'];

        $custom_post_types = $this->get_custom_post_types();
        if (count($custom_post_types)) {
            foreach ($custom_post_types as $custom_post_type => $args) {
                $all_post_types[$custom_post_type] = $args;
            }
        }

        if (!is_null($post_type) && isset($all_post_types[$post_type])) {
            return $all_post_types[$post_type];
        }
        
        return $all_post_types;
    }
    
    private function get_buildin_post_types() {

        $args = array(
            '_builtin' => TRUE,
            'public' => TRUE,
        );

        return get_post_types($args, 'objects');
    }

    private function get_custom_post_types() {

        $args = array(
            '_builtin' => FALSE,
            'public' => TRUE,
        );

        return get_post_types($args, 'objects');
    }
    
    private function get_available_post_status() {
        $allowed_post_status = array_map('trim', explode(',', RRZE_LC_ALLOWED_POST_STATUS));
        $post_status = apply_filters('rrze_lc_allowed_post_status', $allowed_post_status);
        
        return (empty($post_status) && !is_array($post_status)) ? FALSE : array_intersect($post_status, $allowed_post_status);
    }
    
    private function get_post_status_name($status) {
        $status_name = __('Unbekannt', 'rrze-link-checker');

        $builtin_status = array(
            'publish' => __('Veröffentlicht', 'rrze-link-checker'),
            'draft' => __('Entwurf', 'rrze-link-checker'),
            'future' => __('Geplant', 'rrze-link-checker'),
            'private' => __('Privat', 'rrze-link-checker'),
            'pending' => __('Ausstehender Review', 'rrze-link-checker'),
            'trash' => __('Papierkorb', 'rrze-link-checker'),
        );

        if (array_key_exists($status, $builtin_status)) {
            $status_name = $builtin_status[$status];
        }

        return $status_name;
    }
    
    private function get_param($param, $default = '') {
        return RRZE_LC_Helper::get_param($param, $default);
    }

    private function options_url($atts = array()) {
        return RRZE_LC_Helper::options_url($atts);
    }
    
    private function check_urls($post_id = NULL, $max_check_urls = NULL) {
        RRZE_LC_Helper::check_urls($post_id, $max_check_urls);
    }
    
    public function post_admin_notices() {
        $hash = $this->transient_hash();

        if ((!$errors = get_transient($hash)) OR (!is_array($errors))) {
            return;
        }

        delete_transient($hash);

        $errors_output = array();
        foreach ($errors as $error) {
            $errors_output[] = sprintf('<a href="%1$s" target="_blank">%1$s</a> (%2$s)', esc_url($error->url), esc_html($error->text));
        }
        
        $errors_count = count($errors_output);
        
        echo '<div class="error">' . PHP_EOL;
        echo sprintf(_n('%d fehlerhafte Links gefunden:', '%d fehlerhafte Links gefunden:', $errors_count, 'rrze-link-checker'), $errors_count) . '&nbsp;' . PHP_EOL;
        echo implode(', ', $errors_output) . '.' . PHP_EOL;
        echo '</div>' . PHP_EOL;
    }

    public function settings_admin_notices() {
        $page = $this->get_param('page');

        if($page != 'rrze-link-checker-settings') {
            return;
        }

        $hash = $this->transient_hash();

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
        } elseif(!empty($notices['updated']) && is_string($notices['updated'])) {
            echo '<div class="updated">' . PHP_EOL;
            echo '<p>' . $notices['updated'] . '</p>' . PHP_EOL;
            echo '</div>' . PHP_EOL;
        }
                
    }
    
    private function transient_hash() {
        return md5(sprintf('RRZE_LC_%s_%s', get_the_ID(), get_current_user_id()));
    }
    
}
