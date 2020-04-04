<?php

/*
This file is part of "Price Range" project and subject to the terms
and conditions defined in file "LICENSE.txt", which is part of this source
code package and also available on the project page: https://git.io/JvBbw.
*/

class ModelExtensionModulePriceRange extends Model {
	public function getPriceRanges($product_id, $quantity = 1) {
		$manual_range = $this->get($product_id);

		if ($manual_range) {
			$this->load->model('catalog/product');

			$manual_range['min'] *= $quantity;
			$manual_range['max'] *= $quantity;

			$product_info = $this->model_catalog_product->getProduct($product_id);

			$price = (float)$product_info['price'];

			$config_tax = $this->config->get('config_tax');

			if ($config_tax) {
				$price_range = array(
					'min' => $this->tax->calculate($manual_range['min'], $product_info['tax_class_id'], $config_tax),
					'max' => $this->tax->calculate($manual_range['max'], $product_info['tax_class_id'], $config_tax),
				);

				$price_range = $this->format($price_range);

				if ((float)$product_info['special']) {
					$co = (float)$product_info['special'] / (float)$product_info['price'];

					$special_range = array(
						'min' => $this->tax->calculate($manual_range['min'] * $co, $product_info['tax_class_id'], $config_tax),
						'max' => $this->tax->calculate($manual_range['max'] * $co, $product_info['tax_class_id'], $config_tax),
					);

					$special_range = $this->format($special_range);

					$extax_range = array(
						'min' => $manual_range['min'] * $co,
						'max' => $manual_range['max'] * $co,
					);
				} elseif ($this->model_catalog_product->getProductDiscounts($product_id)) {
					$discounts = array();

					foreach ($this->model_catalog_product->getProductDiscounts($product_id) as $discount) {
						$co = (float)$discount['price'] + ($manual_range['max'] - $manual_range['min']);

						$discount_extax_range = array(
							'min' => (float)$discount['price'] + ($manual_range['min'] - (float)$product_info['price']),
							'max' => (float)$discount['price'] + ($manual_range['max'] - (float)$product_info['price']),
						);

						$discount_extax_range = $this->format($discount_extax_range);

						$discount_range = array(
							'min' => $this->tax->calculate((float)$discount['price'] + ($manual_range['min'] - (float)$product_info['price']), $product_info['tax_class_id'], $config_tax),
							'max' => $this->tax->calculate((float)$discount['price'] + ($manual_range['max'] - (float)$product_info['price']), $product_info['tax_class_id'], $config_tax),
						);

						$discount_range = $this->format($discount_range);

						$discounts[] = array(
							'quantity' => $discount['quantity'],
							'price'    => $discount_range,
							'extax'    => $discount_extax_range,
						);

						if ($discount['quantity'] >= $quantity) {
							$price_range = $discount_range;
							$extax_range = $discount_extax_range;
						}
					}
				}

				$extax_range = $this->format($manual_range);
			} else {
				$price_range = $this->format($manual_range);

				if ((float)$product_info['special']) {
					$co = (float)$product_info['special'] / (float)$product_info['price'];

					$special_range = array(
						'min' => $manual_range['min'] * $co,
						'max' => $manual_range['max'] * $co,
					);

					$special_range = $this->format($special_range);
				} elseif ($this->model_catalog_product->getProductDiscounts($product_id)) {
					$discounts = array();

					foreach ($this->model_catalog_product->getProductDiscounts($product_id) as $discount) {
						$discount_range = array(
							'min' => (float)$discount['price'] + ($manual_range['min'] - (float)$product_info['price']),
							'max' => (float)$discount['price'] + ($manual_range['max'] - (float)$product_info['price']),
						);

						$discount_range = $this->format($discount_range);

						$discounts[] = array(
							'quantity' => $discount['quantity'],
							'price'    => $discount_range,
						);

						if ($discount['quantity'] >= $quantity) {
							$price_range = $discount_range;
						}
					}
				}
			}

			return array(
				'price'     => $price_range,
				'extax'     => $config_tax && isset($extax_range) ? $extax_range : null,
				'special'   => isset($special_range) ? $special_range : null,
				'discounts' => isset($discounts) ? $discounts : null,
			);
		}
	}

	// format currencies
	private function format($range) {
		$currency = $this->session->data['currency'];

		if ($range['min'] === $range['max']) {
			return $this->currency->format($range['min'], $currency);
		}

		$style = $this->config->get('module_price_range')['style'];
		$text = $this->config->get('module_price_range')['text'][$this->config->get('config_language_id')];

		if ($style === 'from') {
			$range['min'] = $this->currency->format($range['min'], $currency);

			return $text['from'] . ' ' . $range['min'];
		} elseif ($style === 'upto') {
			$range['max'] = $this->currency->format($range['max'], $currency);

			return $text['upto'] . ' ' . $range['max'];
		} else {
			$range['min'] = $this->currency->format($range['min'], $currency);
			$range['max'] = $this->currency->format($range['max'], $currency);

			return $range['min'] . ' - ' . $range['max'];
		}
	}

	// Returns min and max price values from DB
	private function get($product_id) {
		// $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'product WHERE product_id = "' . (int)$product_id . '"');
		$query = $this->db->query('SELECT min_price, max_price FROM ' . DB_PREFIX . "product WHERE product_id = '" . (int)$product_id . "'");

		$min = (float)$query->row['min_price'];
		$max = (float)$query->row['max_price'];

		if ($min >= $max) {
			return null;
		}

		return array(
			'min' => $min,
			'max' => $max,
		);
	}
}
