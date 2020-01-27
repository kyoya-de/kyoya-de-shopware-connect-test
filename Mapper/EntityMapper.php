<?php

namespace MakairaConnect\Mapper;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Shopware\Bundle\MediaBundle\MediaServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Service\PriceCalculatorInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\Tax;
use Shopware\Components\Routing\RouterInterface;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
use Shopware\Models\Article\Image;
use Shopware\Models\Article\Supplier;
use Shopware\Models\Category\Category;
use Shopware\Models\Shop\Shop;

class EntityMapper
{
    /**
     * @var array
     */
    private static $childrenCache = [];

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var MediaServiceInterface
     */
    private $mediaService;

    private $priceCalculator;

    private $shopContext;

    /**
     * @var array
     */
    private $allShops;

    /**
     * @var string
     */
    private $now;

    /**
     * EntityMapper constructor.
     *
     * @param RouterInterface          $router
     * @param EntityManagerInterface   $entityManager
     * @param MediaServiceInterface    $mediaService
     * @param PriceCalculatorInterface $priceCalculator
     * @param ContextServiceInterface  $contextService
     *
     * @throws Exception
     */
    public function __construct(
        RouterInterface $router,
        EntityManagerInterface $entityManager,
        MediaServiceInterface $mediaService,
        PriceCalculatorInterface $priceCalculator,
        ContextServiceInterface $contextService
    ) {
        $this->router          = $router;
        $this->em              = $entityManager;
        $this->mediaService    = $mediaService;
        $this->priceCalculator = $priceCalculator;
        $this->shopContext     = $contextService->getShopContext();

        $repo           = $entityManager->getRepository(Shop::class);
        $this->allShops = array_map(
            static function (Shop $shop) {
                return $shop->getId();
            },
            $repo->findAll()
        );

        $this->now = (new DateTime())->format('Y-m-d H:i:s');
    }

    /**
     * @param Category $category
     *
     * @return array
     */
    public function mapCategory(Category $category): array
    {
        if (!isset(self::$childrenCache[$category->getPath()])) {
            $repo  = $this->em->getRepository(Category::class);
            $qb    = $repo->createQueryBuilder('c');
            $query = $qb->select('c.id')->where($qb->expr()->like('c.path', ':path'))->setParameter(
                'path',
                str_replace('||', '|', "%|{$category->getId()}{$category->getPath()}|")
            )->getQuery();

            self::$childrenCache[$category->getPath()] = array_map(
                static function ($id) {
                    return (int) $id;
                },
                array_column($query->getScalarResult(), 'id')
            );
        }

        $trimmedPath = trim($category->getPath(), '|');
        $reversePath = array_reverse(explode('|', $trimmedPath));
        $path        = implode('|', $reversePath);

        $shops = $this->allShops;
        if ($category->getShops()) {
            $shops = array_map(
                static function ($id) {
                    return (int) $id;
                },
                explode('|', trim($category->getShops(), '|'))
            );
        }

        $url = (string) $this->router->assemble(
            [
                'sViewport' => 'cat',
                'sCategory' => $category->getId(),
            ]
        );

        return [
            'id'               => $category->getId(),
            'active'           => (bool) $category->getActive(),
            'hidden'           => false,
            'sort'             => (int) $category->getPosition(),
            'category_title'   => (string) $category->getName(),
            'shortdesc'        => (string) $category->getCmsHeadline(),
            'longdesc'         => (string) $category->getCmsText(),
            'meta_keywords'    => (string) $category->getMetaKeywords(),
            'meta_description' => (string) $category->getMetaDescription(),
            'hierarchy'        => str_replace('|', '//', $path),
            'depth'            => substr_count($path, '|') + 1,
            'subcategories'    => self::$childrenCache[$category->getPath()],
            'shop'             => $shops,
            'timestamp'        => $this->now,
            'url'              => $url,
            'additionalData'   => '',
        ];
    }

