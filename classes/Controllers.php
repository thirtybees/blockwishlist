<?php

namespace BlockWishListModule;

use BlockWishList;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class Controllers
 *
 * @package BlockWishListModule
 */
class Controllers
{
    /**
     * Buy wish list logic
     *
     * @return void
     * @throws \PrestaShopException
     */
    public static function buyWishList()
    {
        $error = '';

        // Instance of module class for translations
        $module = new BlockWishList();

        $token = \Tools::getValue('token');
        $idProduct = (int) \Tools::getValue('id_product');
        $idProductAttribute = (int) \Tools::getValue('id_product_attribute');
        if (\Configuration::get('PS_TOKEN_ENABLE') == 1 && strcmp(\Tools::getToken(false), \Tools::getValue('static_token'))) {
            $error = $module->l('Invalid token', 'buywishlistproduct');
        }

        if (!strlen($error) &&
            empty($token) === false &&
            empty($idProduct) === false
        ) {
            $wishlist = WishList::getByToken($token);
            if ($wishlist !== false) {
                WishList::addBoughtProduct($wishlist['id_wishlist'], $idProduct, $idProductAttribute, \Context::getContext()->cart->id, 1);
            }
        } else {
            $error = $module->l('You must log in', 'buywishlistproduct');
        }

        if (empty($error) === false) {
            echo $error;
        }
    }

    /**
     * @return void
     * @throws \PrestaShopException
     * @throws \SmartyException
     */
    public static function cart()
    {
        $context = \Context::getContext();
        $action = \Tools::getValue('action');
        $add = (!strcmp($action, 'add') ? 1 : 0);
        $delete = (!strcmp($action, 'delete') ? 1 : 0);
        $idWishlist = (int) \Tools::getValue('id_wishlist');
        $idProduct = (int) \Tools::getValue('id_product');
        $quantity = (int) \Tools::getValue('quantity');
        $idProductAttribute = (int) \Tools::getValue('id_product_attribute');

        // Instance of module class for translations
        $module = new BlockWishList();

        if (\Configuration::get('PS_TOKEN_ENABLE') == 1 &&
            strcmp(\Tools::getToken(false), \Tools::getValue('token')) &&
            $context->customer->isLogged() === true
        ) {
            echo $module->l('Invalid token', 'cart');
        }
        if ($context->customer->isLogged()) {
            if ($idWishlist && WishList::exists($idWishlist, $context->customer->id) === true) {
                $context->cookie->id_wishlist = (int) $idWishlist;
            }

            if ((int) $context->cookie->id_wishlist > 0 && !WishList::exists($context->cookie->id_wishlist, $context->customer->id)) {
                $context->cookie->id_wishlist = '';
            }

            if (empty($context->cookie->id_wishlist) === true || $context->cookie->id_wishlist == false) {
                $context->smarty->assign('error', true);
            }
            if (($add || $delete) && empty($idProduct) === false) {
                if (!isset($context->cookie->id_wishlist) || $context->cookie->id_wishlist == '') {
                    $wishlist = new WishList();
                    $wishlist->id_shop = $context->shop->id;
                    $wishlist->id_shop_group = $context->shop->id_shop_group;
                    $wishlist->default = 1;

                    $modWishlist = new BlockWishList();
                    $wishlist->name = $modWishlist->default_wishlist_name;
                    $wishlist->id_customer = (int) $context->customer->id;
                    list($us, $s) = explode(' ', microtime());
                    srand($s * $us);
                    $wishlist->token = strtoupper(substr(sha1(uniqid(rand(), true)._COOKIE_KEY_.$context->customer->id), 0, 16));
                    $wishlist->add();
                    $context->cookie->id_wishlist = (int) $wishlist->id;
                }
                if ($add && $quantity) {
                    WishList::addProduct($context->cookie->id_wishlist, $context->customer->id, $idProduct, $idProductAttribute, $quantity);
                } else {
                    if ($delete) {
                        WishList::removeProduct($context->cookie->id_wishlist, $context->customer->id, $idProduct, $idProductAttribute);
                    }
                }
            }
            $context->smarty->assign('products', WishList::getProductByIdCustomer($context->cookie->id_wishlist, $context->customer->id, $context->language->id, null, true));

            if (file_exists(_PS_THEME_DIR_.'modules/blockwishlist/blockwishlist-ajax.tpl')) {
                $context->smarty->display(_PS_THEME_DIR_.'modules/blockwishlist/blockwishlist-ajax.tpl');
            } elseif (file_exists(dirname(__FILE__).'/blockwishlist-ajax.tpl')) {
                $context->smarty->display(dirname(__FILE__).'/blockwishlist-ajax.tpl');
            } else {
                echo $module->l('No template found', 'cart');
            }
        } else {
            echo $module->l('You must be logged in to manage your wishlist.', 'cart');
        }
    }

