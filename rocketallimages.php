<?php
/**
 * NOTICE OF LICENSE.
 *
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

    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName('ROCKET_ALLIMAGES_ORDERED');
    }

    public function getContent()
    {
        if (Tools::isSubmit('submitUpdate')) {
            Configuration::updateValue('ROCKET_ALLIMAGES_ORDERED', (bool) Tools::getValue('ROCKET_ALLIMAGES_ORDERED'));
        }

        return $this->renderForm();
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('Settings', array(), 'Admin.Global'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Display combinations image(s) First?', array(), 'Modules.rocketallimages.Admin'),
                        'desc' => $this->trans('If enabled, this use more resources.', array(), 'Modules.rocketallimages.Admin'),
                        'name' => 'ROCKET_ALLIMAGES_ORDERED',
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', array(), 'Admin.Global'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('No', array(), 'Admin.Global'),
                            ),
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;

        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitUpdate';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'ROCKET_ALLIMAGES_ORDERED' => Tools::getValue('ROCKET_ALLIMAGES_ORDERED', Configuration::get('ROCKET_ALLIMAGES_ORDERED')),
        );
    }

    public function hookActionGetProductPropertiesAfter($params)
    {
        if (null !== $this->context->controller && 'product' === $this->context->controller->php_self) {
            if (true == Configuration::get('ROCKET_ALLIMAGES_ORDERED')) {
                $combinations = Db::getInstance()->executeS(
                    'SELECT `id_product_attribute`
                    FROM `'._DB_PREFIX_.'product_attribute`
                    WHERE `id_product` = '.(int) $params['product']['id_product']
                );
                if (true == $this->haveImageForAllCombinations($combinations, $params['product']['id_product'])) {
                    $params['product']['allimages'] = array();
                    $product = $params['product'];
                    $allimages = $this->imageRetriever->getProductImages(
                        $product,
                        $this->context->language
                    );
                    foreach ($combinations as $combination) {
                        if ($product['id_product_attribute'] != $combination['id_product_attribute']) {
                            $product_next_combination = $params['product'];
                            $product_next_combination['id_product_attribute'] = $combination['id_product_attribute'];
                            $allimages2 = $this->imageRetriever->getProductImages(
                                $product_next_combination,
                                $this->context->language
                            );
                            $allimages = array_merge($allimages, $allimages2);
                        }
                    }
                    $allimages = array_unique($allimages, \SORT_REGULAR);
                    $params['product']['allimages'] = $allimages;
                } else {
                    $params['product']['allimages'] = array();
                    $product = $params['product'];
                    $product['id_product_attribute'] = 0;
                    $allimages = $this->imageRetriever->getProductImages(
                        $product,
                        $this->context->language
                    );
                    $params['product']['allimages'] = $allimages;
                }
            } else {
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

    public function haveImageForAllCombinations($combinations, $id_product)
    {
        foreach ($combinations as $combination) {
            $sql = 'SELECT `id_product_attribute`
                FROM `'._DB_PREFIX_.'product_attribute_image` pai
                LEFT JOIN `'._DB_PREFIX_.'image` im ON pai.id_image = im.id_image
                WHERE im.id_product = '.(int) $id_product.' AND id_product_attribute = '.(int) $combination['id_product_attribute'];
            if (!Db::getInstance()->getValue($sql)) {
                return false;
            }
        }

        return true;
    }
}
