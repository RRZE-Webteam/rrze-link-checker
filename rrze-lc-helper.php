<?php

class RRZE_LC_Helper {
    
    public static function check_urls($post_id = NULL) {
        if (empty($post_id)) {
            return FALSE;
        }

        $post_id = absint($post_id);
        
        $post = get_post($post_id);

        if (empty($post) OR empty($post->post_content)) {
            return FALSE;
        }

        if (!$urls = self::extract_urls($post->post_content)) {
            return FALSE;
        }
        
        $errors = array();

        foreach ($urls as $url) {
            if ($hash = parse_url($url, PHP_URL_FRAGMENT)) {
                $url = str_replace('#' . $hash, '', $url);
            }

            $url = esc_url_raw($url, array('http', 'https'));

            if (empty($url)) {
                continue;
            }

            $response = wp_safe_remote_head($url);

            if (is_wp_error($response)) {
                $curl_error_codes = self::curl_error_codes();
                preg_match('/\d+/', $response->get_error_message(), $matches);
                $text = !empty($matches[0]) && isset($curl_error_codes[$matches[0]]) ? $curl_error_codes[$matches[0]] : __('Unbekannt', RRZE_LC_TEXTDOMAIN);
                
                $errors[] = array(
                    'url' => $url,
                    'text' => $text,
                    'post_title' => $post->post_title
                );
            } else {
                $http_status_codes = self::http_status_codes();
                $code = (int) wp_remote_retrieve_response_code($response);
                
                if ($code >= 400 && $code != 405) {
                    $text = isset($http_status_codes[$code]) ? sprintf('%s - %s', $code, $http_status_codes[$code]) : __('Unbekannt', RRZE_LC_TEXTDOMAIN);
                    $errors[] = array(
                        'url' => $url,
                        'text' => $text,
                        'post_title' => $post->post_title
                    );
                }
            }
        }

        if (empty($errors)) {
            return FALSE;
        }

        return $errors;
    }
    
    public static function extract_urls($html) {
        if (empty($html)) {
            return FALSE;
        }
        
        $urls = array();

        // Disable DOMDocument warnings due to invalid HTML
        libxml_use_internal_errors(true);
        
        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);

        foreach ($doc->childNodes as $item) {
            if ($item->nodeType == XML_PI_NODE) {
                $doc->removeChild($item);
            }
        }

        $doc->encoding = 'UTF-8';

        $nodes = $doc->getElementsByTagName('a');
        foreach ($nodes as $tag) {
            $href = $tag->getAttribute('href');

            if (empty($href)) {
                continue;
            }

            if (strpos($href, '#') === 0) {
                continue;
            }

            if (strpos($href, 'mailto:') === 0) {
                continue;
            }

            if (strpos($href, '/') === 0) {
                $href = site_url() . $href;
            }

            $urls[] = $href;
        }

        $nodes = $doc->getElementsByTagName('img');
        foreach ($nodes as $tag) {
            $src = $tag->getAttribute('src');

            if (empty($src)) {
                continue;
            }

            $urls[] = $src;
        }

        libxml_clear_errors();
        
