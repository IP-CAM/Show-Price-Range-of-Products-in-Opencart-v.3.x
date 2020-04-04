<?php

/*
This file is part of "Price Range M" project and subject to the terms
and conditions defined in file "LICENSE.txt", which is part of this source
code package and also available on the project page: https://git.io/JvBbw.
*/

class ModelExtensionModulePriceRange extends Model {
	public function getPriceRange($product_id = 0) {
		$price_range = array(
			'min_price' => 0,
			'max_price' => 0,
		);

		if ($product_id) {
			// $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'product WHERE product_id = "' . (int)$product_id . '"');
			$query = $this->db->query('SELECT min_price, max_price FROM ' . DB_PREFIX . "product WHERE product_id = '" . (int)$product_id . "'");

			$price_range['min_price'] = (float)$query->row['min_price'];
			$price_range['max_price'] = (float)$query->row['max_price'];
		}

		return $price_range;
	}

	public function addPriceRange($min_price = 0, $max_price = 0) {
		// Workaround to get the last product id
		$result = $this->db->query('SHOW TABLE STATUS LIKE "' . DB_PREFIX . 'product"');
		$product_id = ($result->row['Auto_increment'] - 1);

		if ($product_id) {
			$this->db->query('UPDATE ' . DB_PREFIX . 'product SET min_price = "' . (float)$min_price . '", max_price = "' . (float)$max_price . '" WHERE product_id = "' . (int)$product_id . '"');
		}
	}

	public function editPriceRange($product_id = 0, $min_price = 0, $max_price = 0) {
		if ($product_id) {
			$this->db->query('UPDATE ' . DB_PREFIX . 'product SET min_price = "' . (float)$min_price . '", max_price = "' . (float)$max_price . '" WHERE product_id = "' . (int)$product_id . '"');
		}
	}

	public function addTableColumns() {
		$this->db->query('ALTER TABLE ' . DB_PREFIX . 'product ADD IF NOT EXISTS min_price decimal(15,4) NOT NULL DEFAULT "0", ADD IF NOT EXISTS max_price decimal(15,4) NOT NULL DEFAULT "0"');
	}

	public function delTableColumns() {
		$this->db->query('ALTER TABLE ' . DB_PREFIX . 'product DROP IF EXISTS min_price, DROP IF EXISTS max_price');
	}

	public function hasTableColumns() {
		$query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'product');

		if (isset($query->row['min_price']) && isset($query->row['max_price'])) {
			return true;
		}

		return false;
	}
}
