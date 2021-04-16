<?php
/**
 * NOTICE OF LICENSE
 * @author      @prestarocket
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