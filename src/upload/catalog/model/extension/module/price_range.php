<?php

/*
This file is part of "Price Range" project and subject to the terms
and conditions defined in file "LICENSE.txt", which is part of this source
code package and also available on the project page: https://git.io/JvBbw.
*/

class ModelExtensionModulePriceRange extends Model {
	public function getPriceRange($product_id = 0) {
		if ($product_id) {
			$price_range = $this->get($product_id);

			if ($price_range) {
				$price_range = $this->calculate($product_id, $price_range['min_price'], $price_range['max_price']);
				$price_range = $this->format($price_range, $this->session->data['currency']);

				$text = $this->config->get('module_price_range')['text'][$this->config->get('config_language_id')];
				$style = $this->config->get('module_price_range')['style'];

				$price_range = $this->style($price_range, $style, $text);
			}

			return $price_range;
		}
	}

	// Returns min and max price values from DB
	private function get($product_id) {
		// $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'product WHERE product_id = "' . (int)$product_id . '"');
		$query = $this->db->query('SELECT min_price, max_price FROM ' . DB_PREFIX . "product WHERE product_id = '" . (int)$product_id . "'");

		$min_price = 0;

		if (0 != (float)$query->row['min_price']) {
			$min_price = (float)$query->row['min_price'];
		}

		$max_price = 0;

		if (0 != (float)$query->row['max_price']) {
			$max_price = (float)$query->row['max_price'];
		}

		if ((0 == $min_price && 0 == $max_price) || $min_price >= $max_price) {
			$min_max = null;
		} else {
			$min_max = array(
				'min_price' => $min_price,
				'max_price' => $max_price,
			);
		}

		return $min_max;
	}

	// Returns ranges for price, discounts, special and extax
	private function calculate($product_id, $min_price = 0, $max_price = 0) {
		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		$price = (float)$product_info['price'];
		$special = (float)$product_info['special'];

		$config_tax = $this->config->get('config_tax');

		$discounts = array();

		foreach ($this->model_catalog_product->getProductDiscounts($product_id) as $discount) {
			// getProductDiscounts gets array already sorted by ASC for quantity, priority, price

			$min_discount = $discount['price'] + ($min_price - $price);
			$max_discount = $discount['price'] + ($max_price - $price);

			if ($config_tax) {
				$min_extax = $min_discount;
				$max_extax = $max_discount;

				$min_discount = $this->tax->calculate($min_discount, $product_info['tax_class_id'], $config_tax);
				$max_discount = $this->tax->calculate($max_discount, $product_info['tax_class_id'], $config_tax);
			}

			$discounts[] = array(
				'quantity'  => $discount['quantity'],
				'min'       => $min_discount,
				'max'       => $max_discount,
				'min_extax' => $config_tax ? $min_extax : null,
				'max_extax' => $config_tax ? $max_extax : null,
			);
		}

		if ($special) {
			$min_special = $special + ($min_price - $price);
			$max_special = $special + ($max_price - $price);

			if ($config_tax) {
				$min_extax = $min_special;
				$max_extax = $max_special;

				$min_special = $this->tax->calculate($min_special, $product_info['tax_class_id'], $config_tax);
				$max_special = $this->tax->calculate($max_special, $product_info['tax_class_id'], $config_tax);
			}
		} else {
			if ($config_tax) {
				$min_extax = $min_price;
				$max_extax = $max_price;

				$min_price = $this->tax->calculate($min_price, $product_info['tax_class_id'], $config_tax);
				$max_price = $this->tax->calculate($max_price, $product_info['tax_class_id'], $config_tax);
			}
		}

		$price_range = array(
			'price' => array(
				'min' => $min_price,
				'max' => $max_price,
			),
			'extax' => $config_tax
				? array(
					'min' => $min_extax,
					'max' => $max_extax,
				)
				: null,
			'special' => $special
				? array(
					'min' => $min_special,
					'max' => $max_special,
				)
				: null,
			'discounts' => $discounts ?: null,
		);

		return $price_range;
	}

