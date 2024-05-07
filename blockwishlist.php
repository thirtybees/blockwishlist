<?php
/**
 * 2007-2016 PrestaShop
 *
 * thirty bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017-2024 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017-2024 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

use BlockWishListModule\WishList;

if (!defined('_TB_VERSION_')) {
    exit;
}

require_once __DIR__.'/classes/autoload.php';

/**
 * Class BlockWishList
 */
class BlockWishList extends Module
{
    const INSTALL_SQL_FILE = 'install.sql';

    /**
     * @var string
     */
    private $html;

    /**
     * @var string
     */
    public $default_wishlist_name;

    /**
     * BlockWishList constructor.
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->name = 'blockwishlist';
        $this->tab = 'front_office_features';
        $this->version = '2.1.0';
        $this->author = 'thirty bees';
        $this->need_instance = 0;

        $this->controllers = ['mywishlist', 'view', 'buywishlist', 'managewishlist', 'sendwishlist'];

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Wishlist block');
        $this->description = $this->l('Adds a block containing the customer\'s wishlists.');
        $this->default_wishlist_name = $this->l('My wishlist');
        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => '1.6.99.99'];
        $this->html = '';
    }

    /**
     * @return bool
     * @throws PrestaShopException
     */
    public function reset()
    {
        if (!$this->uninstall(false)) {
            return false;
        }
        if (!$this->install(false)) {
            return false;
        }

        return true;
    }

    /**
     * @param bool $deleteParams
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function uninstall($deleteParams = true)
    {
        if (($deleteParams && !$this->deleteTables()) || !parent::uninstall()) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     * @throws PrestaShopException
     */
    private function deleteTables()
    {
        return Db::getInstance()->execute(
            'DROP TABLE IF EXISTS
			`'._DB_PREFIX_.'wishlist`,
			`'._DB_PREFIX_.'wishlist_email`,
			`'._DB_PREFIX_.'wishlist_product`,
			`'._DB_PREFIX_.'wishlist_product_cart`'
        );
    }

