<?php

declare(strict_types=1);

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

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 商品割引機能: dtb_product に割引率カラムを追加する.
 */
final class Version20260912000000 extends AbstractMigration
{
    private const TABLE = 'dtb_product';
    private const COLUMN = 'discount_rate';

    public function getDescription(): string
    {
        return '商品ごとの割引率(discount_rate)を dtb_product に追加';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable(self::TABLE);
        if ($table->hasColumn(self::COLUMN)) {
            return;
        }

        $table->addColumn(self::COLUMN, 'smallint', [
            'notnull' => false,
            'default' => null,
            'comment' => '割引率(%)',
        ]);
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable(self::TABLE);
        if (!$table->hasColumn(self::COLUMN)) {
            return;
        }

        $table->dropColumn(self::COLUMN);
    }
}
