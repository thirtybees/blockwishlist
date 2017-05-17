<?php
/**
 * 2007-2016 PrestaShop
 *
 * thirty bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */
use BlockWishListModule\WishList;

/**
 * @since 1.5.0
 */
class BlockWishListMyWishListModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    /**
     * BlockWishListMyWishListModuleFrontController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->context = Context::getContext();
    }

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();
        $action = Tools::getValue('action');

        if (!Tools::isSubmit('myajax')) {
            $this->assign();
        } elseif (!empty($action) && method_exists($this, 'ajaxProcess'.Tools::toCamelCase($action))) {
            $this->{'ajaxProcess'.Tools::toCamelCase($action)}();
        } else {
            die(Tools::jsonEncode(['error' => 'method doesn\'t exist']));
        }
    }

    /**
     * Assign wishlist template
     */
    public function assign()
    {
        $errors = [];

        if ($this->context->customer->isLogged()) {
            $add = Tools::getIsset('add');
            $add = (empty($add) === false ? 1 : 0);
            $delete = Tools::getIsset('deleted');
            $delete = (empty($delete) === false ? 1 : 0);
            $default = Tools::getIsset('default');
            $default = (empty($default) === false ? 1 : 0);
            $idWishlist = Tools::getValue('id_wishlist');
            if (Tools::isSubmit('submitWishlist')) {
                if (Configuration::get('PS_TOKEN_ACTIVATED') == 1 && strcmp(Tools::getToken(), Tools::getValue('token'))) {
                    $errors[] = $this->module->l('Invalid token', 'mywishlist');
                }
                if (!count($errors)) {
                    $name = Tools::getValue('name');
                    if (empty($name)) {
                        $errors[] = $this->module->l('You must specify a name.', 'mywishlist');
                    }
                    if (WishList::isExistsByNameForUser($name)) {
                        $errors[] = $this->module->l('This name is already used by another list.', 'mywishlist');
                    }

                    if (!count($errors)) {
                        $wishlist = new WishList();
                        $wishlist->id_shop = $this->context->shop->id;
                        $wishlist->id_shop_group = $this->context->shop->id_shop_group;
                        $wishlist->name = $name;
                        $wishlist->id_customer = (int) $this->context->customer->id;
                        !$wishlist->isDefault($wishlist->id_customer) ? $wishlist->default = 1 : '';
                        list($us, $s) = explode(' ', microtime());
                        srand($s * $us);
                        $wishlist->token = strtoupper(substr(sha1(uniqid(rand(), true)._COOKIE_KEY_.$this->context->customer->id), 0, 16));
                        $wishlist->add();

                        Mail::Send(
                            $this->context->language->id,
                            'wishlink',
                            Mail::l('Your wishlist\'s link', $this->context->language->id),
                            [
                                '{wishlist}' => $wishlist->name,
                                '{message}'  => $this->context->link->getModuleLink('blockwishlist', 'view', ['token' => $wishlist->token]),
                            ],
                            $this->context->customer->email,
                            $this->context->customer->firstname.' '.$this->context->customer->lastname,
                            null,
                            strval(Configuration::get('PS_SHOP_NAME')),
                            null,
                            null,
                            $this->module->getLocalPath().'mails/'
                        );

                        Tools::redirect($this->context->link->getModuleLink('blockwishlist', 'mywishlist'));
                    }
                }
            } else {
                if ($add) {
                    WishList::addCartToWishlist($this->context->customer->id, Tools::getValue('id_wishlist'), $this->context->language->id);
                } elseif ($delete && empty($idWishlist) === false) {
                    $wishlist = new WishList((int) $idWishlist);
                    if ($this->context->customer->isLogged() && $this->context->customer->id == $wishlist->id_customer && Validate::isLoadedObject($wishlist)) {
                        $wishlist->delete();
                    } else {
                        $errors[] = $this->module->l('Cannot delete this wishlist', 'mywishlist');
                    }
                } elseif ($default) {
                    $wishlist = new WishList((int) $idWishlist);
                    if ($this->context->customer->isLogged() && $this->context->customer->id == $wishlist->id_customer && Validate::isLoadedObject($wishlist)) {
                        $wishlist->setDefault();
                    } else {
                        $errors[] = $this->module->l('Cannot delete this wishlist', 'mywishlist');
                    }
                }
            }
            $this->context->smarty->assign('wishlists', WishList::getByIdCustomer($this->context->customer->id));
            $this->context->smarty->assign('nbProducts', WishList::getInfosByIdCustomer($this->context->customer->id));
        } else {
            Tools::redirect('index.php?controller=authentication&back='.urlencode($this->context->link->getModuleLink('blockwishlist', 'mywishlist')));
        }

        $this->context->smarty->assign(
            [
                'id_customer' => (int) $this->context->customer->id,
                'errors'      => $errors,
                'form_link'   => $errors,
            ]
        );

        $this->setTemplate('mywishlist.tpl');
    }

    public function ajaxProcessDeleteList()
    {
        if (!$this->context->customer->isLogged()) {
            die(
            Tools::jsonEncode(
                [
                    'success' => false,
                    'error'   => $this->module->l('You aren\'t logged in', 'mywishlist'),
                ]
            )
            );
        }

        $idWishlist = Tools::getValue('id_wishlist');

        $wishlist = new WishList((int) $idWishlist);
        if (Validate::isLoadedObject($wishlist) && $wishlist->id_customer == $this->context->customer->id) {
            $defaultChange = $wishlist->default ? true : false;
            $idCustomer = $wishlist->id_customer;
            $wishlist->delete();
        } else {
            die(
            Tools::jsonEncode(
                [
                    'success' => false,
                    'error'   => $this->module->l('Cannot delete this wishlist', 'mywishlist'),
                ]
            )
            );
        }

        if ($defaultChange) {
            $array = WishList::getDefault($idCustomer);

            if (count($array)) {
                die(
                Tools::jsonEncode(
                    [
                        'success'    => true,
                        'id_default' => $array[0]['id_wishlist'],
                    ]
                )
                );
            }
        }

        die(Tools::jsonEncode(['success' => true]));
    }

    public function ajaxProcessSetDefault()
    {
        if (!$this->context->customer->isLogged()) {
            die(
            Tools::jsonEncode(
                [
                    'success' => false,
                    'error'   => $this->module->l('You aren\'t logged in', 'mywishlist'),
                ]
            )
            );
        }

        $default = Tools::getIsset('default');
        $default = (empty($default) === false ? 1 : 0);
        $idWishlist = Tools::getValue('id_wishlist');

        if ($default) {
            $wishlist = new WishList((int) $idWishlist);
            if (Validate::isLoadedObject($wishlist) && $wishlist->id_customer == $this->context->customer->id && $wishlist->setDefault()) {
                die(Tools::jsonEncode(['success' => true]));
            }
        }

        die(Tools::jsonEncode(['error' => true]));
    }

    public function ajaxProcessProductChangeWishlist()
    {
        if (!$this->context->customer->isLogged()) {
            die(
            Tools::jsonEncode(
                [
                    'success' => false,
                    'error'   => $this->module->l('You aren\'t logged in', 'mywishlist'),
                ]
            )
            );
        }

        $idProduct = (int) Tools::getValue('id_product');
        $idProductAttribute = (int) Tools::getValue('id_product_attribute');
        $quantity = (int) Tools::getValue('quantity');
        $priority = (int) Tools::getValue('priority');
        $idOldWishlist = (int) Tools::getValue('id_old_wishlist');
        $idNewWishlist = (int) Tools::getValue('id_new_wishlist');
        $newWishlist = new WishList((int) $idNewWishlist);
        $oldWishlist = new WishList((int) $idOldWishlist);

        //check the data is ok
        if (!$idProduct || !is_int($idProductAttribute) || !$quantity ||
            !is_int($priority) || ($priority < 0 && $priority > 2) || !$idOldWishlist || !$idNewWishlist ||
            (Validate::isLoadedObject($newWishlist) && $newWishlist->id_customer != $this->context->customer->id) ||
            (Validate::isLoadedObject($oldWishlist) && $oldWishlist->id_customer != $this->context->customer->id)
        ) {
            die(Tools::jsonEncode(['success' => false, 'error' => $this->module->l('Error while moving product to another list', 'mywishlist')]));
        }

        $res = true;
        $check = (int) Db::getInstance()->getValue(
            'SELECT quantity FROM '._DB_PREFIX_.'wishlist_product
			WHERE `id_product` = '.$idProduct.' AND `id_product_attribute` = '.$idProductAttribute.' AND `id_wishlist` = '.$idNewWishlist
        );

        if ($check) {
            $res &= $oldWishlist->removeProduct($idOldWishlist, $this->context->customer->id, $idProduct, $idProductAttribute);
            $res &= $newWishlist->updateProduct($idNewWishlist, $idProduct, $idProductAttribute, $priority, $quantity + $check);
        } else {
            $res &= $oldWishlist->removeProduct($idOldWishlist, $this->context->customer->id, $idProduct, $idProductAttribute);
            $res &= $newWishlist->addProduct($idNewWishlist, $this->context->customer->id, $idProduct, $idProductAttribute, $quantity);
        }

        if (!$res) {
            die(Tools::jsonEncode(['success' => false, 'error' => $this->module->l('Error while moving product to another list', 'mywishlist')]));
        }
        die(Tools::jsonEncode(['success' => true, 'msg' => $this->module->l('The product has been correctly moved', 'mywishlist')]));
    }
}