    /**
     * @param bool $deleteParams
     *
     * @return bool
     * @throws PrestaShopException
     */
    public function install($deleteParams = true)
    {
        if ($deleteParams) {
            if (!file_exists(dirname(__FILE__).'/'.self::INSTALL_SQL_FILE)) {
                return (false);
            } else {
                if (!$sql = file_get_contents(dirname(__FILE__).'/'.self::INSTALL_SQL_FILE)) {
                    return (false);
                }
            }
            $sql = str_replace(['PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], $sql);
            $sql = preg_split("/;\s*[\r\n]+/", $sql);
            foreach ($sql as $query) {
                if ($query) {
                    if (!Db::getInstance()->execute(trim($query))) {
                        return false;
                    }
                }
            }
        }

        if (!parent::install() ||
            !$this->registerHook('rightColumn') ||
            !$this->registerHook('productActions') ||
            !$this->registerHook('cart') ||
            !$this->registerHook('customerAccount') ||
            !$this->registerHook('header') ||
            !$this->registerHook('adminCustomers') ||
            !$this->registerHook('displayProductListFunctionalButtons') ||
            !$this->registerHook('top')
        ) {
            return false;
        }
        /* This hook is optional */
        $this->registerHook('displayMyAccountBlock');

        return true;
    }

    /**
     * @return string
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function getContent()
    {
        //$this->context->link->getProductLink($val['id_product']);

        if (Tools::isSubmit('viewblockwishlist') && $id = Tools::getValue('id_product')) {
            Tools::redirect($this->context->link->getProductLink($id));
        } elseif (Tools::isSubmit('submitSettings')) {
            $activated = Tools::getValue('activated');
            if ($activated != 0 && $activated != 1) {
                $this->html .= '<div class="alert error alert-danger">'.$this->l('Activate module : Invalid choice.').'</div>';
            }
            $this->html .= '<div class="conf confirm alert alert-success">'.$this->l('Settings updated').'</div>';
        }

        $this->html .= $this->renderJS();
        $this->html .= $this->renderForm();
        if (Tools::getValue('id_customer') && Tools::getValue('id_wishlist')) {
            $this->html .= $this->renderList((int) Tools::getValue('id_wishlist'));
        }

        return $this->html;
    }

    /**
     * @return string
     */
    public function renderJS()
    {
        return "<script>
			$(document).ready(function () { $('#id_customer, #id_wishlist').change( function () { $('#module_form').submit();}); });
		</script>";
    }

    /**
     * @return string
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function renderForm()
    {
        $customers = [];
        foreach (WishList::getCustomers() as $c) {
            $customers[$c['id_customer']]['id_customer'] = $c['id_customer'];
            $customers[$c['id_customer']]['name'] = $c['firstname'].' '.$c['lastname'];
        }

        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Listing'),
                    'icon'  => 'icon-cogs',
                ],
                'input'  => [
                    [
                        'type'    => 'select',
                        'label'   => $this->l('Customers :'),
                        'name'    => 'id_customer',
                        'options' => [
                            'default' => ['value' => 0, 'label' => $this->l('Choose customer')],
                            'query'   => $customers,
                            'id'      => 'id_customer',
                            'name'    => 'name',
                        ],
                    ],
                ],
            ],
        ];

        if ($idCustomer = Tools::getValue('id_customer')) {
            $wishlists = WishList::getByIdCustomer($idCustomer);
            $fieldsForm['form']['input'][] = [
                'type'    => 'select',
                'label'   => $this->l('Wishlist :'),
                'name'    => 'id_wishlist',
                'options' => [
                    'default' => ['value' => 0, 'label' => $this->l('Choose wishlist')],
                    'query'   => $wishlists,
                    'id'      => 'id_wishlist',
                    'name'    => 'name',
                ],
            ];
        }

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name
            .'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        ];

        return $helper->generateForm([$fieldsForm]);
    }

    /**
     * @return array
     */
    public function getConfigFieldsValues()
    {
        return [
            'id_customer' => Tools::getValue('id_customer'),
            'id_wishlist' => Tools::getValue('id_wishlist'),
        ];
    }

    /**
     * @param int $idWishlist
     *
     * @return string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function renderList($idWishlist)
    {
        $wishlist = new WishList($idWishlist);
        $products = WishList::getProductByIdCustomer($idWishlist, $wishlist->id_customer, $this->context->language->id);

        foreach ($products as $key => $val) {
            $image = Image::getCover($val['id_product']);
            $products[$key]['image'] = $this->context->link->getImageLink($val['link_rewrite'], $image['id_image'], ImageType::getFormatedName('small'));
        }

        $fieldsList = [
            'image'            => [
                'title' => $this->l('Image'),
                'type'  => 'image',
            ],
            'name'             => [
                'title' => $this->l('Product'),
                'type'  => 'text',
            ],
            'attributes_small' => [
                'title' => $this->l('Combination'),
                'type'  => 'text',
            ],
            'quantity'         => [
                'title' => $this->l('Quantity'),
                'type'  => 'text',
            ],
            'priority'         => [
                'title'  => $this->l('Priority'),
                'type'   => 'priority',
                'values' => [$this->l('High'), $this->l('Medium'), $this->l('Low')],
            ],
        ];

        $helper = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = true;
        $helper->no_link = true;
        $helper->actions = ['view'];
        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->identifier = 'id_product';
        $helper->title = $this->l('Product list');
        $helper->table = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->tpl_vars = ['priority' => [$this->l('High'), $this->l('Medium'), $this->l('Low')]];

        return $helper->generateList($products, $fieldsList);
    }

    /**
     * @param $params
     * @return string
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function hookDisplayProductListFunctionalButtons($params)
    {
        //TODO : Add cache
        if ($this->context->customer->isLogged()) {
            $this->smarty->assign('wishlists', Wishlist::getByIdCustomer($this->context->customer->id));
        }

        $this->smarty->assign('product', $params['product']);

        return $this->display(__FILE__, 'blockwishlist_button.tpl');
    }

    /**
     * @param $params
     * @return string
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function hookTop($params)
    {
        if ($this->context->customer->isLogged()) {
            $wishlists = Wishlist::getByIdCustomer($this->context->customer->id);
            if (empty($this->context->cookie->id_wishlist) === true ||
                WishList::exists($this->context->cookie->id_wishlist, $this->context->customer->id) === false
            ) {
                if (!count($wishlists)) {
                    $idWishlist = false;
                } else {
                    $idWishlist = (int) $wishlists[0]['id_wishlist'];
                    $this->context->cookie->id_wishlist = (int) $idWishlist;
                }
            } else {
                $idWishlist = $this->context->cookie->id_wishlist;
            }

            $this->smarty->assign(
                [
                    'id_wishlist'       => $idWishlist,
                    'isLogged'          => true,
                    'wishlist_products' => ($idWishlist == false ? false : WishList::getProductByIdCustomer(
                        $idWishlist,
                        $this->context->customer->id,
                        $this->context->language->id,
                        null,
                        true
                    )),
                    'wishlists'         => $wishlists,
                    'ptoken'            => Tools::getToken(false),
                ]
            );
        } else {
            $this->smarty->assign(['wishlist_products' => false, 'wishlists' => false]);
        }

        return $this->display(__FILE__, 'blockwishlist_top.tpl');
    }

    /**
     * @param $params
     * @return void
     * @throws PrestaShopException
     */
    public function hookHeader($params)
    {
        $this->context->controller->addCSS(($this->_path).'blockwishlist.css', 'all');
        $this->context->controller->addJS(($this->_path).'js/ajax-wishlist.js');

        $this->smarty->assign(['wishlist_link' => $this->context->link->getModuleLink('blockwishlist', 'mywishlist')]);
    }

    /**
     * @param $params
     * @return string
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function hookLeftColumn($params)
    {
        return $this->hookRightColumn($params);
    }

    /**
     * @param $params
     * @return string
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function hookRightColumn($params)
    {
        if ($this->context->customer->isLogged()) {
            $wishlists = Wishlist::getByIdCustomer($this->context->customer->id);
            if (empty($this->context->cookie->id_wishlist) === true ||
                WishList::exists($this->context->cookie->id_wishlist, $this->context->customer->id) === false
            ) {
                if (!count($wishlists)) {
                    $idWishlist = false;
                } else {
                    $idWishlist = (int) $wishlists[0]['id_wishlist'];
                    $this->context->cookie->id_wishlist = (int) $idWishlist;
                }
            } else {
                $idWishlist = $this->context->cookie->id_wishlist;
            }
            $this->smarty->assign(
                [
                    'id_wishlist'       => $idWishlist,
                    'isLogged'          => true,
                    'wishlist_products' => ($idWishlist == false ? false : WishList::getProductByIdCustomer(
                        $idWishlist,
                        $this->context->customer->id, $this->context->language->id, null, true
                    )),
                    'wishlists'         => $wishlists,
                    'ptoken'            => Tools::getToken(false),
                ]
            );
        } else {
            $this->smarty->assign(['wishlist_products' => false, 'wishlists' => false]);
        }

        return ($this->display(__FILE__, 'blockwishlist.tpl'));
    }

    /**
     * @param $params
     * @return string
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function hookProductActions($params)
    {
        $cookie = $params['cookie'];

        $this->smarty->assign(
            [
                'id_product' => (int) Tools::getValue('id_product'),
            ]
        );

        if (isset($cookie->id_customer)) {
            $this->smarty->assign(
                [
                    'wishlists' => WishList::getByIdCustomer($cookie->id_customer),
                ]
            );
        }

        return ($this->display(__FILE__, 'blockwishlist-extra.tpl'));
    }

    /**
     * Display Error from controler
     *
     * @param $params
     * @return string
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function hookDisplayMyAccountBlock($params)
    {
        return $this->hookCustomerAccount($params);
    }

    /**
     * @param $params
     * @return string
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function hookCustomerAccount($params)
    {
        return $this->display(__FILE__, 'my-account.tpl');
    }

    /**
     * @param $params
     * @return string|void
     * @throws PrestaShopException
     */
    public function hookAdminCustomers($params)
    {
        $customer = new Customer((int) $params['id_customer']);
        if (!Validate::isLoadedObject($customer)) {
            throw new PrestaShopException("Customer not found");
        }

        $this->html = '<h2>'.$this->l('Wishlists').'</h2>';

        $wishlists = WishList::getByIdCustomer((int) $customer->id);
        if (!count($wishlists)) {
            $this->html .= $customer->lastname.' '.$customer->firstname.' '.$this->l('No wishlist.');
        } else {
            $this->html .= '<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" method="post" id="listing">';

            $idWishlist = (int) Tools::getValue('id_wishlist');
            if (!$idWishlist) {
                $idWishlist = $wishlists[0]['id_wishlist'];
            }

            $this->html .= '<span>'.$this->l('Wishlist').': </span> <select name="id_wishlist" onchange="$(\'#listing\').submit();">';

            if (is_array($wishlists)) {
                foreach ($wishlists as $wishlist) {
                    $this->html .= '<option value="'.(int) $wishlist['id_wishlist'].'"';
                    if ($wishlist['id_wishlist'] == $idWishlist) {
                        $this->html .= ' selected="selected"';
                    }
                    $this->html .= '>'.htmlentities($wishlist['name'], ENT_COMPAT, 'UTF-8').'</option>';
                }
            }
            $this->html .= '</select>';

            $this->displayProducts((int) $idWishlist);

            $this->html .= '</form><br />';

            return $this->html;
        }

    }

    /**
     * @param $idWishlist
     * @return void
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function displayProducts($idWishlist)
    {
        $wishlist = new WishList($idWishlist);
        $products = WishList::getProductByIdCustomer($idWishlist, $wishlist->id_customer, $this->context->language->id);
        $nbProducts = count($products);
        for ($i = 0; $i < $nbProducts; ++$i) {
            $obj = new Product((int) $products[$i]['id_product'], false, $this->context->language->id);
            if (!Validate::isLoadedObject($obj)) {
                continue;
            } else {
                $images = $obj->getImages($this->context->language->id);
                foreach ($images as $image) {
                    if ($image['cover']) {
                        $products[$i]['cover'] = $obj->id.'-'.$image['id_image'];
                        break;
                    }
                }
                if (!isset($products[$i]['cover'])) {
                    $products[$i]['cover'] = $this->context->language->iso_code.'-default';
                }
            }
        }
        $this->html .= '
		<table class="table">
			<thead>
				<tr>
					<th class="first_item" style="width:600px;">'.$this->l('Product').'</th>
					<th class="item" style="text-align:center;width:150px;">'.$this->l('Quantity').'</th>
					<th class="item" style="text-align:center;width:150px;">'.$this->l('Priority').'</th>
				</tr>
			</thead>
			<tbody>';
        $priority = [$this->l('High'), $this->l('Medium'), $this->l('Low')];
        foreach ($products as $product) {
            $this->html .= '
				<tr>
					<td class="first_item">
						<img src="'.$this->context->link->getImageLink(
                    $product['link_rewrite'], $product['cover'],
                    ImageType::getFormatedName('small')
                ).'" alt="'.htmlentities($product['name'], ENT_COMPAT, 'UTF-8').'" style="float:left;" />
						'.$product['name'];
            if (isset($product['attributes_small'])) {
                $this->html .= '<br /><i>'.htmlentities($product['attributes_small'], ENT_COMPAT, 'UTF-8').'</i>';
            }
            $this->html .= '
					</td>
					<td class="item" style="text-align:center;">'.(int) $product['quantity'].'</td>
					<td class="item" style="text-align:center;">'.$priority[(int) $product['priority'] % 3].'</td>
				</tr>';
        }
        $this->html .= '</tbody></table>';
    }

    /**
     * @return string
     */
    public function errorLogged()
    {
        return $this->l('You must be logged in to manage your wishlists.');
    }
}
