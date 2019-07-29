<?php

namespace MakairaConnect\Subscriber;

use Enlight\Event\SubscriberInterface;
use MakairaConnect\Repositories\MakRevisionRepository;

class ShopwareSubscriber implements SubscriberInterface {
  /** @var MakRevisionRepository */
  private $makRevisionRepo;

  public function __construct() {
      $this->makRevisionRepo = Shopware()->Models()->getRepository(MakRevisionModel::class);
  }

  /**
   * for all events like 'Shopware_Modules_Basket_AddArticle_Start'
   * @return array
   */
  public static function getSubscribedEvents() {
    return [];
  }
}