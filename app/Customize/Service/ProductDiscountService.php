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

namespace Customize\Service;

use Eccube\Entity\Product;
use Eccube\Entity\ProductClass;
use Eccube\Service\TaxRuleService;

/**
 * 商品ごとの割引率から割引価格を算出する.
 *
 * 割引は税抜の販売価格(price02)に対して適用し, 端数は四捨五入する.
 * 税込価格は割引後の税抜価格から改めて計算するため, カート・受注の金額と表示がずれない.
 */
class ProductDiscountService
{
    /**
     * @var TaxRuleService
     */
    protected $taxRuleService;

    public function __construct(TaxRuleService $taxRuleService)
    {
        $this->taxRuleService = $taxRuleService;
    }

    /**
     * 有効な割引率(1〜100)を返す. 割引なしの場合は null.
     *
     * @param Product|null $Product
     *
     * @return int|null
     */
    public function getDiscountRate($Product)
    {
        if (!$Product instanceof Product || !method_exists($Product, 'getDiscountRate')) {
            return null;
        }

        $rate = $Product->getDiscountRate();
        if (null === $rate) {
            return null;
        }

        $rate = (int) $rate;

        return ($rate > 0 && $rate <= 100) ? $rate : null;
    }

    /**
     * @param Product|null $Product
     *
     * @return bool
     */
    public function isDiscounted($Product)
    {
        return null !== $this->getDiscountRate($Product);
    }

    /**
     * 割引後の販売価格(税抜).
     *
     * @return string|null
     */
    public function getDiscountedPrice02(ProductClass $ProductClass)
    {
        $price = $ProductClass->getPrice02();
        $rate = $this->getDiscountRate($ProductClass->getProduct());

        if (null === $rate || null === $price) {
            return $price;
        }

        return (string) (int) round(((float) $price) * (100 - $rate) / 100);
    }

    /**
     * 割引後の販売価格(税込).
     *
     * @return string|null
     */
    public function getDiscountedPrice02IncTax(ProductClass $ProductClass)
    {
        $rate = $this->getDiscountRate($ProductClass->getProduct());
        if (null === $rate) {
            return $ProductClass->getPrice02IncTax();
        }

        return $this->taxRuleService->getPriceIncTax(
            $this->getDiscountedPrice02($ProductClass),
            $ProductClass->getProduct(),
            $ProductClass
        );
    }

    /**
     * 割引後の販売価格(税込)の最小値.
     *
     * @return string|null
     */
    public function getDiscountedPrice02IncTaxMin(Product $Product)
    {
        $prices = $this->getDiscountedPrice02IncTaxes($Product);

        return $prices ? min($prices) : null;
    }

    /**
     * 割引後の販売価格(税込)の最大値.
     *
     * @return string|null
     */
    public function getDiscountedPrice02IncTaxMax(Product $Product)
    {
        $prices = $this->getDiscountedPrice02IncTaxes($Product);

        return $prices ? max($prices) : null;
    }

    /**
     * 規格ID => 割引後の販売価格(税込) のマップ.
     * 商品詳細ページで規格を切り替えたときの表示更新に使用する.
     *
     * @return array
     */
    public function getDiscountedPriceMap(Product $Product)
    {
        $map = [];
        foreach ($this->getVisibleProductClasses($Product) as $ProductClass) {
            $map[$ProductClass->getId()] = $this->getDiscountedPrice02IncTax($ProductClass);
        }

        return $map;
    }

    /**
     * @return string[]
     */
    private function getDiscountedPrice02IncTaxes(Product $Product)
    {
        $prices = [];
        foreach ($this->getVisibleProductClasses($Product) as $ProductClass) {
            $prices[] = $this->getDiscountedPrice02IncTax($ProductClass);
        }

        return $prices;
    }

    /**
     * 表示対象の規格のみを返す. Product::_calc() の絞り込みと揃えている.
     *
     * @return ProductClass[]
     */
    private function getVisibleProductClasses(Product $Product)
    {
        $ProductClasses = [];
        foreach ($Product->getProductClasses() as $ProductClass) {
            if (!$ProductClass->isVisible()) {
                continue;
            }
            $ClassCategory1 = $ProductClass->getClassCategory1();
            if ($ClassCategory1 && !$ClassCategory1->isVisible()) {
                continue;
            }
            $ClassCategory2 = $ProductClass->getClassCategory2();
            if ($ClassCategory2 && !$ClassCategory2->isVisible()) {
                continue;
            }
            $ProductClasses[] = $ProductClass;
        }

        return $ProductClasses;
    }
}
