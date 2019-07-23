<?php

namespace MakairaConnect\Classes\Subscriber;

class ModelSubscriber extends Subscriber {
  /**
   * for all events like 'Shopware\Models\Article\Detail::postPersist'
   * @return array
   */
  public static function getSubscribedEvents() {
    return [
      'Shopware\Models\Category\Category::postPersist' => 'onCategorySave',
      'Shopware\Models\Category\Category::postUpdate' => 'onCategorySave',
      'Shopware\Models\Category\Category::preRemove' => 'onCategorySave',
      'Shopware\Models\Article\Supplier::postPersist' => 'onManufacturerSave',
      'Shopware\Models\Article\Supplier::postUpdate' => 'onManufacturerSave',
      'Shopware\Models\Article\Supplier::preRemove' => 'onManufacturerSave',
      'Shopware\Models\Article\Detail::postPersist' => 'onVariantSave',
      'Shopware\Models\Article\Detail::postUpdate' => 'onVariantSave',
      'Shopware\Models\Article\Detail::preRemove' => 'onVariantSave',
    ];
  }
}