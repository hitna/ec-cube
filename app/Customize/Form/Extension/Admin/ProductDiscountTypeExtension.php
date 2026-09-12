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

namespace Customize\Form\Extension\Admin;

use Eccube\Form\Type\Admin\ProductType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * 商品登録・編集画面に割引率を追加する.
 */
class ProductDiscountTypeExtension extends AbstractTypeExtension
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('discount_rate', IntegerType::class, [
            'required' => false,
            'label' => '割引率',
            'attr' => [
                'placeholder' => '0',
                'min' => 0,
                'max' => 100,
            ],
            'constraints' => [
                new Assert\Range([
                    'min' => 0,
                    'max' => 100,
                    'notInRangeMessage' => '割引率は0〜100の範囲で入力してください。',
                ]),
            ],
        ]);

        // 何らかの理由で入力欄が描画されなかった場合でも,
        // 登録済みの割引率が null で上書きされないようにする.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            if (!is_array($data) || array_key_exists('discount_rate', $data)) {
                return;
            }

            $Product = $event->getForm()->getData();
            if ($Product && method_exists($Product, 'getDiscountRate')) {
                $data['discount_rate'] = $Product->getDiscountRate();
                $event->setData($data);
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public static function getExtendedTypes(): iterable
    {
        return [ProductType::class];
    }
}
