<?php

use BlockWishListModule\Controllers;
use BlockWishListModule\WishList;

if (!defined('_TB_VERSION_')) {
    exit;
}

class BlockwishlistcartModuleFrontController extends ModuleFrontController
{
    public function init()
    {
        Controllers::cart();

        die();
    }
}