    /**
     * @param Supplier $supplier
     *
     * @return array
     */
    public function mapManufacturer(Supplier $supplier): array
    {
        return [
            'id'                 => $supplier->getId(),
            'manufacturer_title' => $supplier->getName(),
            'shortdesc'          => $supplier->getDescription(),
            'meta_keywords'      => $supplier->getMetaKeywords(),
            'meta_description'   => $supplier->getMetaDescription(),
            'timestamp'          => $this->now,
            'url'                => $this->router->assemble(
                [
                    'sViewport' => 'listing',
                    'sAction'   => 'manufacturer',
                    'sSupplier' => $supplier->getId(),
                ]
            ),
            'active'             => true,
            'shop'               => $this->allShops,
            'additionalData'     => '',
        ];
    }

    /**
     * @param Article $article
     *
     * @return array
     */
    public function mapProduct(Article $article): array
    {
        $router = $this->router;
        $url    = (string) $router->assemble(
            [
                'sViewport' => 'detail',
                'sArticle'  => $article->getId(),
            ]
        );

        $mainDetail = $article->getMainDetail();

        $imageUrl = '';

        $images = $article->getImages();
        if (0 < $images->count()) {
            /** @var Image $image */
            $image = $images->first();

            $imageUrl = $this->mediaService->getUrl("media/image/{$image->getPath()}.{$image->getExtension()}");
        }

        $categories   = $article->getCategories();
        $mainCategory = $categories->first();

        $mainCategoryUrl = (string) $router->assemble(
            [
                'sViewport' => 'cat',
                'sCategory' => $mainCategory->getId(),
            ]
        );

        $categories = array_map(
            function (Category $category) {
                return [
                    'catid'  => $category->getId(),
                    'title'  => $category->getName(),
                    'path'   => $this->getPath(
                        $this->router->assemble(['sViewport' => 'cat', 'sCategory' => $category->getId()])
                    ),
                    'shopid' => $this->getShopIds($category->getShops()),
                ];
            },
            $article->getCategories()->toArray()
        );

        $sum = array_reduce(
            $article->getDetails()->toArray(),
            static function ($carry, Detail $item) {
                return $carry + $item->getInStock();
            },
            0.0
        );

        $price = 0.0;
        if ($mainDetail) {
            $taxRule = $this->shopContext->getTaxRule($article->getTax()->getId()) ?? new Tax();
            $price   = $this->priceCalculator->calculatePrice(
                $mainDetail->getPrices()->first()->getPrice(),
                $taxRule,
                $this->shopContext
            );
        }

        return [
            'id'                           => $article->getId(),
            'parent'                       => '',
            'shop'                         => $this->allShops,
            'ean'                          => $mainDetail ? $mainDetail->getNumber() : '',
            'activeto'                     => $article->getAvailableTo() ?
                $article->getAvailableTo()->format('Y-m-d H:i:s') :
                '',
            'activefrom'                   => $article->getAvailableFrom() ?
                $article->getAvailableFrom()->format('Y-m-d H:i:s') :
                '',
            'isVariant'                    => false,
            'active'                       => $article->getActive(),
            'sort'                         => $mainDetail ? $mainDetail->getPosition() : 0,
            'stock'                        => $sum,
            'onstock'                      => $sum > 0,
            'picture_url_main'             => $imageUrl,
            'title'                        => $article->getName(),
            'shortdesc'                    => $article->getDescription(),
            'longdesc'                     => $article->getDescriptionLong(),
            'price'                        => $price,
            'soldamount'                   => 0,
            'searchable'                   => true,
            'searchkeys'                   => '',
            'meta_keywords'                => $article->getKeywords(),
            'meta_description'             => $article->getDescription(),
            'manufacturerid'               => $article->getSupplier()->getId(),
            'manufacturer_title'           => $article->getSupplier()->getName(),
            'url'                          => $url,
            'maincategory'                 => $mainCategory->getId(),
            'maincategoryurl'              => $mainCategoryUrl,
            'category'                     => $categories,
            'attributes'                   => [],
            'attributeStr'                 => [],
            'attributeInt'                 => [],
            'attributeFloat'               => [],
            'mak_boost_norm_insert'        => 0.0,
            'mak_boost_norm_sold'          => 0.0,
            'mak_boost_norm_rating'        => 0.0,
            'mak_boost_norm_revenue'       => 0.0,
            'mak_boost_norm_profit_margin' => 0.0,
            'timestamp'                    => $this->now,
            'additionalData'               => [
                'ean2' => $mainDetail ? $mainDetail->getEan() : '',
            ],
        ];
    }

