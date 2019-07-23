<?php

namespace MakairaConnect\Classes\Subscriber;

class EventSubscriber extends Subscriber {
  /**
   * for all events like 'Shopware_Modules_Basket_AddArticle_Start'
   * @return array
   */
  public static function getSubscribedEvents() {
    return [

    ];
  }
}