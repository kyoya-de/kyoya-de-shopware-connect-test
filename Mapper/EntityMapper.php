<?php

namespace MakairaConnect\Mapper;

use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use MakairaConnect\Modifier\CategoryModifierInterface;
use MakairaConnect\Modifier\ManufacturerModifierInterface;
use MakairaConnect\Modifier\ProductModifierInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\Category as CategoryStruct;
use Shopware\Bundle\StoreFrontBundle\Struct\Property\Set as PropertySet;
use Shopware\Bundle\StoreFrontBundle\Struct\Product;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContext;
use Shopware\Components\Routing\RouterInterface;
use Shopware\Models\Category\Category;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Status;
use function array_map;
use function array_pop;
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
     * @var ProductModifierInterface[]
     */
    private $productModifiers = [];

    /**
     * @var ProductModifierInterface[]
     */
    private $variantModifiers = [];

    /**
     * @var CategoryModifierInterface[]
     */
    private $categoryModifiers = [];

    /**
     * @var ManufacturerModifierInterface[]
     */
    private $manufacturerModifiers = [];

    /**
     * @var string
     */
    private $now;

    /**
     * EntityMapper constructor.
     *
     * @param RouterInterface        $router
     * @param EntityManagerInterface $entityManager
     *
     * @throws Exception
     */
    public function __construct(
        RouterInterface $router,
        EntityManagerInterface $entityManager
    ) {
        $this->router = $router;
        $this->em     = $entityManager;
        $this->now    = (new DateTime())->format('Y-m-d H:i:s');
    }

    /**
     * @param CategoryStruct $category
     * @param ShopContext    $context
     *
     * @return array
     */
    public function mapCategory(CategoryStruct $category, ShopContext $context): array
    {
        $path = implode('|', $category->getPath());
        if (!isset(self::$childrenCache[$path])) {
            $repo = $this->em->getRepository(Category::class);
            $qb   = $repo->createQueryBuilder('c');

            // @formatter:off
            $query = $qb->select('c.id')
                ->where($qb->expr()->like('c.path', ':path'))
                ->setParameter('path', str_replace('||', '|', "%|{$category->getId()}|{$path}|"))
                ->getQuery();
            // @formatter:on

            self::$childrenCache[$path]   = array_map(
                static function ($id) {
                    return (int) $id;
                },
                array_column($query->getScalarResult(), 'id')
            );
            self::$childrenCache[$path][] = $category->getId();
        }

        $trimmedPath = trim($path, '|');
        $reversePath = array_reverse(explode('|', $trimmedPath));
        $path        = implode('|', $reversePath);

        $url = (string) $this->router->assemble(
            [
                'sViewport' => 'cat',
                'sCategory' => $category->getId(),
                'fullPath'  => '',
            ]
        );

        $mappedData = [
            'id'             => $category->getId(),
            'active'         => true,
            'hidden'         => false,
            'sort'           => (int) $category->getPosition(),
            'category_title' => (string) $category->getName(),
            'hierarchy'      => str_replace('|', '//', $path),
            'depth'          => substr_count($path, '|') + 1,
            'subcategories'  => self::$childrenCache[$path],
            'shop'           => [$context->getShop()->getId()],
            'timestamp'      => $this->now,
            'url'            => $url,
            'additionalData' => '',
        ];

        foreach ($this->categoryModifiers as $categoryModifier) {
            $categoryModifier->modifyCategory($mappedData, $category, $context);
        }

        return $mappedData;
    }

    /**
     * @param Product\Manufacturer $supplier
     * @param ShopContext          $context
     *
     * @return array
     */
    public function mapManufacturer(Product\Manufacturer $supplier, ShopContext $context): array
    {
        $url = $this->router->assemble(
            [
                'action'     => 'manufacturer',
                'controller' => 'listing',
                'sSupplier'  => $supplier->getId(),
                'fullPath'   => '',
            ]
        );

        $mappedData = [
            'id'                 => $supplier->getId(),
            'manufacturer_title' => $supplier->getName(),
            'timestamp'          => $this->now,
            'url'                => $url,
            'active'             => true,
            'shop'               => [$context->getShop()->getId()],
            'additionalData'     => [
                'metaTitle' => $supplier->getMetaTitle(),
            ],
        ];

        foreach ($this->manufacturerModifiers as $manufacturerModifier) {
            $manufacturerModifier->modifyManufacturer($mappedData, $supplier, $context);
        }

        return $mappedData;
    }

    /**
     * @param Product     $product
     * @param Product[]   $variants
     * @param ShopContext $context
     *
     * @return array
     */
    public function mapProduct(Product $product, array $variants, ShopContext $context): array
    {
        $mapped = $this->mapCommonProductData($product, $context, false);

        $productAttributes = [];
        $attributeStr = [];
        if (null !== ($properties = $product->getPropertySet())) {
            foreach ($properties->getGroups() as $group) {
                $id = $group->getId();
                $title = $group->getName();
                foreach ($group->getOptions() as $option) {
                    $value = $option->getName();

                    $productAttributes[$id][] = $value;

                    if (empty($attributeStr[$id])) {
                        $attributeStr[$id] = [
                            'id' => $id,
                            'title' => $title,
                            'value' => [$value],
                        ];
                    } else {
                        $attributeStr[$id]['value'][] = $value;
                    }
                }
            }
        }

        foreach ($variants as $variant) {
            $variantAttributes = $productAttributes;
            foreach ($variant->getConfiguration() as $group) {
                $id = $group->getId();
                $title = $group->getName();
                foreach ($group->getOptions() as $groupOption) {
                    $value = $groupOption->getName();
                    $variantAttributes[$id][] = $value;

                    if (empty($attributeStr[$id])) {
                        $attributeStr[$id] = [
                            'id' => $id,
                            'title' => $title,
                            'value' => [$value],
                        ];
                    } else {
                        if (!in_array($value, $attributeStr[$id]['value'])) {
                            $attributeStr[$id]['value'][] = $value;
                        }
                    }
                }
            }
            $mapped['attributes'][] = $variantAttributes;
        }
        $mapped['attributeStr'] = array_values($attributeStr);

        if (0 === count($mapped['attributeStr'])) {
            unset($mapped['attributeStr']);
        }

        foreach ($this->productModifiers as $productModifier) {
            $productModifier->modifyProduct($mapped, $product, $context);
        }

        return $mapped;
    }

    /**
     * @param Product $product
     * @param ShopContext $context
     * @param bool $asVariant
     *
     * @return array
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    protected function mapCommonProductData(Product $product, ShopContext $context, $asVariant): array
    {
        $router = $this->router;
        $url    = (string) $router->assemble(
            [
                'sViewport' => 'detail',
                'sArticle'  => $product->getId(),
                'fullPath'  => '',
            ]
        );

        $imageUrl = '';
        $images   = $product->getMedia();
        if (0 < count($images)) {
            foreach ($images as $image) {
                if ('IMAGE' === $image->getType()) {
                    $imageUrl = $image->getFile();
                    break;
                }
            }
        }

        $categories    = $product->getCategories();
        $categorySort  = [];
        $allCategories = array_map(
            function (CategoryStruct $category) use ($context, $product, &$categorySort) {
                // todo: check if there is a smarter way to get the position from s_categories_manual_sorting
                $categoryObject = $this->em->find(Category::class, $category->getId());
                $manualSorting  = $categoryObject->getManualSorting();
                // if no position is set, take a high value to guarantee that these will be displayed under the positioned ones
                $position = 999;
                foreach ($manualSorting as $sorting) {
                    if ($product->getId() === $sorting->getProduct()->getId()) {
                        $position = $sorting->getPosition();
                        break;
                    }
                }
                $categorySort["cat_{$category->getId()}"] = $position;

                return [
                    'catid'  => (string) $category->getId(),
                    'title'  => $category->getName(),
                    'path'   => $this->getPath(
                        $this->router->assemble(
                            [
                                'sViewport' => 'cat',
                                'sCategory' => $category->getId(),
                                'fullPath'  => '',
                            ]
                        )
                    ),
                    'shopid' => $context->getShop()->getId(),
                ];
            },
            $categories
        );
        $mainCategory  = array_pop($categories);

        $mainCategoryUrl = (string) $router->assemble(
            [
                'sViewport' => 'cat',
                'sCategory' => $mainCategory->getId(),
                'fullPath'  => '',
            ]
        );

        if ($asVariant) {
            $price = $product->getVariantPrice()->getCalculatedPrice();
        } else {
            $price = ($product->getCheapestPrice() ?? $product->getVariantPrice())->getCalculatedPrice();
        }

        $releaseDate = '0001-01-01T00:00:00';
        if (null !== ($releaseDateObject = $product->getReleaseDate())) {
            $releaseDate = $releaseDateObject->format(DateTimeInterface::ATOM);
        }

        $creationDate = '0001-01-01T00:00:00';
        if (null !== ($creationDateObject = $product->getCreatedAt())) {
            $creationDate = $creationDateObject->format(DateTimeInterface::ATOM);
        }

        $manufacturerTitle = '';
        $makManufacturer   = [];
        if (null !== ($manufacturer = $product->getManufacturer())) {
            $manufacturerTitle = $manufacturer->getName();
            $makManufacturer   = [
                'id'    => $manufacturer->getId(),
                'cover' => '',
                'url'   => $manufacturer->getLink(),
            ];

            if (null !== ($manufacturerMedia = $manufacturer->getCoverMedia())) {
                $makManufacturer['cover'] = $manufacturerMedia->getFile();
            }
        }

        $rawData = [
            'id'                           => $asVariant ? $product->getVariantId() : $product->getId(),
            'parent'                       => (string) ($asVariant ? $product->getId() : ''),
            'shop'                         => [$context->getShop()->getId()],
            'ean'                          => $product->getNumber(),
            'activeto'                     => '',
            'activefrom'                   => '',
            'isVariant'                    => $asVariant,
            'active'                       => $product->isAvailable(),
            'sort'                         => 0,
            'stock'                        => $product->getStock(),
            'onstock'                      => $product->getStock() > 0,
            'picture_url_main'             => $imageUrl,
            'title'                        => $product->getName(),
            'shortdesc'                    => $product->getShortDescription(),
            'longdesc'                     => $product->getLongDescription(),
            'price'                        => $price,
            'soldamount'                   => $this->getSoldAmount($product, $asVariant),
            'searchable'                   => true,
            'searchkeys'                   => '',
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
                'ean2'               => $product->getEan(),
                'releaseDate'        => (string) $releaseDate,
                'popularity'         => $product->getSales(),
                'catSort'            => $categorySort,
                'manufacturerid'     => (string) ($makManufacturer['id'] ?? ''),
                'manufacturer_title' => $manufacturerTitle,
                'sw_manufacturer'    => $makManufacturer,
                'creationDate'       => (string) $creationDate,
            ],
        ];

        if (!$asVariant) {
            $rawData['attributes']   = [];
            $rawData['attributeStr'] = [];
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
     * @param Product $variant
     * @param ShopContext $context
     * @param PropertySet[] $propertySets
     *
     * @return array
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function mapVariant(Product $variant, ShopContext $context, array $propertySets): array
    {
        $mapped = $this->mapCommonProductData($variant, $context, true);

        $attributeStr = [];
        foreach ($propertySets as $propertySet) {
            foreach ($propertySet->getGroups() as $group) {
                foreach ($group->getOptions() as $option) {
                    if (empty($attributeStr[$group->getId()])) {
                        $attributeStr[$group->getId()] = [
                            'id'    => $group->getId(),
                            'title' => $group->getName(),
                            'value' => [$option->getName()],
                        ];
                    } else {
                        $attributeStr[$group->getId()]['value'][] = $option->getName();
                    }
                }
            }
        }

        foreach ($variant->getConfiguration() as $group) {
            foreach ($group->getOptions() as $option) {
                if (empty($attributeStr[$group->getId()])) {
                    $attributeStr[$group->getId()] = [
                        'id'    => $group->getId(),
                        'title' => $group->getName(),
                        'value' => [$option->getName()],
                    ];
                } else {
                    $attributeStr[$group->getId()]['value'][] = $option->getName();
                }
            }
        }

        $mapped['attributeStr'] = array_values($attributeStr);
        if (0 === count($mapped['attributeStr'])) {
            unset($mapped['attributeStr']);
        }

        foreach ($this->variantModifiers as $variantModifier) {
            $variantModifier->modifyProduct($mapped, $variant, $context);
        }

        return $mapped;
    }

    /**
     * @param ProductModifierInterface $productModifier
     *
     * @return $this
     */
    public function addProductModifier(ProductModifierInterface $productModifier)
    {
        $this->productModifiers[] = $productModifier;

        return $this;
    }

    /**
     * @param ProductModifierInterface $variantModifier
     *
     * @return $this
     */
    public function addVariantModifier(ProductModifierInterface $variantModifier)
    {
        $this->variantModifiers[] = $variantModifier;

        return $this;
    }

    /**
     * @param CategoryModifierInterface $categoryModifier
     *
     * @return $this
     */
    public function addCategoryModifier(CategoryModifierInterface $categoryModifier)
    {
        $this->categoryModifiers[] = $categoryModifier;

        return $this;
    }

    /**
     * @param ManufacturerModifierInterface $manufacturerModifier
     *
     * @return $this
     */
    public function addManufacturerModifier(ManufacturerModifierInterface $manufacturerModifier)
    {
        $this->manufacturerModifiers[] = $manufacturerModifier;

        return $this;
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    private function getSoldAmount(Product $product, $isVariant): int
    {
        $orderDetailRepo = $this->em->getRepository(Detail::class);
        $builder = $orderDetailRepo->createQueryBuilder('od');
        $builder->select([
            'SUM(od.quantity) AS sold_amount',
        ])
            ->innerJoin('od.order', 'o')
            ->where('o.status NOT IN (:status)')
            ->setParameter('status', [Status::ORDER_STATE_CANCELLED_REJECTED, Status::ORDER_STATE_CANCELLED])
            ->andWhere('od.mode = 0');

        if ($isVariant) {
            $builder->andWhere('od.articleNumber = :articleNumber')
                ->setParameter('articleNumber', $product->getNumber());
        } else {
            $builder->andWhere('od.articleId = :articleId')
                ->setParameter('articleId', $product->getId());
        }

        return (int)$builder->getQuery()->getSingleScalarResult();
    }
}
