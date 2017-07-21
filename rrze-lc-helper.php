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
                $text = !empty($matches[0]) && isset($curl_error_codes[$matches[0]]) ? $curl_error_codes[$matches[0]] : __('Unbekannt', 'rrze-link-checker');
                
                $errors[] = array(
                    'url' => $url,
                    'text' => $text,
                    'post_title' => $post->post_title
                );
            } else {
                $http_status_codes = self::http_status_codes();
                $code = (int) wp_remote_retrieve_response_code($response);
                
                if ($code >= 400 && $code != 405) {
                    $text = isset($http_status_codes[$code]) ? sprintf('%s - %s', $code, $http_status_codes[$code]) : __('Unbekannt', 'rrze-link-checker');
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
            400 => __('Bad Request', 'rrze-link-checker'),
            401 => __('Unauthorized', 'rrze-link-checker'),
            402 => __('Payment Required', 'rrze-link-checker'),
            403 => __('Forbidden', 'rrze-link-checker'),
            404 => __('Not Found', 'rrze-link-checker'),
            405 => __('Method Not Allowed', 'rrze-link-checker'),
            406 => __('Not Acceptable', 'rrze-link-checker'),
            407 => __('Proxy Authentication Required', 'rrze-link-checker'),
            408 => __('Request Timeout', 'rrze-link-checker'),
            409 => __('Conflict', 'rrze-link-checker'),
            410 => __('Gone', 'rrze-link-checker'),
            411 => __('Length Required', 'rrze-link-checker'),
            412 => __('Precondition Failed', 'rrze-link-checker'),
            413 => __('Request Entity Too Large', 'rrze-link-checker'),
            414 => __('Request-URI Too Long', 'rrze-link-checker'),
            415 => __('Unsupported Media Type', 'rrze-link-checker'),
            416 => __('Requested Range Not Satisfiable', 'rrze-link-checker'),
            417 => __('Expectation Failed', 'rrze-link-checker'),
            500 => __('Internal Server Error', 'rrze-link-checker'),
            501 => __('Not Implemented', 'rrze-link-checker'),
            502 => __('Bad Gateway', 'rrze-link-checker'),
            503 => __('Service Unavailable', 'rrze-link-checker'),
            504 => __('Gateway Timeout', 'rrze-link-checker'),
            505 => __('HTTP Version Not Supported', 'rrze-link-checker')            
        );
    }

    /**
     * http://curl.haxx.se/libcurl/c/libcurl-errors.html
     */
    public static function curl_error_codes() {
        return array (
            0 => __('Ok', 'rrze-link-checker'),
            1 => __('Unsupported protocol', 'rrze-link-checker'),
            2 => __('Failed initialization', 'rrze-link-checker'),
            3 => __('URL malformat', 'rrze-link-checker'),
            4 => __('Not built-in', 'rrze-link-checker'),
            5 => __('Couldn\'t resolve proxy', 'rrze-link-checker'),
            6 => __('Couldn\'t resolve host', 'rrze-link-checker'),
            7 => __('Couldn\'t connect', 'rrze-link-checker'),
            8 => __('FTP weird server reply', 'rrze-link-checker'),
            9 => __('Remote access denied', 'rrze-link-checker'),
            10 => __('FTP access failed', 'rrze-link-checker'),
            11 => __('FTP weird pass reply', 'rrze-link-checker'),
            12 => __('FTP accept timeout', 'rrze-link-checker'),
            13 => __('FTP weird pasv reply', 'rrze-link-checker'),
            14 => __('FTP weird 227 format', 'rrze-link-checker'),
            15 => __('FTP can\'t get host', 'rrze-link-checker'),
            16 => __('HTTP2 framing layer problem', 'rrze-link-checker'),
            17 => __('FTP couldn\'t set type', 'rrze-link-checker'),
            18 => __('Partial file', 'rrze-link-checker'),
            19 => __('FTP couldn\'t retrieve file', 'rrze-link-checker'),
            21 => __('Quote error', 'rrze-link-checker'),
            22 => __('HTTP returned error', 'rrze-link-checker'),
            23 => __('Write error', 'rrze-link-checker'),
            25 => __('Upload failed', 'rrze-link-checker'),
            26 => __('Read error', 'rrze-link-checker'),
            27 => __('Out of memory', 'rrze-link-checker'),
            28 => __('Operation timedout', 'rrze-link-checker'),
            30 => __('FTP port failed', 'rrze-link-checker'),
            31 => __('FTP couldn\'t use REST', 'rrze-link-checker'),
            33 => __('Range error', 'rrze-link-checker'),
            34 => __('HTTP post error', 'rrze-link-checker'),
            35 => __('SSL connect error', 'rrze-link-checker'),
            36 => __('Bad download resume', 'rrze-link-checker'),
            37 => __('File couldn\'t read file', 'rrze-link-checker'),
            38 => __('LDAP cannot bind', 'rrze-link-checker'),
            39 => __('LDAP search failed', 'rrze-link-checker'),
            41 => __('Function not found', 'rrze-link-checker'),
            42 => __('Aborted by callback', 'rrze-link-checker'),
            43 => __('Bad function argument', 'rrze-link-checker'),
            45 => __('Interface failed', 'rrze-link-checker'),
            47 => __('Too many redirections', 'rrze-link-checker'),
            48 => __('Unknown option', 'rrze-link-checker'),
            49 => __('Telnet option syntax', 'rrze-link-checker'),
            51 => __('Peer failed verification', 'rrze-link-checker'),
            52 => __('Got nothing', 'rrze-link-checker'),
            53 => __('SSL engine not found', 'rrze-link-checker'),
            54 => __('SSL engine set failed', 'rrze-link-checker'),
            55 => __('Send error', 'rrze-link-checker'),
            56 => __('Receive error', 'rrze-link-checker'),
            58 => __('Certificate problem', 'rrze-link-checker'),
            59 => __('SSL cipher', 'rrze-link-checker'),
            60 => __('SSL CA certificate', 'rrze-link-checker'),
            61 => __('Bad content encoding', 'rrze-link-checker'),
            62 => __('LDAP invalid URL', 'rrze-link-checker'),
            63 => __('File size exceeded', 'rrze-link-checker'),
            64 => __('Use SSL failed', 'rrze-link-checker'),
            65 => __('Send fail rewind', 'rrze-link-checker'),
            66 => __('SSL initialization failed', 'rrze-link-checker'),
            67 => __('Login denied', 'rrze-link-checker'),
            68 => __('TFTP file not found', 'rrze-link-checker'),
            69 => __('TFTP permission', 'rrze-link-checker'),
            70 => __('Remote disk full', 'rrze-link-checker'),
            71 => __('TFTP illegal', 'rrze-link-checker'),
            72 => __('FTP unknown ID', 'rrze-link-checker'),
            73 => __('Remote file exists', 'rrze-link-checker'),
            74 => __('TFTP no such user', 'rrze-link-checker'),
            75 => __('Conversion failed', 'rrze-link-checker'),
            76 => __('Conversion callbacks', 'rrze-link-checker'),
            77 => __('SSL CA certificate bad file', 'rrze-link-checker'),
            78 => __('Remote file not found', 'rrze-link-checker'),
            79 => __('SSH error', 'rrze-link-checker'),
            80 => __('SSL shutdown failed', 'rrze-link-checker'),
            81 => __('Socket not ready', 'rrze-link-checker'),
            82 => __('SSL CRL bad file', 'rrze-link-checker'),
            83 => __('SSL issue error', 'rrze-link-checker'),
            84 => __('FTP PRET failed', 'rrze-link-checker'),
            85 => __('RTSP CSEQ error', 'rrze-link-checker'),
            86 => __('RTSP session error', 'rrze-link-checker'),
            87 => __('FTP bad file list', 'rrze-link-checker'),
            88 => __('Chunk failed', 'rrze-link-checker'),
            89 => __('No connection available', 'rrze-link-checker'),
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
