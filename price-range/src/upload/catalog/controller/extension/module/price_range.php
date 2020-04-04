<?php

/*
This file is part of "Price Range" project and subject to the terms
and conditions defined in file "LICENSE.txt", which is part of this source
code package and also available on the project page: https://git.io/JvBbw.
*/

class ControllerExtensionModulePriceRange extends Controller {
	// catalog/view/extension/module/*/before
	// catalog/view/product/*/before
	public function showPriceRange(&$route, &$data) {
		if ($this->config->get('module_price_range_status')) {
			if (($this->config->get('config_customer_price') && $this->customer->isLogged()) ||
				!$this->config->get('config_customer_price')
			) {
				$this->load->model('extension/module/price_range');

				if (isset($data['products']) && is_array($data['products'])) {
					foreach ($data['products'] as &$product) {
						$price_range = $this->model_extension_module_price_range->getPriceRanges($product['product_id']);

						if (isset($price_range)) {
							if (isset($price_range['price']) && isset($product['price'])) {
								$product['price'] = $price_range['price'];
							}

							if (isset($price_range['special']) && !empty($product['special'])) {
								$product['special'] = $price_range['special'];
							}

							if (isset($price_range['extax']) && !empty($product['tax'])) {
								$product['tax'] = $price_range['extax'];
							}
						}
					}

					unset($product);
				}

				if (isset($data['product_id']) && $this->config->get('module_price_range')['view'] == 'full') {
					$price_range = $this->model_extension_module_price_range->getPriceRanges($data['product_id']);

					if (isset($price_range)) {
						if (isset($price_range['price'])) {
							$data['price'] = $price_range['price'];
						}

						if (isset($price_range['special'])) {
							$data['special'] = $price_range['special'];
						}

						if (isset($price_range['discounts'])) {
							$data['discounts'] = $price_range['discounts'];
						}

						if (isset($price_range['extax'])) {
							$data['tax'] = $price_range['extax'];
						}
					}
				}
			}
		}
	}
}
