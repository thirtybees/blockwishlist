<?php

use BlockWishListModule\Controllers;

if (!defined('_TB_VERSION_')) {
    exit;
}

class BlockwishlistcartModuleFrontController extends ModuleFrontController
{
    /**
     * @return void
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function init()
    {
        Controllers::cart();

        die();
    }
}
