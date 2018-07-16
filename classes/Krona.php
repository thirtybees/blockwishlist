<?php

namespace BlockWishListModule;

if (!defined('_TB_VERSION_')) {
  exit;
}

class Krona
{
  const ACTION_PRODUCT_ADDED = 'product_added';
  const ACTION_WISHLIST_SENT = 'wishlisht_sent';
  const ACTION_WISHLIST_CREATED = 'wishlisht_created';

  public static function getActions() {
    return [
      self::ACTION_PRODUCT_ADDED => [
        'title'   => 'Add product to wishlist',
        'message' => 'You received {points} points for adding product to your wishlist',
      ],
      self::ACTION_WISHLIST_SENT => [
        'title'   => 'Send wishlist by email',
        'message' => 'You received {points} points for sending wishlist via email',
      ],
      self::ACTION_WISHLIST_CREATED => [
        'title'   => 'Created wishlist',
        'message' => 'You received {points} points for creating wishlist',
      ],
    ];
  }

  public static function productAdded($productId) {
    self::triggerAction(self::ACTION_PRODUCT_ADDED, self::getProductLink($productId));
  }

  public static function sendWishList() {
    self::triggerAction(self::ACTION_WISHLIST_SENT);
  }

  public static function wishlistCreated($wishlist) {
    $url = \Context::getContext()->link->getModuleLink('blockwishlist', 'view', ['token' => $wishlist->token]);
    self::triggerAction(self::ACTION_WISHLIST_CREATED, $url);
  }

  private static function getProductLink($productId) {
    if ((int)$productId) {
      return \Context::getContext()->link->getProductLink($productId);
    }
    return null;
  }

  private static function triggerAction($action, $url=null) {
    $customer = \Context::getContext()->customer;
    if ($customer->isLogged() && array_key_exists($action, self::getActions())) {
      $data =[
        'module_name' => 'blockwishlist',
        'action_name' => $action,
        'id_customer' => (int)$customer->id
      ];
      if (! is_null($url)) {
        $data['action_url'] = $url;
      }
      \Hook::exec('actionExecuteKronaAction', $data);
    }
  }

}
