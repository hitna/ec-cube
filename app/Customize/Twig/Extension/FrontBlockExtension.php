<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Customize\Twig\Extension;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Category;
use Eccube\Entity\Master\ProductStatus;
use Eccube\Entity\Product;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * トップページのブロックに実データを渡す.
 *
 * 本体の Block/new_item.twig と Block/category.twig は商品名も画像も
 * テンプレートに直接書かれているため、登録済みの商品と連動しない。
 * app/template/default/Block 側で上書きしたテンプレートから、ここの関数を使う。
 */
class FrontBlockExtension extends AbstractExtension
{
    /** @var EntityManagerInterface */
    protected $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('new_products', [$this, 'getNewProducts']),
            new TwigFunction('top_categories', [$this, 'getTopCategories']),
            new TwigFunction('deal_products', [$this, 'getDealProducts']),
            new TwigFunction('category_tiles', [$this, 'getCategoryTiles']),
        ];
    }

    /**
     * 公開中の商品を新しい順に返す.
     *
     * @return Product[]
     */
    public function getNewProducts(int $limit = 6): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('p')->addSelect('pi')
            ->from(Product::class, 'p')
            ->leftJoin('p.ProductImage', 'pi')
            ->where('p.Status = :status')
            ->setParameter('status', ProductStatus::DISPLAY_SHOW)
            ->orderBy('p.update_date', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * 割引率が設定されている商品を、割引率の大きい順に返す.
     *
     * @return Product[]
     */
    public function getDealProducts(int $limit = 12): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->where('p.Status = :status')
            ->andWhere('p.discount_rate > 0')
            ->setParameter('status', ProductStatus::DISPLAY_SHOW)
            ->orderBy('p.discount_rate', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * カテゴリごとに商品画像を最大4枚まとめたタイル用のデータを返す.
     *
     * @return array<int, array{category: Category, images: string[], count: int}>
     */
    public function getCategoryTiles(int $limit = 4): array
    {
        $tiles = [];
        foreach ($this->getTopCategories() as $row) {
            /** @var Category $Category */
            $Category = $row['category'];
            $ids = $this->descendantIds($Category);

            // 複数カテゴリに属する商品は join で行が重複するため distinct が要る
            /** @var Product[] $products */
            $products = $this->entityManager->createQueryBuilder()
                ->select('p')
                ->distinct(true)
                ->from(Product::class, 'p')
                ->innerJoin('p.ProductCategories', 'pct')
                ->where('pct.category_id IN (:ids)')
                ->andWhere('p.Status = :status')
                ->setParameter('ids', $ids)
                ->setParameter('status', ProductStatus::DISPLAY_SHOW)
                ->orderBy('p.id', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();

            $images = [];
            foreach ($products as $Product) {
                $Image = $Product->getProductImage()->first();
                if ($Image) {
                    $images[] = $Image->getFileName();
                }
            }
            if (!$images) {
                continue;
            }

            $tiles[] = ['category' => $Category, 'images' => $images, 'count' => $row['count']];
        }

        return $tiles;
    }

    /**
     * 第1階層のカテゴリを、商品件数と代表画像つきで返す.
     *
     * @return array<int, array{category: Category, count: int, image: string|null}>
     */
    public function getTopCategories(): array
    {
        /** @var Category[] $categories */
        $categories = $this->entityManager->getRepository(Category::class)
            ->createQueryBuilder('c')
            ->where('c.hierarchy = 1')
            ->orderBy('c.sort_no', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($categories as $Category) {
            $ids = $this->descendantIds($Category);

            $count = (int) $this->entityManager->createQueryBuilder()
                ->select('COUNT(DISTINCT p.id)')
                ->from(Product::class, 'p')
                ->innerJoin('p.ProductCategories', 'pct')
                ->where('pct.category_id IN (:ids)')
                ->andWhere('p.Status = :status')
                ->setParameter('ids', $ids)
                ->setParameter('status', ProductStatus::DISPLAY_SHOW)
                ->getQuery()
                ->getSingleScalarResult();

            if ($count === 0) {
                continue;
            }

            $result[] = [
                'category' => $Category,
                'count' => $count,
                'image' => $this->representativeImage($ids),
            ];
        }

        return $result;
    }

    /**
     * 自身と子孫カテゴリのIDを返す.
     *
     * @return int[]
     */
    private function descendantIds(Category $Category): array
    {
        $ids = [$Category->getId()];
        foreach ($Category->getChildren() as $Child) {
            $ids = array_merge($ids, $this->descendantIds($Child));
        }

        return $ids;
    }

    /**
     * カテゴリの代表画像として、所属商品のうち最も新しいものの画像を返す.
     *
     * @param int[] $categoryIds
     */
    private function representativeImage(array $categoryIds): ?string
    {
        /** @var Product[] $products */
        $products = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->distinct(true)
            ->from(Product::class, 'p')
            ->innerJoin('p.ProductCategories', 'pct')
            ->where('pct.category_id IN (:ids)')
            ->andWhere('p.Status = :status')
            ->setParameter('ids', $categoryIds)
            ->setParameter('status', ProductStatus::DISPLAY_SHOW)
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        if (!$products) {
            return null;
        }
        $Image = $products[0]->getProductImage()->first();

        return $Image ? $Image->getFileName() : null;
    }
}
