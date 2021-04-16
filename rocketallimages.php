<?php
/**
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 * You must not modify, adapt or create derivative works of this source code
 *
 * @author      Prestarocket <prestarocket@gmail.com>
 * @copyright   SARL JUST WEB
 * @license     Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Adapter\Image\ImageRetriever;

class rocketallimages extends Module
{
    private $imageRetriever = null;

    public function __construct()
    {
        $this->name = 'rocketallimages';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Prestarocket';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Add all products images on product page');

        $this->imageRetriever = new ImageRetriever($this->context->link);
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('actionGetProductPropertiesAfter');
    }

    public function hookActionGetProductPropertiesAfter($params)
    {
        if ($this->context->controller->php_self === 'product') {
            $params['product']['allimages'] = array();
            $product = $params['product'];
            $product['id_product_attribute'] = 0;
            $allimages = $this->imageRetriever->getProductImages(
                $product,
                $this->context->language
            );

            $params['product']['allimages'] = $allimages;
        }
    }
}