    /**
     * @param Detail $detail
     *
     * @return array
     */
    public function mapVariant(Detail $detail): array
    {
        $article = $detail->getArticle();

        $url = (string) $this->router->assemble(
            [
                'sViewport' => 'detail',
                'sArticle'  => $article->getId(),
            ]
        );

        $imageUrl = '';

        $images = $article->getImages();
        if (0 < $images->count()) {
            /** @var Image $image */
            $image = $images->first();

            $imageUrl = $this->mediaService->getUrl("media/image/{$image->getPath()}.{$image->getExtension()}");
        }

        $categories   = $article->getCategories();
        $mainCategory = $categories->first();

        $mainCategoryUrl = (string) $this->router->assemble(
            [
                'sViewport' => 'cat',
                'sCategory' => $mainCategory->getId(),
            ]
        );

        $taxRule = $this->shopContext->getTaxRule($article->getTax()->getId()) ?? new Tax();
        $price   = $this->priceCalculator->calculatePrice(
            $detail->getPrices()->first()->getPrice(),
            $taxRule,
            $this->shopContext
        );

        $categories = array_map(
            function (Category $category) {
                return [
                    'catid'  => $category->getId(),
                    'title'  => $category->getName(),
                    'path'   => $this->getPath(
                        $this->router->assemble(['sViewport' => 'cat', 'sCategory' => $category->getId()])
                    ),
                    'shopid' => $this->getShopIds($category->getShops()),
                ];
            },
            $article->getCategories()->toArray()
        );

        return [
            'id'                           => $detail->getId(),
            'parent'                       => $article->getId(),
            'shop'                         => $this->allShops,
            'ean'                          => $detail->getNumber(),
            'activeto'                     => $article->getAvailableTo() ?
                $article->getAvailableTo()->format('Y-m-d H:i:s') :
                '',
            'activefrom'                   => $article->getAvailableFrom() ?
                $article->getAvailableFrom()->format('Y-m-d H:i:s') :
                '',
            'isVariant'                    => true,
            'active'                       => $article->getActive(),
            'sort'                         => $detail->getPosition(),
            'stock'                        => $detail->getInStock(),
            'onstock'                      => $detail->getInStock() > 0,
            'picture_url_main'             => $imageUrl,
            'title'                        => $article->getName(),
            'shortdesc'                    => $article->getDescription(),
            'longdesc'                     => $article->getDescriptionLong(),
            'price'                        => $price,
            'soldamount'                   => 0,
            'searchable'                   => true,
            'searchkeys'                   => '',
            'meta_keywords'                => $article->getKeywords(),
            'meta_description'             => $article->getDescription(),
            'manufacturerid'               => $article->getSupplier()->getId(),
            'manufacturer_title'           => $article->getSupplier()->getName(),
            'url'                          => $url,
            'maincategory'                 => $mainCategory->getId(),
            'maincategoryurl'              => $mainCategoryUrl,
            'category'                     => $categories,
            'attributes'                   => [],
            'attributeStr'                 => [],
            'attributeInt'                 => [],
            'attributeFloat'               => [],
            'mak_boost_norm_insert'        => 0.0,
            'mak_boost_norm_sold'          => 0.0,
            'mak_boost_norm_rating'        => 0.0,
            'mak_boost_norm_revenue'       => 0.0,
            'mak_boost_norm_profit_margin' => 0.0,
            'timestamp'                    => $this->now,
            'additionalData'               => [
                'ean2' => $detail->getEan(),
            ],
        ];
    }

    /**
     * @param string|null $shops
     *
     * @return array
     */
    private function getShopIds(?string $shops): array
    {
        if (null === $shops) {
            return $this->allShops;
        }

        return array_map(
            static function ($shop) {
                return (int) $shop;
            },
            explode('|', trim($shops, '|'))
        );
    }

    /**
     * @param string $url
     *
     * @return string
     */
    private function getPath(string $url): string
    {
        return ltrim(
            parse_url($url, PHP_URL_PATH),
            '/'
        );
    }
}
