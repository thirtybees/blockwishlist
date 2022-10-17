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

namespace BlockWishListModule;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class WishList
 */
class WishList extends \ObjectModel
{
    // @codingStandardsIgnoreStart
    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table'   => 'wishlist',
        'primary' => 'id_wishlist',
        'fields'  => [
            'id_customer'   => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId', 'required' => true],
            'token'         => ['type' => self::TYPE_STRING, 'validate' => 'isMessage',    'required' => true],
            'name'          => ['type' => self::TYPE_STRING, 'validate' => 'isMessage',    'required' => true],
            'date_add'      => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
            'date_upd'      => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
            'id_shop'       => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId'],
            'id_shop_group' => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId'],
            'default'       => ['type' => self::TYPE_BOOL,   'validate' => 'isUnsignedId'],
        ],
    ];
    /** @var integer Wishlist ID */
    public $id;
    /** @var integer Customer ID */
    public $id_customer;
    /** @var integer Token */
    public $token;
    /** @var integer Name */
    public $name;
    /** @var string Object creation date */
    public $date_add;
    /** @var string Object last modification date */
    public $date_upd;
    /** @var string Object last modification date */
    public $id_shop;
    /** @var string Object last modification date */
    public $id_shop_group;
    /** @var integer default */
    public $default;
    // @codingStandardsIgnoreEnd

    /**
     * Increment counter
     *
     * @param int $idWishlist
     *
     * @return bool succeed
     * @throws \PrestaShopException
     */
    public static function incCounter($idWishlist)
    {
        if (!\Validate::isUnsignedId($idWishlist)) {
            die (\Tools::displayError());
        }
        $sql = new \DbQuery();
        $sql->select('`counter`');
        $sql->from('wishlist');
        $sql->where('`id_wishlist` = '.(int) $idWishlist);
        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);
        if ($result == false || !count($result) || empty($result) === true) {
            return (false);
        }

        return \Db::getInstance()->update(
            'wishlist',
            [
                'counter' => (int) $result['counter'] + 1,
            ],
            '`id_wishlist` = '.(int) $idWishlist
        );
    }

    /**
     * @param string $name
     *
     * @return false|null|string
     * @throws \PrestaShopException
     */
    public static function isExistsByNameForUser($name)
    {
        if (\Shop::getContextShopID()) {
            $shopRestriction = 'AND id_shop = '.(int) \Shop::getContextShopID();
        } elseif (\Shop::getContextShopGroupID()) {
            $shopRestriction = 'AND id_shop_group = '.(int) \Shop::getContextShopGroupID();
        } else {
            $shopRestriction = '';
        }

        $context = \Context::getContext();

        return \Db::getInstance()->getValue(
            '
			SELECT COUNT(*) AS total
			FROM `'._DB_PREFIX_.'wishlist`
			WHERE `name` = \''.pSQL($name).'\'
				AND `id_customer` = '.(int) $context->customer->id.'
				'.$shopRestriction
        );
    }

    /**
     * Return true if wishlist exists else false
     *
     * @param int $idWishlist
     * @param int $idCustomer
     * @param bool | array $return
     *
     * @return bool|array exists
     * @throws \PrestaShopException
     */
    public static function exists($idWishlist, $idCustomer, $return = false)
    {
        if (!\Validate::isUnsignedId($idWishlist) || !\Validate::isUnsignedId($idCustomer)) {
            die (\Tools::displayError());
        }
        $result = \Db::getInstance()->getRow(
            '
		SELECT `id_wishlist`, `name`, `token`
		  FROM `'._DB_PREFIX_.'wishlist`
		WHERE `id_wishlist` = '.(int) ($idWishlist).'
		AND `id_customer` = '.(int) ($idCustomer).'
		AND `id_shop` = '.(int) \Context::getContext()->shop->id
        );
        if (empty($result) === false && $result != false && sizeof($result)) {
            if ($return === false) {
                return (true);
            } else {
                return ($result);
            }
        }

        return (false);
    }

    /**
     * Get Customers having a wishlist
     *
     * @return array Results
     * @throws \PrestaShopException
     */
    public static function getCustomers()
    {
        $cacheId = 'WishList::getCustomers';
        if (!\Cache::isStored($cacheId)) {
            $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                '
				SELECT c.`id_customer`, c.`firstname`, c.`lastname`
				  FROM `'._DB_PREFIX_.'wishlist` w
				INNER JOIN `'._DB_PREFIX_.'customer` c ON c.`id_customer` = w.`id_customer`
				ORDER BY c.`firstname` ASC'
            );
            \Cache::store($cacheId, $result);
        }

        return \Cache::retrieve($cacheId);
    }

    /**
     * Get ID wishlist by Token
     *
     * @return array|false Results
     * @throws \PrestaShopException
     */
    public static function getByToken($token)
    {
        if (!\Validate::isMessage($token)) {
            die (\Tools::displayError());
        }

        return (\Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
            '
		SELECT w.`id_wishlist`, w.`name`, w.`id_customer`, c.`firstname`, c.`lastname`
		  FROM `'._DB_PREFIX_.'wishlist` w
		INNER JOIN `'._DB_PREFIX_.'customer` c ON c.`id_customer` = w.`id_customer`
		WHERE `token` = \''.pSQL($token).'\''
        ));
    }

    /**
     * Get Wishlists by Customer ID
     *
     * @return array Results
     * @throws \PrestaShopException
     */
    public static function getByIdCustomer($idCustomer)
    {
        if (!\Validate::isUnsignedId($idCustomer)) {
            die (\Tools::displayError());
        }
        if (\Shop::getContextShopID()) {
            $shopRestriction = 'AND id_shop = '.(int) \Shop::getContextShopID();
        } elseif (\Shop::getContextShopGroupID()) {
            $shopRestriction = 'AND id_shop_group = '.(int) \Shop::getContextShopGroupID();
        } else {
            $shopRestriction = '';
        }

        $cacheId = 'WhishList::getByIdCustomer_'.(int) $idCustomer.'-'.(int) \Shop::getContextShopID().'-'.(int) \Shop::getContextShopGroupID();
        if (!\Cache::isStored($cacheId)) {
            $result = \Db::getInstance()->executeS(
                '
			SELECT w.`id_wishlist`, w.`name`, w.`token`, w.`date_add`, w.`date_upd`, w.`counter`, w.`default`
			FROM `'._DB_PREFIX_.'wishlist` w
			WHERE `id_customer` = '.(int) ($idCustomer).'
			'.$shopRestriction.'
			ORDER BY w.`name` ASC'
            );
            \Cache::store($cacheId, $result);
        }

        return \Cache::retrieve($cacheId);
    }

    /**
     * @param int $idWishlist
     *
     * @return void
     * @throws \PrestaShopException
     */
    public static function refreshWishList($idWishlist)
    {
        $oldCarts = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            '
		SELECT wp.id_product, wp.id_product_attribute, wpc.id_cart, UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(wpc.date_add) AS timecart
		FROM `'._DB_PREFIX_.'wishlist_product_cart` wpc
		JOIN `'._DB_PREFIX_.'wishlist_product` wp ON (wp.id_wishlist_product = wpc.id_wishlist_product)
		JOIN `'._DB_PREFIX_.'cart` c ON  (c.id_cart = wpc.id_cart)
		JOIN `'._DB_PREFIX_.'cart_product` cp ON (wpc.id_cart = cp.id_cart)
		LEFT JOIN `'._DB_PREFIX_.'orders` o ON (o.id_cart = c.id_cart)
		WHERE (wp.id_wishlist='.(int) ($idWishlist).' AND o.id_cart IS NULL)
		HAVING timecart  >= 3600*6'
        );

        if (is_array($oldCarts)) {
            foreach ($oldCarts as $oldCart) {
                \Db::getInstance()->execute(
                    '
					DELETE FROM `'._DB_PREFIX_.'cart_product`
					WHERE id_cart='.(int) ($oldCart['id_cart']).' AND id_product='.(int) ($oldCart['id_product']).' AND id_product_attribute='.(int) ($oldCart['id_product_attribute'])
                );
            }
        }

        $freshwish = \Db::getInstance()->executeS(
            '
			SELECT  wpc.id_cart, wpc.id_wishlist_product
			FROM `'._DB_PREFIX_.'wishlist_product_cart` wpc
			JOIN `'._DB_PREFIX_.'wishlist_product` wp ON (wpc.id_wishlist_product = wp.id_wishlist_product)
			JOIN `'._DB_PREFIX_.'cart` c ON (c.id_cart = wpc.id_cart)
			LEFT JOIN `'._DB_PREFIX_.'cart_product` cp ON (cp.id_cart = wpc.id_cart AND cp.id_product = wp.id_product AND cp.id_product_attribute = wp.id_product_attribute)
			WHERE (wp.id_wishlist = '.(int) ($idWishlist).' AND ((cp.id_product IS NULL AND cp.id_product_attribute IS NULL)))
			'
        );
        $res = \Db::getInstance()->executeS(
            '
			SELECT wp.id_wishlist_product, cp.quantity AS cart_quantity, wpc.quantity AS wish_quantity, wpc.id_cart
			FROM `'._DB_PREFIX_.'wishlist_product_cart` wpc
			JOIN `'._DB_PREFIX_.'wishlist_product` wp ON (wp.id_wishlist_product = wpc.id_wishlist_product)
			JOIN `'._DB_PREFIX_.'cart` c ON (c.id_cart = wpc.id_cart)
			JOIN `'._DB_PREFIX_.'cart_product` cp ON (cp.id_cart = wpc.id_cart AND cp.id_product = wp.id_product AND cp.id_product_attribute = wp.id_product_attribute)
			WHERE wp.id_wishlist='.(int) ($idWishlist)
        );

        if (isset($res) && $res != false) {
            foreach ($res as $refresh) {
                if ($refresh['wish_quantity'] > $refresh['cart_quantity']) {
                    \Db::getInstance()->execute(
                        '
						UPDATE `'._DB_PREFIX_.'wishlist_product`
						SET `quantity`= `quantity` + '.((int) ($refresh['wish_quantity']) - (int) ($refresh['cart_quantity'])).'
						WHERE id_wishlist_product='.(int) ($refresh['id_wishlist_product'])
                    );
                    \Db::getInstance()->execute(
                        '
						UPDATE `'._DB_PREFIX_.'wishlist_product_cart`
						SET `quantity`='.(int) ($refresh['cart_quantity']).'
						WHERE id_wishlist_product='.(int) ($refresh['id_wishlist_product']).' AND id_cart='.(int) ($refresh['id_cart'])
                    );
                }
            }
        }
        if (isset($freshwish) && $freshwish != false) {
            foreach ($freshwish as $prodcustomer) {
                \Db::getInstance()->execute(
                    '
					UPDATE `'._DB_PREFIX_.'wishlist_product` SET `quantity`=`quantity` +
					(
						SELECT `quantity` FROM `'._DB_PREFIX_.'wishlist_product_cart`
						WHERE `id_wishlist_product`='.(int) ($prodcustomer['id_wishlist_product']).' AND `id_cart`='.(int) ($prodcustomer['id_cart']).'
					)
					WHERE `id_wishlist_product`='.(int) ($prodcustomer['id_wishlist_product']).' AND `id_wishlist`='.(int) ($idWishlist)
                );
                \Db::getInstance()->execute(
                    '
					DELETE FROM `'._DB_PREFIX_.'wishlist_product_cart`
					WHERE `id_wishlist_product`='.(int) ($prodcustomer['id_wishlist_product']).' AND `id_cart`='.(int) ($prodcustomer['id_cart'])
                );
            }
        }
    }

    /**
     * Get Wishlist products by Customer ID
     *
     * @return array Results
     * @throws \PrestaShopException
     */
    public static function getProductByIdCustomer($idWishlist, $idCustomer, $idLang, $idProduct = null, $quantity = false)
    {
        if (!\Validate::isUnsignedId($idCustomer) ||
            !\Validate::isUnsignedId($idLang) ||
            !\Validate::isUnsignedId($idWishlist)
        ) {
            die (\Tools::displayError());
        }
        $products = \Db::getInstance()->executeS(
            '
		SELECT wp.`id_product`, wp.`quantity`, p.`quantity` AS product_quantity, pl.`name`, wp.`id_product_attribute`, wp.`priority`, pl.link_rewrite, cl.link_rewrite AS category_rewrite
		FROM `'._DB_PREFIX_.'wishlist_product` wp
		LEFT JOIN `'._DB_PREFIX_.'product` p ON p.`id_product` = wp.`id_product`
		'.\Shop::addSqlAssociation('product', 'p').'
		LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.`id_product` = wp.`id_product`'.\Shop::addSqlRestrictionOnLang('pl').'
		LEFT JOIN `'._DB_PREFIX_.'wishlist` w ON w.`id_wishlist` = wp.`id_wishlist`
		LEFT JOIN `'._DB_PREFIX_.'category_lang` cl ON cl.`id_category` = product_shop.`id_category_default` AND cl.id_lang='.(int) $idLang.\Shop::addSqlRestrictionOnLang('cl').'
		WHERE w.`id_customer` = '.(int) ($idCustomer).'
		AND pl.`id_lang` = '.(int) ($idLang).'
		AND wp.`id_wishlist` = '.(int) ($idWishlist).
            (empty($idProduct) === false ? ' AND wp.`id_product` = '.(int) ($idProduct) : '').
            ($quantity == true ? ' AND wp.`quantity` != 0' : '').'
		GROUP BY p.id_product, wp.id_product_attribute'
        );
        if (empty($products) === true || !sizeof($products)) {
            return [];
        }
        for ($i = 0; $i < sizeof($products); ++$i) {
            if (isset($products[$i]['id_product_attribute']) && \Validate::isUnsignedInt($products[$i]['id_product_attribute'])) {
                $result = \Db::getInstance()->executeS(
                    '
				SELECT al.`name` AS attribute_name, pa.`quantity` AS "attribute_quantity"
				FROM `'._DB_PREFIX_.'product_attribute_combination` pac
				LEFT JOIN `'._DB_PREFIX_.'attribute` a ON (a.`id_attribute` = pac.`id_attribute`)
				LEFT JOIN `'._DB_PREFIX_.'attribute_group` ag ON (ag.`id_attribute_group` = a.`id_attribute_group`)
				LEFT JOIN `'._DB_PREFIX_.'attribute_lang` al ON (a.`id_attribute` = al.`id_attribute` AND al.`id_lang` = '.(int) ($idLang).')
				LEFT JOIN `'._DB_PREFIX_.'attribute_group_lang` agl ON (ag.`id_attribute_group` = agl.`id_attribute_group` AND agl.`id_lang` = '.(int) ($idLang).')
				LEFT JOIN `'._DB_PREFIX_.'product_attribute` pa ON (pac.`id_product_attribute` = pa.`id_product_attribute`)
				'.\Shop::addSqlAssociation('product_attribute', 'pa').'
				WHERE pac.`id_product_attribute` = '.(int) ($products[$i]['id_product_attribute'])
                );
                $products[$i]['attributes_small'] = '';
                if ($result) {
                    foreach ($result as $k => $row) {
                        $products[$i]['attributes_small'] .= $row['attribute_name'].', ';
                    }
                }
                $products[$i]['attributes_small'] = rtrim($products[$i]['attributes_small'], ', ');
                if (isset($result[0])) {
                    $products[$i]['attribute_quantity'] = $result[0]['attribute_quantity'];
                }
            } else {
                $products[$i]['attribute_quantity'] = $products[$i]['product_quantity'];
            }
        }

        return ($products);
    }

    /**
     * Get Wishlists number products by Customer ID
     *
     * @return array Results
     * @throws \PrestaShopException
     */
    public static function getInfosByIdCustomer($idCustomer)
    {
        if (\Shop::getContextShopID()) {
            $shopRestriction = 'AND id_shop = '.(int) \Shop::getContextShopID();
        } elseif (\Shop::getContextShopGroupID()) {
            $shopRestriction = 'AND id_shop_group = '.(int) \Shop::getContextShopGroupID();
        } else {
            $shopRestriction = '';
        }

        if (!\Validate::isUnsignedId($idCustomer)) {
            die (\Tools::displayError());
        }

        return (\Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            '
		SELECT SUM(wp.`quantity`) AS nbProducts, wp.`id_wishlist`
		  FROM `'._DB_PREFIX_.'wishlist_product` wp
		INNER JOIN `'._DB_PREFIX_.'wishlist` w ON (w.`id_wishlist` = wp.`id_wishlist`)
		WHERE w.`id_customer` = '.(int) ($idCustomer).'
		'.$shopRestriction.'
		GROUP BY w.`id_wishlist`
		ORDER BY w.`name` ASC'
        ));
    }

    /**
     * Add product to ID wishlist
     *
     * @return boolean succeed
     * @throws \PrestaShopException
     */
    public static function addProduct($idWishlist, $idCustomer, $idProduct, $idProductAttribute, $quantity)
    {
        if (!\Validate::isUnsignedId($idWishlist) ||
            !\Validate::isUnsignedId($idCustomer) ||
            !\Validate::isUnsignedId($idProduct) ||
            !\Validate::isUnsignedId($quantity)
        ) {
            die (\Tools::displayError());
        }
        $sql = new \DbQuery();
        $sql->select('wp.`quantity`');
        $sql->from('wishlist_product', 'wp');
        $sql->innerJoin('wishlist', 'w', 'w.`id_wishlist` = wp.`id_wishlist`');
        $sql->where('wp.`id_wishlist` = '.(int) $idWishlist);
        $sql->where('w.`id_customer` = '.(int) $idCustomer);
        $sql->where('wp.`id_product` = '.(int) $idProduct);
        $sql->where('wp.`id_product_attribute` = '.(int) $idProductAttribute);
        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);
        if (is_array($result)) {
            if (($result['quantity'] + $quantity) <= 0) {
                return (static::removeProduct($idWishlist, $idCustomer, $idProduct, $idProductAttribute));
            } else {
                return \Db::getInstance()->update(
                    'wishlist_product',
                    [
                        'quantity' => (int) $quantity + (int) $result['quantity'],
                    ],
                    '`id_wishlist` = '.(int) $idWishlist.' AND `id_product` = '.(int) $idProduct.' AND `id_product_attribute` = '.(int) $idProductAttribute
                );
            }
        } else {
            return \Db::getInstance()->insert(
                'wishlist_product',
                [
                    bqSQL(static::$definition['primary']) => (int) $idWishlist,
                    'id_product'                          => (int) $idProduct,
                    'id_product_attribute'                => (int) $idProductAttribute,
                    'quantity'                            => (int) $quantity,
                    'priority'                            => 1,
                ]
            );
        }

    }

    /**
     * Remove product from wishlist
     *
     * @return boolean succeed
     * @throws \PrestaShopException
     */
    public static function removeProduct($idWishlist, $idCustomer, $idProduct, $idProductAttribute)
    {
        if (!\Validate::isUnsignedId($idWishlist) ||
            !\Validate::isUnsignedId($idCustomer) ||
            !\Validate::isUnsignedId($idProduct)
        ) {
            die (\Tools::displayError());
        }
        $result = \Db::getInstance()->getRow(
            '
		SELECT w.`id_wishlist`, wp.`id_wishlist_product`
		FROM `'._DB_PREFIX_.'wishlist` w
		LEFT JOIN `'._DB_PREFIX_.'wishlist_product` wp ON (wp.`id_wishlist` = w.`id_wishlist`)
		WHERE `id_customer` = '.(int) ($idCustomer).'
		AND w.`id_wishlist` = '.(int) ($idWishlist)
        );
        if (empty($result) === true ||
            $result === false ||
            !sizeof($result) ||
            $result['id_wishlist'] != $idWishlist
        ) {
            return (false);
        }
        // Delete product in wishlist_product_cart
        \Db::getInstance()->execute(
            '
		DELETE FROM `'._DB_PREFIX_.'wishlist_product_cart`
		WHERE `id_wishlist_product` = '.(int) ($result['id_wishlist_product'])
        );

        return \Db::getInstance()->execute(
            '
		DELETE FROM `'._DB_PREFIX_.'wishlist_product`
		WHERE `id_wishlist` = '.(int) ($idWishlist).'
		AND `id_product` = '.(int) ($idProduct).'
		AND `id_product_attribute` = '.(int) ($idProductAttribute)
        );
    }

    /**
     * Update product to wishlist
     *
     * @return boolean succeed
     * @throws \PrestaShopException
     */
    public static function updateProduct($idWishlist, $idProduct, $idProductAttribute, $priority, $quantity)
    {
        if (!\Validate::isUnsignedId($idWishlist) ||
            !\Validate::isUnsignedId($idProduct) ||
            !\Validate::isUnsignedId($quantity) ||
            $priority < 0 || $priority > 2
        ) {
            die (\Tools::displayError());
        }

        return (\Db::getInstance()->execute(
            '
		UPDATE `'._DB_PREFIX_.'wishlist_product` SET
		`priority` = '.(int) ($priority).',
		`quantity` = '.(int) ($quantity).'
		WHERE `id_wishlist` = '.(int) ($idWishlist).'
		AND `id_product` = '.(int) ($idProduct).'
		AND `id_product_attribute` = '.(int) ($idProductAttribute)
        ));
    }

    /**
     * Return bought product by ID wishlist
     *
     * @return array results
     * @throws \PrestaShopException
     */
    public static function getBoughtProduct($idWishlist)
    {

        if (!\Validate::isUnsignedId($idWishlist)) {
            die (\Tools::displayError());
        }

        return (\Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            '
		SELECT wp.`id_product`, wp.`id_product_attribute`, wpc.`quantity`, wpc.`date_add`, cu.`lastname`, cu.`firstname`
		FROM `'._DB_PREFIX_.'wishlist_product_cart` wpc
		JOIN `'._DB_PREFIX_.'wishlist_product` wp ON (wp.id_wishlist_product = wpc.id_wishlist_product)
		JOIN `'._DB_PREFIX_.'cart` ca ON (ca.id_cart = wpc.id_cart)
		JOIN `'._DB_PREFIX_.'customer` cu ON (cu.`id_customer` = ca.`id_customer`)
		WHERE wp.`id_wishlist` = '.(int) ($idWishlist)
        ));
    }

    /**
     * Add bought product
     *
     * @return boolean succeed
     * @throws \PrestaShopException
     */
    public static function addBoughtProduct($idWishlist, $idProduct, $idProductAttribute, $idCart, $quantity)
    {
        if (!\Validate::isUnsignedId($idWishlist) ||
            !\Validate::isUnsignedId($idProduct) ||
            !\Validate::isUnsignedId($quantity)
        ) {
            die (\Tools::displayError());
        }
        $result = \Db::getInstance()->getRow(
            '
			SELECT `quantity`, `id_wishlist_product`
		  FROM `'._DB_PREFIX_.'wishlist_product` wp
			WHERE `id_wishlist` = '.(int) ($idWishlist).'
			AND `id_product` = '.(int) ($idProduct).'
			AND `id_product_attribute` = '.(int) ($idProductAttribute)
        );

        if (!sizeof($result) || ($result['quantity'] - $quantity) < 0 || $quantity > $result['quantity']) {
            return (false);
        }

        \Db::getInstance()->executeS(
            '
			SELECT *
			FROM `'._DB_PREFIX_.'wishlist_product_cart`
			WHERE `id_wishlist_product`='.(int) ($result['id_wishlist_product']).' AND `id_cart`='.(int) ($idCart)
        );

        if (\Db::getInstance()->NumRows() > 0) {
            $result2 = \Db::getInstance()->execute(
                '
				UPDATE `'._DB_PREFIX_.'wishlist_product_cart`
				SET `quantity`=`quantity` + '.(int) ($quantity).'
				WHERE `id_wishlist_product`='.(int) ($result['id_wishlist_product']).' AND `id_cart`='.(int) ($idCart)
            );
        } else {
            $result2 = \Db::getInstance()->execute(
                '
				INSERT INTO `'._DB_PREFIX_.'wishlist_product_cart`
				(`id_wishlist_product`, `id_cart`, `quantity`, `date_add`) VALUES(
				'.(int) ($result['id_wishlist_product']).',
				'.(int) ($idCart).',
				'.(int) ($quantity).',
				\''.pSQL(date('Y-m-d H:i:s')).'\')'
            );
        }

        if ($result2 === false) {
            return (false);
        }

        return (\Db::getInstance()->execute(
            '
			UPDATE `'._DB_PREFIX_.'wishlist_product` SET
			`quantity` = '.(int) ($result['quantity'] - $quantity).'
			WHERE `id_wishlist` = '.(int) ($idWishlist).'
			AND `id_product` = '.(int) ($idProduct).'
			AND `id_product_attribute` = '.(int) ($idProductAttribute)
        ));
    }

    /**
     * Add email to wishlist
     *
     * @param int    $idWishlist
     * @param string $email
     *
     * @return bool succeed
     * @throws \PrestaShopException
     */
    public static function addEmail($idWishlist, $email)
    {
        if (!\Validate::isUnsignedId($idWishlist) || empty($email) || !\Validate::isEmail($email)) {
            return false;
        }

        return (\Db::getInstance()->execute(
            '
		INSERT INTO `'._DB_PREFIX_.'wishlist_email` (`id_wishlist`, `email`, `date_add`) VALUES(
		'.(int) ($idWishlist).',
		\''.pSQL($email).'\',
		\''.pSQL(date('Y-m-d H:i:s')).'\')'
        ));
    }

    /**
     * Get email from wishlist
     *
     * @return array results
     * @throws \PrestaShopException
     */
    public static function getEmail($idWishlist, $idCustomer)
    {
        if (!\Validate::isUnsignedId($idWishlist) || !\Validate::isUnsignedId($idCustomer)) {
            die (\Tools::displayError());
        }

        return (\Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            '
		SELECT we.`email`, we.`date_add`
		  FROM `'._DB_PREFIX_.'wishlist_email` we
		INNER JOIN `'._DB_PREFIX_.'wishlist` w ON w.`id_wishlist` = we.`id_wishlist`
		WHERE we.`id_wishlist` = '.(int) ($idWishlist).'
		AND w.`id_customer` = '.(int) ($idCustomer)
        ));
    }

    /**
     * Return if there is a default already set
     *
     * @return boolean
     * @throws \PrestaShopException
     */
    public static function isDefault($idCustomer)
    {
        return (bool) \Db::getInstance()->getValue('SELECT * FROM `'._DB_PREFIX_.'wishlist` WHERE `id_customer` = '.$idCustomer.' AND `default` = 1');
    }

    /**
     * @return bool
     * @throws \PrestaShopException
     */
    public function delete()
    {
        \Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'wishlist_email` WHERE `id_wishlist` = '.(int) ($this->id));
        \Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'wishlist_product` WHERE `id_wishlist` = '.(int) ($this->id));
        if ($this->default) {
            $result = \Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'wishlist` WHERE `id_customer` = '.(int) $this->id_customer.' AND `id_wishlist` != '.(int) $this->id.' LIMIT 1');
            foreach ($result as $res) {
                \Db::getInstance()->update('wishlist', ['default' => '1'], 'id_wishlist = '.(int) $res['id_wishlist']);
            }
        }
        if (isset($this->context->cookie->id_wishlist)) {
            unset($this->context->cookie->id_wishlist);
        }

        return (parent::delete());
    }

    /**
     * Set current WishList as default
     *
     * @return boolean
     * @throws \PrestaShopException
     */
    public function setDefault()
    {
        if ($default = $this->getDefault($this->id_customer)) {
            \Db::getInstance()->update('wishlist', ['default' => '0'], 'id_wishlist = '.$default[0]['id_wishlist']);
        }

        return \Db::getInstance()->update('wishlist', ['default' => '1'], 'id_wishlist = '.$this->id);
    }

    /**
     * @param int $idCustomer
     *
     * @return array|false|null|\PDOStatement
     * @throws \PrestaShopException
     */
    public static function getDefault($idCustomer)
    {
        return \Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'wishlist` WHERE `id_customer` = '.$idCustomer.' AND `default` = 1');
    }
}
