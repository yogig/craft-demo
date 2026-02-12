<?php
namespace modules\cli\migrations;

use craft\db\Migration;

class m260127_000001_create_commerce_revenue_table extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable('{{%commerce_revenue}}', [
            'id' => $this->primaryKey(),
            'storeId' => $this->integer()->notNull()->defaultValue(1),
            'totalRevenue' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'totalPaid' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'totalRefunded' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'orderCount' => $this->integer()->notNull()->defaultValue(0),
            'paidOrderCount' => $this->integer()->notNull()->defaultValue(0),
            'refundedOrderCount' => $this->integer()->notNull()->defaultValue(0),
            'currency' => $this->string(3)->notNull()->defaultValue('EUR'),
            'lastOrderId' => $this->integer()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Create index
        $this->createIndex(null, '{{%commerce_revenue}}', ['storeId'], true);

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%commerce_revenue}}');
        return true;
    }
}