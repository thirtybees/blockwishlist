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
 * Class BlockWishListViewModuleFrontController
 */
class BlockWishListViewModuleFrontController extends ModuleFrontController
{

    /**
     * BlockWishListViewModuleFrontController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->context = Context::getContext();
    }

    /**
     * Initialize content
     */
    public function initContent()
    {
        parent::initContent();
        $token = Tools::getValue('token');

        $module = new BlockWishList();

        if ($token) {
            $wishlist = WishList::getByToken($token);

            WishList::refreshWishList($wishlist['id_wishlist']);
            $products = WishList::getProductByIdCustomer((int) $wishlist['id_wishlist'], (int) $wishlist['id_customer'], $this->context->language->id, null, true);

            $nbProducts = count($products);
            $priorityNames = [0 => $module->l('High'), 1 => $module->l('Medium'), 2 => $module->l('Low')];

            for ($i = 0; $i < $nbProducts; ++$i) {
                $obj = new Product((int) $products[$i]['id_product'], true, $this->context->language->id);
                if (!Validate::isLoadedObject($obj)) {
                    continue;
                } else {
                    $products[$i]['priority_name'] = $priorityNames[$products[$i]['priority']];
                    $quantity = Product::getQuantity((int) $products[$i]['id_product'], $products[$i]['id_product_attribute']);
                    $products[$i]['attribute_quantity'] = $quantity;
                    $products[$i]['product_quantity'] = $quantity;
                    $products[$i]['allow_oosp'] = $obj->isAvailableWhenOutOfStock((int) $obj->out_of_stock);
                    if ($products[$i]['id_product_attribute'] != 0) {
                        $combinationImgs = $obj->getCombinationImages($this->context->language->id);
                        if (isset($combinationImgs[$products[$i]['id_product_attribute']][0])) {
                            $products[$i]['cover'] = $obj->id.'-'.$combinationImgs[$products[$i]['id_product_attribute']][0]['id_image'];
                        } else {
                            $cover = Product::getCover($obj->id);
                            $products[$i]['cover'] = $obj->id.'-'.$cover['id_image'];
                        }
                    } else {
                        $images = $obj->getImages($this->context->language->id);
                        foreach ($images as $image) {
                            if ($image['cover']) {
                                $products[$i]['cover'] = $obj->id.'-'.$image['id_image'];
                                break;
                            }
                        }
                    }
                    if (!isset($products[$i]['cover'])) {
                        $products[$i]['cover'] = $this->context->language->iso_code.'-default';
                    }
                }
                $products[$i]['bought'] = false;
            }

            WishList::incCounter((int) $wishlist['id_wishlist']);
            $ajax = Configuration::get('PS_BLOCK_CART_AJAX');

            $wishlists = WishList::getByIdCustomer((int) $wishlist['id_customer']);

            foreach ($wishlists as $key => $item) {
                if ($item['id_wishlist'] == $wishlist['id_wishlist']) {
                    unset($wishlists[$key]);
                    break;
                }
            }

            $this->context->smarty->assign(
                [
                    'current_wishlist' => $wishlist,
                    'token'            => $token,
                    'ajax'             => ((isset($ajax) && (int) $ajax == 1) ? '1' : '0'),
                    'wishlists'        => $wishlists,
                    'products'         => $products,
                ]
            );
        }
        $this->setTemplate('view.tpl');
    }
}
