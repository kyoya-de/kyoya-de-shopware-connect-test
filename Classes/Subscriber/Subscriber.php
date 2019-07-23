<?php

namespace MakairaConnect\Classes\Subscriber;

use Enlight\Event\SubscriberInterface;
use MakairaConnect\Classes\Models\MakRevision;
use Shopware\Models\Article\Detail;
use Shopware\Models\Article\Supplier;
use Shopware\Models\Category\Category;

use MakairaConnect\Classes\Models\MakRevision as MakRevisionModel;

class Subscriber implements SubscriberInterface {
    /** @var \MakairaConnect\Classes\Repositories\MakRevisionRepository */
    private $makRevisionRepo;

    /**
     * Subscriber constructor.
     */
    public function __construct() {
        $this->makRevisionRepo = Shopware()->Models()->getRepository(MakRevisionModel::class);
    }

    /**
     * do not fill this class, its meant to be extended
     * @return array
     */
    public static function getSubscribedEvents() {
      return [];
    }

    /**
     * @param \Enlight_Event_EventArgs $event
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function onCategorySave(\Enlight_Event_EventArgs $event) {
        /** @var Category $model */

        $model = $event->get('entity');
        $this->makRevisionRepo->addRevision('category', $model->getId());
    }

    /**
     * @param \Enlight_Event_EventArgs $event
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function onManufacturerSave(\Enlight_Event_EventArgs $event) {
        /** @var Supplier $model */

        $model = $event->get('entity');
        $this->makRevisionRepo->addRevision('manufacturer', $model->getId());
    }

    /**
     * @param \Enlight_Event_EventArgs $event
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function onVariantSave(\Enlight_Event_EventArgs $event) {
        /** @var Detail $model */

        $model = $event->get('entity');
        if (2 == $model->getKind()) {
            $this->makRevisionRepo->addRevision('variant', $model->getId());
        } else {
            $this->makRevisionRepo->addRevision('product', $model->getArticleId());
        }
    }
}
