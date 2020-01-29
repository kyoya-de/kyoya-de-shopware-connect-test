<?php

namespace MakairaConnect\Mapper;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Shopware\Bundle\MediaBundle\MediaServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Service\PriceCalculatorInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\Category as CategoryStruct;
use Shopware\Bundle\StoreFrontBundle\Struct\Configurator\Set;
use Shopware\Bundle\StoreFrontBundle\Struct\Product;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContext;
use Shopware\Components\Routing\RouterInterface;
use Shopware\Models\Category\Category;
use Shopware\Models\Shop\Shop;
use function array_map;
use function array_merge;
use function array_pop;
use function array_values;
use function count;
use function str_replace;

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
     * @param CategoryStruct $category
     *
     * @return array
     */
    public function mapCategory(CategoryStruct $category, ShopContext $context): array
    {
        $path = implode('|', $category->getPath());
        if (!isset(self::$childrenCache[$path])) {
            $repo  = $this->em->getRepository(Category::class);
            $qb    = $repo->createQueryBuilder('c');
            $query =
                $qb->select('c.id')->where($qb->expr()->like('c.path', ':path'))->setParameter(
                        'path',
                        str_replace('||', '|', "%|{$category->getId()}|{$path}|")
                    )->getQuery();

            self::$childrenCache[$path] = array_map(
                static function ($id) {
                    return (int) $id;
                },
                array_column($query->getScalarResult(), 'id')
            );
        }

        $trimmedPath = trim($path, '|');
        $reversePath = array_reverse(explode('|', $trimmedPath));
        $path        = implode('|', $reversePath);

        $url = (string) $this->router->assemble(
            [
                'sViewport' => 'cat',
                'sCategory' => $category->getId(),
            ]
        );

        return [
            'id'               => $category->getId(),
            'active'           => true,
            'hidden'           => false,
            'sort'             => (int) $category->getPosition(),
            'category_title'   => (string) $category->getName(),
            'shortdesc'        => (string) $category->getCmsHeadline(),
            'longdesc'         => (string) $category->getCmsText(),
            'meta_keywords'    => (string) $category->getMetaKeywords(),
            'meta_description' => (string) $category->getMetaDescription(),
            'hierarchy'        => str_replace('|', '//', $path),
            'depth'            => substr_count($path, '|') + 1,
            'subcategories'    => self::$childrenCache[$path],
            'shop'             => [$context->getShop()->getId()],
            'timestamp'        => $this->now,
            'url'              => $url,
            'additionalData'   => '',
        ];
    }

    /**
     * @param Product\Manufacturer $supplier
     * @param ShopContext          $context
     *
     * @return array
     */
    public function mapManufacturer(Product\Manufacturer $supplier, ShopContext $context): array
    {
        return [
            'id'                 => $supplier->getId(),
            'manufacturer_title' => $supplier->getName(),
            'shortdesc'          => $supplier->getDescription(),
            'meta_keywords'      => $supplier->getMetaKeywords(),
            'meta_description'   => $supplier->getMetaDescription(),
            'timestamp'          => $this->now,
            'url'                => $supplier->getLink(),
            'active'             => true,
            'shop'               => [$context->getShop()->getId()],
            'additionalData'     => [
                'metaTitle'       => $supplier->getMetaTitle(),
            ],
        ];
    }

    /**
     * @param Product     $article
     * @param ShopContext $context
     * @param Set         $configurator
     *
     * @return array
     */
    public function mapProduct(Product $article, ShopContext $context, Set $configurator): array
    {
        $mapped = $this->mapCommonProductData($article, $context, false);

        if (null !== ($properties = $article->getPropertySet())) {
            foreach ($properties->getGroups() as $group) {
                foreach ($group->getOptions() as $option) {
                    $mapped['attribute'][$group->getId()][] = $option->getName();
                    $mapped['attributeStr'][]               = [
                        'id'    => $group->getId(),
                        'title' => $group->getName() . ' (property)',
                        'value' => $option->getName(),

                    ];
                }
            }
        }

        foreach ($configurator->getGroups() as $group) {
            foreach ($group->getOptions() as $option) {
                $mapped['attribute'][$group->getId()][] = $option->getName();
                $mapped['attributeStr'][]               = [
                    'id'    => $group->getId(),
                    'title' => $group->getName() . ' (variant)',
                    'value' => $option->getName(),
                ];
            }
        }

        return $mapped;
    }

    /**
     * @param Product     $detail
     * @param ShopContext $context
     * @param bool        $asVariant
     *
     * @return array
     */
    protected function mapCommonProductData(Product $detail, ShopContext $context, $asVariant): array
    {
        $router = $this->router;
        $url    = (string) $router->assemble(
            [
                'sViewport' => 'detail',
                'sArticle'  => $detail->getId(),
            ]
        );

        $imageUrl = '';
        $images   = $detail->getMedia();
        if (0 < count($images)) {
            foreach ($images as $image) {
                if ('IMAGE' === $image->getType()) {
                    $imageUrl = $image->getFile();
                    break;
                }
            }
        }

        $categories    = $detail->getCategories();
        $allCategories = array_map(
            function (CategoryStruct $category) use ($context) {
                return [
                    'catid'  => $category->getId(),
                    'title'  => $category->getName(),
                    'path'   => $this->getPath(
                        $this->router->assemble(['sViewport' => 'cat', 'sCategory' => $category->getId()])
                    ),
                    // TODO Replace hardcoded Shop-ID.
                    'shopid' => [$context->getShop()->getId()],
                ];
            },
            $categories
        );
        $mainCategory  = array_pop($categories);

        $mainCategoryUrl = (string) $router->assemble(
            [
                'sViewport' => 'cat',
                'sCategory' => $mainCategory->getId(),
            ]
        );

        $price = $detail->getVariantPrice()->getCalculatedPrice();

        $rawData = [
            'id'                           => $detail->getVariantId(),
            'parent'                       => $detail->getId(),
            'shop'                         => [$context->getShop()->getId()],
            'ean'                          => $detail->getNumber(),
            'activeto'                     => '',
            'activefrom'                   => '',
            'isVariant'                    => $asVariant,
            'active'                       => $detail->isAvailable(),
            'sort'                         => 0,
            'stock'                        => $detail->getStock(),
            'onstock'                      => $detail->getStock() > 0,
            'picture_url_main'             => $imageUrl,
            'title'                        => $detail->getName(),
            'shortdesc'                    => $detail->getShortDescription(),
            'longdesc'                     => $detail->getLongDescription(),
            'price'                        => $price,
            'soldamount'                   => 0,
            'searchable'                   => true,
            'searchkeys'                   => '',
            'meta_keywords'                => $detail->getKeywords(),
            'meta_description'             => $detail->getShortDescription(),
            'manufacturerid'               => $detail->getManufacturer()->getId(),
            'manufacturer_title'           => $detail->getManufacturer()->getName(),
            'url'                          => $url,
            'maincategory'                 => $mainCategory->getId(),
            'maincategoryurl'              => $mainCategoryUrl,
            'category'                     => $allCategories,
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

        if (!$asVariant) {
            $rawData['attribute'] = [];
        }

        return $rawData;
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

    /**
     * @param Product     $detail
     * @param ShopContext $context
     * @param Set[]       $propertySets
     *
     * @return array
     */
    public function mapVariant(Product $detail, ShopContext $context, array $propertySets): array
    {
        $mapped = $this->mapCommonProductData($detail, $context, true);

        foreach ($propertySets as $propertySet) {
            foreach ($propertySet->getGroups() as $group) {
                foreach ($group->getOptions() as $option) {
                    $mapped['attributeStr'][] = [
                        'id'    => $group->getId(),
                        'title' => $group->getName() . ' (property)',
                        'value' => $option->getName(),
                    ];
                }
            }
        }

        foreach ($detail->getConfiguration() as $group) {
            foreach ($group->getOptions() as $option) {
                $mapped['attributeStr'][] = [
                    'id'    => $group->getId(),
                    'title' => $group->getName() . ' (variant)',
                    'value' => $option->getName(),
                ];
            }
        }

        return $mapped;
    }
}
