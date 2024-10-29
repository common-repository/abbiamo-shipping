<?php

/* Exit if accessed directly */
if (!defined('ABSPATH')) {
  exit;
}

/**
 * Install table.
 *
 * @access public
 * @return void
 */
function wc_abbiamo_install_table()
{
    global $wpdb;
    require_once(ABSPATH.'wp-admin/includes/upgrade.php');

    $sql = "
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}woocommerce_abbiamolog (
            `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_id`         VARCHAR(255)  NOT NULL,
            `tracking`         VARCHAR(255)  DEFAULT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARSET=utf8";
    if (!$wpdb->query($sql))
        error_log('Error in query:'.$sql);
}

/**
 * Uninstall table.
 *
 * @access public
 * @return void
 */
function wc_abbiamo_uninstall_table()
{
    global $wpdb;
    require_once(ABSPATH.'wp-admin/includes/upgrade.php');

    error_log('Warning! wc_jadlog_uninstall_table() does not drop jadlog orders table to preserve data!');
    // $sql = "DROP TABLE IF EXISTS {$wpdb->prefix}woocommerce_jadlog";
    // if (!$wpdb->query($sql))
    //     error_log('Error in query:'.$sql);
}