        return $urls;
    }
    
    public static function get_param($param, $default = '') {
        if (isset($_POST[$param])) {
            return $_POST[$param];
        }

        if (isset($_GET[$param])) {
            return $_GET[$param];
        }

        return $default;
    }
    
    public static function options_url($atts = array()) {
        $atts = array_merge(
            array(
                'page' => 'rrze-link-checker'
            ), $atts
        );

        return add_query_arg($atts, get_admin_url(NULL, 'admin.php'));
    }
    
    /**
     * https://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
     */
    public static function http_status_codes() {
        return array(
            400 => __('Bad Request', RRZE_LC_TEXTDOMAIN),
            401 => __('Unauthorized', RRZE_LC_TEXTDOMAIN),
            402 => __('Payment Required', RRZE_LC_TEXTDOMAIN),
            403 => __('Forbidden', RRZE_LC_TEXTDOMAIN),
            404 => __('Not Found', RRZE_LC_TEXTDOMAIN),
            405 => __('Method Not Allowed', RRZE_LC_TEXTDOMAIN),
            406 => __('Not Acceptable', RRZE_LC_TEXTDOMAIN),
            407 => __('Proxy Authentication Required', RRZE_LC_TEXTDOMAIN),
            408 => __('Request Timeout', RRZE_LC_TEXTDOMAIN),
            409 => __('Conflict', RRZE_LC_TEXTDOMAIN),
            410 => __('Gone', RRZE_LC_TEXTDOMAIN),
            411 => __('Length Required', RRZE_LC_TEXTDOMAIN),
            412 => __('Precondition Failed', RRZE_LC_TEXTDOMAIN),
            413 => __('Request Entity Too Large', RRZE_LC_TEXTDOMAIN),
            414 => __('Request-URI Too Long', RRZE_LC_TEXTDOMAIN),
            415 => __('Unsupported Media Type', RRZE_LC_TEXTDOMAIN),
            416 => __('Requested Range Not Satisfiable', RRZE_LC_TEXTDOMAIN),
            417 => __('Expectation Failed', RRZE_LC_TEXTDOMAIN),
            500 => __('Internal Server Error', RRZE_LC_TEXTDOMAIN),
            501 => __('Not Implemented', RRZE_LC_TEXTDOMAIN),
            502 => __('Bad Gateway', RRZE_LC_TEXTDOMAIN),
            503 => __('Service Unavailable', RRZE_LC_TEXTDOMAIN),
            504 => __('Gateway Timeout', RRZE_LC_TEXTDOMAIN),
            505 => __('HTTP Version Not Supported', RRZE_LC_TEXTDOMAIN)            
        );
    }

    /**
     * http://curl.haxx.se/libcurl/c/libcurl-errors.html
     */
    public static function curl_error_codes() {
        return array (
            0 => __('Ok', RRZE_LC_TEXTDOMAIN),
            1 => __('Unsupported protocol', RRZE_LC_TEXTDOMAIN),
            2 => __('Failed initialization', RRZE_LC_TEXTDOMAIN),
            3 => __('URL malformat', RRZE_LC_TEXTDOMAIN),
            4 => __('Not built-in', RRZE_LC_TEXTDOMAIN),
            5 => __('Couldn\'t resolve proxy', RRZE_LC_TEXTDOMAIN),
            6 => __('Couldn\'t resolve host', RRZE_LC_TEXTDOMAIN),
            7 => __('Couldn\'t connect', RRZE_LC_TEXTDOMAIN),
            8 => __('FTP weird server reply', RRZE_LC_TEXTDOMAIN),
            9 => __('Remote access denied', RRZE_LC_TEXTDOMAIN),
            10 => __('FTP access failed', RRZE_LC_TEXTDOMAIN),
            11 => __('FTP weird pass reply', RRZE_LC_TEXTDOMAIN),
            12 => __('FTP accept timeout', RRZE_LC_TEXTDOMAIN),
            13 => __('FTP weird pasv reply', RRZE_LC_TEXTDOMAIN),
            14 => __('FTP weird 227 format', RRZE_LC_TEXTDOMAIN),
            15 => __('FTP can\'t get host', RRZE_LC_TEXTDOMAIN),
            16 => __('HTTP2 framing layer problem', RRZE_LC_TEXTDOMAIN),
            17 => __('FTP couldn\'t set type', RRZE_LC_TEXTDOMAIN),
            18 => __('Partial file', RRZE_LC_TEXTDOMAIN),
            19 => __('FTP couldn\'t retrieve file', RRZE_LC_TEXTDOMAIN),
            21 => __('Quote error', RRZE_LC_TEXTDOMAIN),
            22 => __('HTTP returned error', RRZE_LC_TEXTDOMAIN),
            23 => __('Write error', RRZE_LC_TEXTDOMAIN),
            25 => __('Upload failed', RRZE_LC_TEXTDOMAIN),
            26 => __('Read error', RRZE_LC_TEXTDOMAIN),
            27 => __('Out of memory', RRZE_LC_TEXTDOMAIN),
            28 => __('Operation timedout', RRZE_LC_TEXTDOMAIN),
            30 => __('FTP port failed', RRZE_LC_TEXTDOMAIN),
            31 => __('FTP couldn\'t use REST', RRZE_LC_TEXTDOMAIN),
            33 => __('Range error', RRZE_LC_TEXTDOMAIN),
            34 => __('HTTP post error', RRZE_LC_TEXTDOMAIN),
            35 => __('SSL connect error', RRZE_LC_TEXTDOMAIN),
            36 => __('Bad download resume', RRZE_LC_TEXTDOMAIN),
            37 => __('File couldn\'t read file', RRZE_LC_TEXTDOMAIN),
            38 => __('LDAP cannot bind', RRZE_LC_TEXTDOMAIN),
            39 => __('LDAP search failed', RRZE_LC_TEXTDOMAIN),
            41 => __('Function not found', RRZE_LC_TEXTDOMAIN),
            42 => __('Aborted by callback', RRZE_LC_TEXTDOMAIN),
            43 => __('Bad function argument', RRZE_LC_TEXTDOMAIN),
            45 => __('Interface failed', RRZE_LC_TEXTDOMAIN),
            47 => __('Too many redirections', RRZE_LC_TEXTDOMAIN),
            48 => __('Unknown option', RRZE_LC_TEXTDOMAIN),
            49 => __('Telnet option syntax', RRZE_LC_TEXTDOMAIN),
            51 => __('Peer failed verification', RRZE_LC_TEXTDOMAIN),
            52 => __('Got nothing', RRZE_LC_TEXTDOMAIN),
            53 => __('SSL engine not found', RRZE_LC_TEXTDOMAIN),
            54 => __('SSL engine set failed', RRZE_LC_TEXTDOMAIN),
            55 => __('Send error', RRZE_LC_TEXTDOMAIN),
            56 => __('Receive error', RRZE_LC_TEXTDOMAIN),
            58 => __('Certificate problem', RRZE_LC_TEXTDOMAIN),
            59 => __('SSL cipher', RRZE_LC_TEXTDOMAIN),
            60 => __('SSL CA certificate', RRZE_LC_TEXTDOMAIN),
            61 => __('Bad content encoding', RRZE_LC_TEXTDOMAIN),
            62 => __('LDAP invalid URL', RRZE_LC_TEXTDOMAIN),
            63 => __('File size exceeded', RRZE_LC_TEXTDOMAIN),
            64 => __('Use SSL failed', RRZE_LC_TEXTDOMAIN),
            65 => __('Send fail rewind', RRZE_LC_TEXTDOMAIN),
            66 => __('SSL initialization failed', RRZE_LC_TEXTDOMAIN),
            67 => __('Login denied', RRZE_LC_TEXTDOMAIN),
            68 => __('TFTP file not found', RRZE_LC_TEXTDOMAIN),
            69 => __('TFTP permission', RRZE_LC_TEXTDOMAIN),
            70 => __('Remote disk full', RRZE_LC_TEXTDOMAIN),
            71 => __('TFTP illegal', RRZE_LC_TEXTDOMAIN),
            72 => __('FTP unknown ID', RRZE_LC_TEXTDOMAIN),
            73 => __('Remote file exists', RRZE_LC_TEXTDOMAIN),
            74 => __('TFTP no such user', RRZE_LC_TEXTDOMAIN),
            75 => __('Conversion failed', RRZE_LC_TEXTDOMAIN),
            76 => __('Conversion callbacks', RRZE_LC_TEXTDOMAIN),
            77 => __('SSL CA certificate bad file', RRZE_LC_TEXTDOMAIN),
            78 => __('Remote file not found', RRZE_LC_TEXTDOMAIN),
            79 => __('SSH error', RRZE_LC_TEXTDOMAIN),
            80 => __('SSL shutdown failed', RRZE_LC_TEXTDOMAIN),
            81 => __('Socket not ready', RRZE_LC_TEXTDOMAIN),
            82 => __('SSL CRL bad file', RRZE_LC_TEXTDOMAIN),
            83 => __('SSL issue error', RRZE_LC_TEXTDOMAIN),
            84 => __('FTP PRET failed', RRZE_LC_TEXTDOMAIN),
            85 => __('RTSP CSEQ error', RRZE_LC_TEXTDOMAIN),
            86 => __('RTSP session error', RRZE_LC_TEXTDOMAIN),
            87 => __('FTP bad file list', RRZE_LC_TEXTDOMAIN),
            88 => __('Chunk failed', RRZE_LC_TEXTDOMAIN),
            89 => __('No connection available', RRZE_LC_TEXTDOMAIN),
        );
    }
    
}

function _rrze_lc_debug_log($input, $append = true) {
    if(defined('WP_DEBUG') && WP_DEBUG) {
        $file = dirname(__FILE__) . '/debug.log';
        $flags = $append ? FILE_APPEND | LOCK_EX : LOCK_EX;
        file_put_contents($file, print_r($input, true) . PHP_EOL, $flags);
    }
}
