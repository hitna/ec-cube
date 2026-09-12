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

namespace Customize\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;

/**
 * 商品ごとの割引率を保持する.
 *
 * @EntityExtension("Eccube\Entity\Product")
 */
trait ProductDiscountTrait
{
    /**
     * 割引率(%). null または 0 の場合は割引なし.
     *
     * @ORM\Column(name="discount_rate", type="smallint", nullable=true)
     */
    public $discount_rate;

    /**
     * @return int|null
     */
    public function getDiscountRate()
    {
        return null === $this->discount_rate ? null : (int) $this->discount_rate;
    }

    /**
     * @param int|null $discount_rate
     *
     * @return $this
     */
    public function setDiscountRate($discount_rate)
    {
        $this->discount_rate = $discount_rate;

        return $this;
    }
}