	// format currencies
	private function format($price_range, $currency) {
		if (isset($price_range['price'])) {
			$price_range['price']['min'] = $this->currency->format($price_range['price']['min'], $currency);
			$price_range['price']['max'] = $this->currency->format($price_range['price']['max'], $currency);
		}

		if (isset($price_range['discounts'])) {
			foreach ($price_range['discounts'] as &$discount) {
				$discount['min'] = $this->currency->format($discount['min'], $currency);
				$discount['min_extax'] = $this->currency->format($discount['min_extax'], $currency);
				$discount['max'] = $this->currency->format($discount['max'], $currency);
				$discount['max_extax'] = $this->currency->format($discount['max_extax'], $currency);
			}

			unset($discount);
		}

		if (isset($price_range['special'])) {
			$price_range['special']['min'] = $this->currency->format($price_range['special']['min'], $currency);
			$price_range['special']['max'] = $this->currency->format($price_range['special']['max'], $currency);
		}

		if ($this->config->get('config_tax') && isset($price_range['extax'])) {
			$price_range['extax']['min'] = $this->currency->format($price_range['extax']['min'], $currency);
			$price_range['extax']['max'] = $this->currency->format($price_range['extax']['max'], $currency);
		}

		return $price_range;
	}

	private function style($price_range, $style, $text) {
		$this->load->language('product/product');

		// $text_discount = $this->language->get('text_discount');
		$text_extax = $this->language->get('text_tax');

		if ($style === 'from') {
			$from_text = $text['from'];

			if (isset($price_range['price'])) {
				$price_range['price'] = $from_text . ' ' . $price_range['price']['min'];
			}

			if (isset($price_range['discounts'])) {
				foreach ($price_range['discounts'] as $key => $value) {
					$price_range['discounts'][$key] = array(
						'quantity'    => $value['quantity'],
						'price'       => $from_text . ' ' . $value['max'],
						'extax'       => $from_text . ' ' . $value['max_extax'],
					);
				}
			}

			if (isset($price_range['special'])) {
				$price_range['special'] = $from_text . ' ' . $price_range['special']['min'];
			}

			if ($this->config->get('config_tax') && isset($price_range['extax'])) {
				$price_range['extax'] = $from_text . ' ' . $price_range['extax']['min'];
			}
		} elseif ($style === 'upto') {
			$upto_text = $text['upto'];

			if (isset($price_range['price'])) {
				$price_range['price'] = $upto_text . ' ' . $price_range['price']['max'];
			}

			if (isset($price_range['discounts'])) {
				foreach ($price_range['discounts'] as $key => $value) {
					$price_range['discounts'][$key] = array(
						'quantity'    => $value['quantity'],
						'price'       => $upto_text . ' ' . $value['max'],
						'extax'       => $upto_text . ' ' . $value['max_extax'],
					);
				}
			}

			if (isset($price_range['special'])) {
				$price_range['special'] = $upto_text . ' ' . $price_range['special']['max'];
			}

			if ($this->config->get('config_tax') && isset($price_range['extax'])) {
				$price_range['extax'] = $upto_text . ' ' . $price_range['extax']['max'];
			}
		} else {
			if (isset($price_range['price'])) {
				$price_range['price'] = $price_range['price']['min'] . ' - ' . $price_range['price']['max'];
			}

			if (isset($price_range['discounts'])) {
				foreach ($price_range['discounts'] as $key => $value) {
					$price_range['discounts'][$key] = array(
						'quantity'    => $value['quantity'],
						'price'       => $value['min'] . ' - ' . $value['max'],
						'extax'       => $value['min_extax'] . ' - ' . $value['max_extax'],
					);
				}
			}

			if (isset($price_range['special'])) {
				$price_range['special'] = $price_range['special']['min'] . ' - ' . $price_range['special']['max'];
			}

			if ($this->config->get('config_tax') && isset($price_range['extax'])) {
				$price_range['extax'] = $price_range['extax']['min'] . ' - ' . $price_range['extax']['max'];
			}
		}

		return $price_range;
	}
}