    /**
     * @return void
     * @throws \PrestaShopException
     * @throws \SmartyException
     */
    public static function manageWishList()
    {
        $context = \Context::getContext();
        if ($context->customer->isLogged()) {
            $action = \Tools::getValue('action');
            $idWishlist = (int) \Tools::getValue('id_wishlist');
            $idProduct = (int) \Tools::getValue('id_product');
            $idProductAttribute = (int) \Tools::getValue('id_product_attribute');
            $quantity = (int) \Tools::getValue('quantity');
            $priority = \Tools::getValue('priority');
            $wishlist = new WishList((int) ($idWishlist));
            $refresh = (($_GET['refresh'] == 'true') ? 1 : 0);
            if (empty($idWishlist) === false) {
                if (!strcmp($action, 'update')) {
                    WishList::updateProduct($idWishlist, $idProduct, $idProductAttribute, $priority, $quantity);
                } else {
                    if (!strcmp($action, 'delete')) {
                        WishList::removeProduct($idWishlist, (int) $context->customer->id, $idProduct, $idProductAttribute);
                    }

                    $products = WishList::getProductByIdCustomer($idWishlist, $context->customer->id, $context->language->id);
                    $bought = WishList::getBoughtProduct($idWishlist);

                    for ($i = 0; $i < sizeof($products); ++$i) {
                        $obj = new \Product((int) ($products[$i]['id_product']), false, $context->language->id);
                        if (!\Validate::isLoadedObject($obj)) {
                            continue;
                        } else {
                            if ($products[$i]['id_product_attribute'] != 0) {
                                $combinationImgs = $obj->getCombinationImages($context->language->id);
                                if (isset($combinationImgs[$products[$i]['id_product_attribute']][0])) {
                                    $products[$i]['cover'] = $obj->id.'-'.$combinationImgs[$products[$i]['id_product_attribute']][0]['id_image'];
                                } else {
                                    $cover = \Product::getCover($obj->id);
                                    $products[$i]['cover'] = $obj->id.'-'.$cover['id_image'];
                                }
                            } else {
                                $images = $obj->getImages($context->language->id);
                                foreach ($images as $image) {
                                    if ($image['cover']) {
                                        $products[$i]['cover'] = $obj->id.'-'.$image['id_image'];
                                        break;
                                    }
                                }
                            }
                            if (!isset($products[$i]['cover'])) {
                                $products[$i]['cover'] = $context->language->iso_code.'-default';
                            }
                        }
                        $products[$i]['bought'] = false;
                        for ($j = 0, $k = 0; $j < sizeof($bought); ++$j) {
                            if ($bought[$j]['id_product'] == $products[$i]['id_product'] && $bought[$j]['id_product_attribute'] == $products[$i]['id_product_attribute']) {
                                $products[$i]['bought'][$k++] = $bought[$j];
                            }
                        }
                    }

                    $productBoughts = [];

                    foreach ($products as $product) {
                        if (sizeof($product['bought'])) {
                            $productBoughts[] = $product;
                        }
                    }
                    $context->smarty->assign(
                        [
                            'products'        => $products,
                            'productsBoughts' => $productBoughts,
                            'id_wishlist'     => $idWishlist,
                            'refresh'         => $refresh,
                            'token_wish'      => $wishlist->token,
                            'wishlists'       => WishList::getByIdCustomer(\Context::getContext()->cookie->id_customer),
                        ]
                    );

                    // Instance of module class for translations
                    $module = new BlockWishList();

                    if (file_exists(_PS_THEME_DIR_.'modules/blockwishlist/views/templates/front/managewishlist.tpl')) {
                        $context->smarty->display(_PS_THEME_DIR_.'modules/blockwishlist/views/templates/front/managewishlist.tpl');
                    } elseif (file_exists(__DIR__.'/views/templates/front/managewishlist.tpl')) {
                        $context->smarty->display(__DIR__.'/views/templates/front/managewishlist.tpl');
                    } elseif (file_exists(__DIR__.'/managewishlist.tpl')) {
                        $context->smarty->display(__DIR__.'/managewishlist.tpl');
                    } else {
                        echo $module->l('No template found', 'managewishlist');
                    }
                }
            }
        }
    }

    /**
     * @return void
     * @throws \PrestaShopException
     */
    public static function sendWishList()
    {
        $context = \Context::getContext();
        // Instance of module class for translations
        $module = new BlockWishList();

        if (\Configuration::get('PS_TOKEN_ENABLE') == 1 && strcmp(\Tools::getToken(false), \Tools::getValue('token')) && $context->customer->isLogged() === true) {
            exit($module->l('invalid token', 'sendwishlist'));
        }

        if ($context->customer->isLogged()) {
            $idWishlist = (int) \Tools::getValue('id_wishlist');
            if (empty($idWishlist) === true) {
                exit($module->l('Invalid wishlist', 'sendwishlist'));
            }
            for ($i = 1; empty($_POST['email'.$i]) === false; ++$i) {
                $to = \Tools::getValue('email'.$i);
                $wishlist = WishList::exists($idWishlist, $context->customer->id, true);
                if ($wishlist === false) {
                    exit($module->l('Invalid wishlist', 'sendwishlist'));
                }
                if (WishList::addEmail($idWishlist, $to) === false) {
                    exit($module->l('Wishlist send error', 'sendwishlist'));
                }
                $toName = strval(\Configuration::get('PS_SHOP_NAME'));
                $customer = $context->customer;
                if (\Validate::isLoadedObject($customer)) {
                    \Mail::Send(
                        $context->language->id,
                        'wishlist',
                        sprintf(\Mail::l('Message from %1$s %2$s', $context->language->id), $customer->lastname, $customer->firstname),
                        [
                            '{lastname}'  => $customer->lastname,
                            '{firstname}' => $customer->firstname,
                            '{wishlist}'  => $wishlist['name'],
                            '{message}'   => $context->link->getModuleLink('blockwishlist', 'view', ['token' => $wishlist['token']]),
                        ],
                        $to,
                        $toName,
                        $customer->email,
                        $customer->firstname.' '.$customer->lastname,
                        null,
                        null,
                        __DIR__.'/../mails/'
                    );
                }
            }
        }
    }
}
