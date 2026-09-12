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

use Customize\Service\ProductDiscountService;
use Eccube\Entity\Product;
use Eccube\Twig\Extension\EccubeExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * フロント表示用に割引価格を提供する.
 */
class ProductDiscountExtension extends AbstractExtension
{
    /**
     * @var ProductDiscountService
     */
    protected $productDiscountService;

    /**
     * @var EccubeExtension
     */
    protected $eccubeExtension;

    public function __construct(ProductDiscountService $productDiscountService, EccubeExtension $eccubeExtension)
    {
        $this->productDiscountService = $productDiscountService;
        $this->eccubeExtension = $eccubeExtension;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('product_discount_rate', [$this, 'getDiscountRate']),
            new TwigFunction('product_discount_price_min', [$this, 'getDiscountedPriceMin']),
            new TwigFunction('product_discount_price_max', [$this, 'getDiscountedPriceMax']),
            new TwigFunction('product_discount_prices_as_json', [$this, 'getDiscountedPricesAsJson']),
        ];
    }

    /**
     * @return int|null
     */
    public function getDiscountRate($Product)
    {
        return $this->productDiscountService->getDiscountRate($Product);
    }

    /**
     * @return string|null
     */
    public function getDiscountedPriceMin(Product $Product)
    {
        return $this->productDiscountService->getDiscountedPrice02IncTaxMin($Product);
    }

    /**
     * @return string|null
     */
    public function getDiscountedPriceMax(Product $Product)
    {
        return $this->productDiscountService->getDiscountedPrice02IncTaxMax($Product);
    }

    /**
     * 規格ID => 通貨書式に整形した割引後の販売価格(税込) のマップを JSON で返す.
     * 商品詳細ページで規格を切り替えたときの表示更新に使用する.
     *
     * @return string
     */
    public function getDiscountedPricesAsJson(Product $Product)
    {
        $map = [];
        foreach ($this->productDiscountService->getDiscountedPriceMap($Product) as $id => $price) {
            $map[$id] = $this->eccubeExtension->getPriceFilter($price);
        }

        return json_encode($map);
    }
}
