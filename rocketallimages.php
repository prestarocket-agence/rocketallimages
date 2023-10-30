<?php
/**
 * NOTICE OF LICENSE.
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
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Manage products images');
        $this->description = $this->l('Add all products images on product page');

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
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Settings', [], 'Admin.Global'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Display combinations image(s) First?', [], 'Modules.rocketallimages.Admin'),
                        'desc' => $this->trans('If enabled, this use more resources.', [], 'Modules.rocketallimages.Admin'),
                        'name' => 'ROCKET_ALLIMAGES_ORDERED',
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', [], 'Admin.Global'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('No', [], 'Admin.Global'),
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;

        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitUpdate';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$fields_form]);
    }

    public function getConfigFieldsValues()
    {
        return [
            'ROCKET_ALLIMAGES_ORDERED' => Tools::getValue('ROCKET_ALLIMAGES_ORDERED', Configuration::get('ROCKET_ALLIMAGES_ORDERED')),
        ];
    }

    public function hookActionGetProductPropertiesAfter($params)
    {
        if (null !== $this->context->controller && 'product' === $this->context->controller->php_self) {
            if (true == Configuration::get('ROCKET_ALLIMAGES_ORDERED')) {
                $combinations = $this->getProductCombinations((int) $params['product']['id_product']);
                if (null === $combinations) {
                    return;
                }

                if (true === $this->haveImageForAllCombinations($combinations, (int) $params['product']['id_product'])) {
                    $params['product']['allimages'] = [];
                    $product = $params['product'];
                    $allimages = $this->imageRetriever->getProductImages(
                        $product,
                        $this->context->language
                    );

                    foreach ($combinations as $combination) {
                        if ((int) $product['id_product_attribute'] !== (int) $combination['id_product_attribute']) {
                            $productNextCombination = $params['product'];
                            $productNextCombination['id_product_attribute'] = $combination['id_product_attribute'];
                            $allimages2 = $this->imageRetriever->getProductImages(
                                $productNextCombination,
                                $this->context->language
                            );

                            $allimages = array_merge($allimages, $allimages2);
                        }
                    }

                    $allimages = array_unique($allimages, SORT_REGULAR);
                    $params['product']['allimages'] = $allimages;
                } else {
                    $params['product']['allimages'] = [];
                    $product = $params['product'];
                    $product['id_product_attribute'] = 0;
                    $allimages = $this->imageRetriever->getProductImages(
                        $product,
                        $this->context->language
                    );
                    $params['product']['allimages'] = $allimages;
                }
            } else {
                $params['product']['allimages'] = [];
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

    private function haveImageForAllCombinations(array $combinations, int $productId): bool
    {
        foreach ($combinations as $combination) {
            if (false === $this->haveImageForThisCombination((int) $combination['id_product_attribute'], $productId)) {
                return false;
            }
        }

        return true;
    }

    private function haveImageForThisCombination(int $productAttributeId, int $productId): bool
    {
        return (bool) Db::getInstance()->getValue((new DbQuery())
            ->select('pai.`id_product_attribute`')
            ->from('product_attribute_image', 'pai')
            ->leftJoin('image', 'im', 'pai.`id_image` = im.`id_image`')
            ->where('im.`id_product` = ' . (int) $productId)
            ->where('pai.`id_product_attribute` = ' . (int) $productAttributeId)
        );
    }

    private function getProductCombinations(int $productId): ?array
    {
        $combs = Db::getInstance()->executeS((new DbQuery())
            ->select('`id_product_attribute`')
            ->from('product_attribute')
            ->where('`id_product` = ' . (int) $productId)
        );

        return $combs ? $combs : null;
    }
}
