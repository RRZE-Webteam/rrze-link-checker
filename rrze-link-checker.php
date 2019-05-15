<?php

/*
Plugin Name:     RRZE Link-Checker
Plugin URI:      https://github.com/RRZE-Webteam/rrze-cf7-redirect
Description:     Überprüfung auf defekte Links.
Version:         2.0.1
Author:          RRZE-Webteam
Author URI:      https://blogs.fau.de/webworking/
License:         GNU General Public License v2
License URI:     http://www.gnu.org/licenses/gpl-2.0.html
Domain Path:     /languages
Text Domain:     rrze-link-checker
*/

namespace RRZE\LinkChecker;

defined('ABSPATH') || exit;

use RRZE\LinkChecker\Main;
use RRZE\LinkChecker\DB;
use RRZE\LinkChecker\Options;

const RRZE_PHP_VERSION = '7.1';
const RRZE_WP_VERSION = '5.2';

const RRZE_PLUGIN_FILE = __FILE__;
const RRZE_PLUGIN_VERSION = '2.0.1';

spl_autoload_register(function ($class) {
    $prefix = __NAMESPACE__;
    $base_dir = __DIR__ . '/includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

register_activation_hook(RRZE_PLUGIN_FILE, __NAMESPACE__ . '\activation');
register_deactivation_hook(RRZE_PLUGIN_FILE, __NAMESPACE__ . '\deactivation');
add_action('plugins_loaded', __NAMESPACE__ . '\loaded');

function load_textdomain()
{
    load_plugin_textdomain('rrze-link-checker', false, sprintf('%s/languages/', dirname(plugin_basename(RRZE_PLUGIN_FILE))));
}

function system_requirements()
{
    $error = '';
    if (version_compare(PHP_VERSION, RRZE_PHP_VERSION, '<')) {
        $error = sprintf(__('The server is running PHP version %1$s. The Plugin requires at least PHP version %2$s.', 'rrze-link-checker'), PHP_VERSION, RRZE_PHP_VERSION);
    } elseif (version_compare($GLOBALS['wp_version'], RRZE_WP_VERSION, '<')) {
        $error = sprintf(__('The server is running WordPress version %1$s. The Plugin requires at least WordPress version %2$s.', 'rrze-link-checker'), $GLOBALS['wp_version'], RRZE_WP_VERSION);
    }
    return $error;
}

function activation()
{
    load_textdomain();

    if ($error = system_requirements()) {
        deactivate_plugins(plugin_basename(RRZE_PLUGIN_FILE), false, true);
        wp_die($error);
    }

    DB::createDbTables();
    update_option(Options::getVersionOptionName(), RRZE_PLUGIN_VERSION);
}

function deactivation()
{
    delete_option(Options::getOptionName());
    delete_option(Options::getCronTimestampOptionName());
    delete_option(Options::getScanTimestampOptionName());
    DB::dropDbTables();
}

function loaded()
{
    load_textdomain();

    if ($error = system_requirements()) {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        $plugin_data = get_plugin_data(RRZE_PLUGIN_FILE);
        $plugin_name = $plugin_data['Name'];
        $tag = is_network_admin() ? 'network_admin_notices' : 'admin_notices';
        add_action($tag, function () use ($plugin_name, $error) {
            printf('<div class="notice notice-error"><p>%1$s: %2$s</p></div>', esc_html($plugin_name), esc_html($error));
        });
    } else {
        new Main();
    }
}
