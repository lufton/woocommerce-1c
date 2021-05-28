<?php
if (!defined('ABSPATH')) exit;

if (!defined('WC1C_PRICE_TYPE')) define('WC1C_PRICE_TYPE', null);

function wc1c_offers_start_element_handler($is_full, $names, $depth, $name, $attrs) {
  global $wc1c_price_types, $wc1c_offer, $wc1c_price;

  if (@$names[$depth - 1] == 'ПакетПредложений' && $name == 'ТипыЦен') {
    $wc1c_price_types = array();
  }
  elseif (@$names[$depth - 1] == 'ТипыЦен' && $name == 'ТипЦены') {
    $wc1c_price_types[] = array();
  }
  elseif (@$names[$depth - 1] == 'Предложения' && $name == 'Предложение') {
    $wc1c_offer = array();
  }
  elseif (@$names[$depth - 1] == 'Предложение' && $name == 'Склад') {
    @$wc1c_offer['КоличествоНаСкладе'] += $attrs['КоличествоНаСкладе'];
  }
  elseif (@$names[$depth - 1] == 'Цены' && $name == 'Цена') {
    $wc1c_price = array();
  }
}

function wc1c_offers_character_data_handler($is_full, $names, $depth, $name, $data) {
  global $wc1c_price_types, $wc1c_offer, $wc1c_price;

  if (@$names[$depth - 2] == 'ТипыЦен' && @$names[$depth - 1] == 'ТипЦены' && $name != 'Налог') {
    $i = count($wc1c_price_types) - 1;
    @$wc1c_price_types[$i][$name] .= $data;
  }
  elseif (@$names[$depth - 2] == 'Предложения' && @$names[$depth - 1] == 'Предложение' && !in_array($name, array('БазоваяЕдиница', 'Цены'))) {
    @$wc1c_offer[$name] .= $data;
  }
  elseif (@$names[$depth - 2] == 'Цены' && @$names[$depth - 1] == 'Цена') {
    @$wc1c_price[$name] .= $data;
  }
}

function wc1c_offers_end_element_handler($is_full, $names, $depth, $name) {
  global $wpdb, $wc1c_price_types, $wc1c_price_type, $wc1c_offer, $wc1c_price;

  if (@$names[$depth - 1] == 'ПакетПредложений' && $name == 'ТипыЦен') {
    if (!WC1C_PRICE_TYPE) {
      $wc1c_price_type = $wc1c_price_types[0];
    }
    else {
      foreach ($wc1c_price_types as $price_type) {
        if (@$price_type['Ид'] != WC1C_PRICE_TYPE && @$price_type['Наименование'] != WC1C_PRICE_TYPE) continue;

        $wc1c_price_type = $price_type;
        break;
      }
      if (!isset($wc1c_price_type)) wc1c_error("Failed to match price type");
    }

    if (!empty($wc1c_price_type['Валюта'])) {
      update_option('wc1c_currency', $wc1c_price_type['Валюта']);
    }
  }
  elseif (@$names[$depth - 1] == 'Цены' && $name == 'Цена') {
    if (!isset($wc1c_offer['Цена']) && (!isset($wc1c_price['ИдТипаЦены']) || $wc1c_price['ИдТипаЦены'] == $wc1c_price_type['Ид'])) $wc1c_offer['Цена'] = $wc1c_price;
  }
  elseif (@$names[$depth - 1] == 'Предложения' && $name == 'Предложение') {
    list($guid, ) = explode('#', $wc1c_offer['Ид'], 1);
    $post_id = wc1c_post_id_by_meta('_wc1c_guid', $guid);
    if ($post_id) {
      $product = wc_get_product($post_id);
	  $quantity = isset($wc1c_offer['Количество']) ? $wc1c_offer['Количество'] : @$wc1c_offer['КоличествоНаСкладе'];
	  $price = isset($wc1c_offer['Цена']['ЦенаЗаЕдиницу']) ? wc1c_parse_decimal($wc1c_offer['Цена']['ЦенаЗаЕдиницу']) : null;
      if (!is_null($price)) {
        $coefficient = isset($wc1c_offer['Цена']['Коэффициент']) ? wc1c_parse_decimal($wc1c_offer['Цена']['Коэффициент']) : null;
        if (!is_null($coefficient)) $price *= $coefficient;
	  }
	  $product->set_regular_price($price);
	  wc_update_product_stock($product, $quantity);
      unset($product, $quantity, $price, $coefficient);
    }
	unset($guid);
  }
  elseif (!$depth && $name == 'КоммерческаяИнформация') {
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_%'");
    wc1c_check_wpdb_error();

    do_action('wc1c_post_offers', $is_full);
  }
}