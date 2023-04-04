<?php

/*
 * Plugin Name: Mail Statistics
 * Description: Collects basic statistics on the mailing activity and shows them in the Site Health page
 * Version: 1.0.0
 * Requires at least: 4.6
 * Author: satollo
 * Author URI: https://www.satollo.net
 * Update URI: false
 */

class MailStats {

    var $start = 0;

    /**
     * Keep the code executed on load at minimum.
     */
    function __construct() {

        add_filter('pre_wp_mail', [$this, 'pre_wp_mail'], 1, 1);

        if (is_admin()) {
            add_filter('debug_information', [$this, 'debug_information']);
        }
    }

    function pre_wp_mail($result) {
        static $filters = false;

        // There are no reason to add filters on each page load, just add them if we know an email is going to be sent
        if (!$filters) {
            $filters = true;

            // Register a failure
            add_action('wp_mail_failed', [$this, 'wp_mail_failed']);

            // Register a success
            add_action('wp_mail_succeeded', [$this, 'wp_mail_succeeded']);
        }
        $this->start = microtime(true);
        return $result;
    }

    function wp_mail_succeeded() {
        $duration = microtime(true) - $this->start;
        $stats = get_option('mail_stats', ['succeeded' => 0, 'failed' => 0, 'avg_send_time' => 0]);
        $stats['succeeded']++;
        $stats['avg_send_time'] = $stats['avg_send_time'] + ($duration - $stats['avg_send_time']) / $stats['succeeded'];
        update_option('mail_stats', $stats, false);
    }

    function wp_mail_failed($wp_error) {
        $stats = get_option('mail_stats', ['succeeded' => 0, 'failed' => 0, 'avg_send_time' => 0]);
        $stats['failed']++;
        $stats['last_error'] = $wp_error->get_error_message();
        update_option('mail_stats', $stats, false);
    }

    // See https://developer.wordpress.org/reference/hooks/debug_information/
    function debug_information($info) {

        $stats = get_option('mail_stats', ['count' => 0, 'duration' => 0]);

        if (empty($stats['started'])) {
            $stats['started'] = time();
            update_option('mail_stats', $stats, false);
        }

        $day_count = ceil((time() - $stats['started']) / DAY_IN_SECONDS);

        $info['mailing'] = [
            'label' => 'Mailing',
            'description' => 'Site email activity',
            'show_count' => false,
            'private' => false,
            'fields' => [
                [
                    'label' => 'Email send attempts',
                    'value' => $stats['failed'] + $stats['succeeded'],
                    'private' => false,
                    'debug' => ''
                ],
                [
                    'label' => 'Email succeeded',
                    'value' => $stats['succeeded'],
                    'private' => false,
                    'debug' => ''
                ],
                [
                    'label' => 'Email failed',
                    'value' => $stats['failed'],
                    'private' => false,
                    'debug' => ''
                ],
                [
                    'label' => 'Last error',
                    'value' => esc_html($stats['last_error']),
                    'private' => false,
                    'debug' => ''
                ],
                [
                    'label' => 'Average sending time',
                    'value' => $stats['avg_send_time'] . ' seconds',
                    'private' => false,
                    'debug' => ''
                ],
                [
                    'label' => 'Average emails per day',
                    'value' => $stats['succeeded'] / $day_count,
                    'private' => false,
                    'debug' => ''
                ]
            ]
        ];
        return $info;
    }

}

new MailStats();
