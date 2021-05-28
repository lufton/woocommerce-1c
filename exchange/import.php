<?php
if (!defined('ABSPATH')) exit;

if (!defined('WC1C_MATCH_BY_SKU')) define('WC1C_MATCH_BY_SKU', false);

function wc1c_import_start_element_handler($is_full, $names, $depth, $name, $attrs) {
  global $wc1c_product;

  if (!$depth && $name != 'КоммерческаяИнформация') {
    wc1c_error("XML parser misbehavior.");
  }
  elseif (@$names[$depth - 1] == 'Товары' && $name == 'Товар') {
    $wc1c_product = array();
  }
}

function wc1c_import_character_data_handler($is_full, $names, $depth, $name, $data) {
  global $wc1c_product;
  //echo "$name\r\n";

  if (@$names[$depth - 2] == 'Товары' && @$names[$depth - 1] == 'Товар' && in_array($name, array('Ид', 'Артикул'))) {
    @$wc1c_product[$name] .= $data;
  }
}

function wc1c_import_end_element_handler($is_full, $names, $depth, $name) {
  global $wpdb, $wc1c_product;

  if (@$names[$depth - 1] == 'Товары' && $name == 'Товар') {
    list($guid, ) = explode('#', $wc1c_product['Ид'], 1);
    if (WC1C_MATCH_BY_SKU) {
      $sku = @$wc1c_product['Артикул'];
      if ($sku) {
        $_post_id = wc1c_post_id_by_meta('_sku', $sku);
        if ($_post_id) update_post_meta($_post_id, '_wc1c_guid', $guid);
      }
    }
  }
  elseif (!$depth && $name == 'КоммерческаяИнформация') {
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_%'");
    wc1c_check_wpdb_error();

    do_action('wc1c_post_import', $is_full);
  }
}