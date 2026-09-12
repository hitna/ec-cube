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

namespace Customize\Service\PurchaseFlow\Processor;

use Customize\Service\ProductDiscountService;
use Eccube\Entity\ItemInterface;
use Eccube\Entity\OrderItem;
use Eccube\Service\PurchaseFlow\ItemValidator;
use Eccube\Service\PurchaseFlow\PurchaseContext;

/**
 * 販売価格の変更検知(商品割引対応版).
 *
 * コアの Eccube\Service\PurchaseFlow\Processor\PriceChangeValidator を差し替えて使用する.
 * コア版は明細の単価を必ず ProductClass の販売価格へ戻してしまうため,
 * 割引価格をカートに保持できない.
 *
 * ここでは「あるべき単価」を割引後の価格として判定し,
 * 明細が割引前の標準価格のまま登録されている場合(カート投入直後・受注明細の生成直後)は
 * 警告を出さずに割引価格へ置き換える.
 * それ以外の不一致は従来どおり価格変更として検知する.
 */
class DiscountedPriceChangeValidator extends ItemValidator
{
    /**
     * @var ProductDiscountService
     */
    protected $productDiscountService;

    public function __construct(ProductDiscountService $productDiscountService)
    {
        $this->productDiscountService = $productDiscountService;
    }

    /**
     * @param ItemInterface $item
     * @param PurchaseContext $context
     *
     * @throws \Eccube\Service\PurchaseFlow\InvalidItemException
     */
    public function validate(ItemInterface $item, PurchaseContext $context)
    {
        if (!$item->isProduct()) {
            return;
        }

        $ProductClass = $item->getProductClass();

        if ($item instanceof OrderItem) {
            // OrderItem::price は税抜金額.
            $listPrice = $ProductClass->getPrice02();
            $realPrice = $this->productDiscountService->getDiscountedPrice02($ProductClass);
        } else {
            // CartItem::price は税込金額.
            $listPrice = $ProductClass->getPrice02IncTax();
            $realPrice = $this->productDiscountService->getDiscountedPrice02IncTax($ProductClass);
        }

        $price = $item->getPrice();

        if ($price == $realPrice) {
            return;
        }

        // 標準価格のまま積まれている明細は, 価格変更ではなく割引の未適用とみなす.
        $notYetDiscounted = ($price == $listPrice);

        $item->setPrice($realPrice);

        if (!$notYetDiscounted) {
            $this->throwInvalidItemException('front.shopping.price_changed', $ProductClass);
        }
    }
}
