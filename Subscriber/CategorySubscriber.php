<?php

namespace MakairaConnect\Subscriber;

use Doctrine\ORM\EntityRepository;
use Enlight\Event\SubscriberInterface;
use Enlight_Controller_ActionEventArgs;
use Exception;
use Shopware\Models\Category\Category;

class CategorySubscriber implements SubscriberInterface
{
    private $categoryRepository;
    private $makairaRevisionRepository;

    public function __construct(
        EntityRepository $categoryRepository,
        EntityRepository $makairaRevisionRepository
    )
    {
        $this->categoryRepository = $categoryRepository;
        $this->makairaRevisionRepository = $makairaRevisionRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'Enlight_Controller_Action_PreDispatch_Backend_Category' => 'onCategoryPostDispatch'
        ];
    }

    public function onCategoryPostDispatch(Enlight_Controller_ActionEventArgs $arguments)
    {
        $request = $arguments->getRequest();

        try {
            if ($request->getActionName() === 'updateDetail') {
                $categoryId = $request->get('id');
                /**@var Category $category */
                $category = $this->categoryRepository->find($categoryId);
                if ($category->getSortingIds() !== $request->get('sortingIds')) {
                    $products = $category->getArticles()->toArray();
                    if (!empty($products)) {
                        $this->makairaRevisionRepository->addRevisions('product', $products);
                    }
                }
            }
        } catch (Exception $exception) {
        }
    }
}
