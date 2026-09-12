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

namespace Customize\EventListener;

use Eccube\Event\TemplateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * 商品登録・編集画面に割引率の入力欄を差し込む.
 *
 * 932行ある product.twig をコピーして上書きすると本体のアップデートに追従できなくなるため,
 * 検索ワード欄の直後にスニペットを挿入する形で対応する.
 */
class AdminProductDiscountSubscriber implements EventSubscriberInterface
{
    /**
     * 検索ワードの入力欄とその行を閉じる div までをアンカーにする.
     */
    private const ANCHOR_PATTERN = '{(\{\{\s*form_errors\(form\.search_word\)\s*\}\}\s*</div>\s*</div>\s*</div>)}';

    public static function getSubscribedEvents()
    {
        return [
            '@admin/Product/product.twig' => 'onAdminProductTwig',
        ];
    }

    public function onAdminProductTwig(TemplateEvent $event)
    {
        $source = $event->getSource();

        $replaced = preg_replace(
            self::ANCHOR_PATTERN,
            '$1'."\n".'{% include \'@admin/Product/_discount_rate.twig\' %}',
            $source,
            1,
            $count
        );

        if (!$count) {
            // 本体のテンプレートが変わりアンカーを見失った場合は何もしない.
            // 子テンプレートのブロック外に出力を足すと Twig エラーになるため.
            // このとき割引率の欄は描画されないが, 保存時の値は
            // Customize\\Form\\Extension\\Admin\\ProductDiscountTypeExtension の
            // PRE_SUBMIT で保持されるので, 既存の設定値が消えることはない.
            return;
        }

        $event->setSource($replaced);
    }
}
