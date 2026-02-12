<?php
namespace modules\cli\services;

use Craft;
use yii\base\Component;

class RevenueService extends Component
{
    /**
     * Get current revenue data
     */
    public function getRevenue(int $storeId = 1): array
    {
        $db = Craft::$app->getDb();

        $result = $db->createCommand("
            SELECT * FROM {{%commerce_revenue}} WHERE storeId = :storeId
        ", ['storeId' => $storeId])->queryOne();

        if (!$result) {
            return [
                'totalRevenue' => 0,
                'totalPaid' => 0,
                'totalRefunded' => 0,
                'orderCount' => 0,
                'paidOrderCount' => 0,
                'refundedOrderCount' => 0,
                'currency' => 'EUR',
            ];
        }

        return $result;
    }

    /**
     * Add order to revenue (when order is completed)
     */
    public function addOrder(float $total, float $paid, int $storeId = 1, string $currency = 'EUR'): void
    {
        $this->_ensureRecord($storeId, $currency);

        $db = Craft::$app->getDb();
        $db->createCommand("
        UPDATE {{%commerce_revenue}} 
        SET 
            totalRevenue = totalRevenue + :total,
            totalPaid = totalPaid + :paid,
            orderCount = orderCount + 1,
            paidOrderCount = paidOrderCount + IF(:paid > 0, 1, 0),
            dateUpdated = NOW()
        WHERE storeId = :storeId
    ", [
            'total' => $total,
            'paid' => $paid,
            'storeId' => $storeId,
        ])->execute();
    }

    /**
     * Update payment (when order is paid)
     */
    public function addPayment(float $amount, int $storeId = 1): void
    {
        $this->_ensureRecord($storeId);

        $db = Craft::$app->getDb();
        $db->createCommand("
            UPDATE {{%commerce_revenue}} 
            SET 
                totalPaid = totalPaid + :amount,
                paidOrderCount = paidOrderCount + 1,
                dateUpdated = NOW()
            WHERE storeId = :storeId
        ", [
            'amount' => $amount,
            'storeId' => $storeId,
        ])->execute();
    }

    /**
     * Add refund
     */
    public function addRefund(float $amount, int $storeId = 1): void
    {
        $this->_ensureRecord($storeId);

        $db = Craft::$app->getDb();
        $db->createCommand("
        UPDATE {{%commerce_revenue}} 
        SET 
            totalRefunded = totalRefunded + :amount,
            totalPaid = totalPaid - :amount,
            refundedOrderCount = refundedOrderCount + 1,
            paidOrderCount = paidOrderCount - 1,
            dateUpdated = NOW()
        WHERE storeId = :storeId
    ", [
            'amount' => $amount,
            'storeId' => $storeId,
        ])->execute();
    }

    /**
     * Remove order (when order is cancelled/deleted)
     */
    public function removeOrder(float $total, float $paid, int $storeId = 1): void
    {
        $this->_ensureRecord($storeId);

        $db = Craft::$app->getDb();
        $db->createCommand("
        UPDATE {{%commerce_revenue}} 
        SET 
            totalRevenue = totalRevenue - :total,
            totalPaid = totalPaid - :paid,
            orderCount = orderCount - 1,
            paidOrderCount = paidOrderCount - IF(:paid > 0, 1, 0),
            dateUpdated = NOW()
        WHERE storeId = :storeId
    ", [
            'total' => $total,
            'paid' => $paid,
            'storeId' => $storeId,
        ])->execute();
    }

    /**
     * Initialize revenue from existing orders (run once)
     */
    public function initializeFromOrders(int $storeId = 1): array
    {
        $db = Craft::$app->getDb();

        // Get totals - JOIN with elements to exclude soft-deleted orders
        $result = $db->createCommand("
        SELECT 
            COALESCE(SUM(co.total), 0) as totalRevenue,
            COALESCE(SUM(co.totalPaid), 0) as totalPaid,
            COUNT(*) as orderCount,
            SUM(CASE WHEN co.paidStatus = 'paid' THEN 1 ELSE 0 END) as paidOrderCount
        FROM {{%commerce_orders}} co
        INNER JOIN {{%elements}} e ON co.id = e.id
        WHERE co.isCompleted = 1 
          AND e.dateDeleted IS NULL
    ")->queryOne();

        // Get most common currency
        $currencyResult = $db->createCommand("
        SELECT co.currency, COUNT(*) as cnt 
        FROM {{%commerce_orders}} co
        INNER JOIN {{%elements}} e ON co.id = e.id
        WHERE co.isCompleted = 1 
          AND e.dateDeleted IS NULL
        GROUP BY co.currency 
        ORDER BY cnt DESC 
        LIMIT 1
    ")->queryOne();

        $currency = $currencyResult['currency'] ?? 'EUR';

        if (!$result) {
            $result = [
                'totalRevenue' => 0,
                'totalPaid' => 0,
                'orderCount' => 0,
                'paidOrderCount' => 0,
            ];
        }

        // Get refund totals (only for non-deleted orders)
        $refunds = $db->createCommand("
        SELECT COALESCE(SUM(t.amount), 0) as totalRefunded, COUNT(*) as refundCount
        FROM {{%commerce_transactions}} t
        INNER JOIN {{%commerce_orders}} co ON t.orderId = co.id
        INNER JOIN {{%elements}} e ON co.id = e.id
        WHERE t.type = 'refund' 
          AND t.status = 'success'
          AND e.dateDeleted IS NULL
    ")->queryOne();

        $now = date('Y-m-d H:i:s');

        // Delete existing record for store
        $db->createCommand()->delete('{{%commerce_revenue}}', ['storeId' => $storeId])->execute();

        // Insert fresh data
        $db->createCommand()->insert('{{%commerce_revenue}}', [
            'storeId' => $storeId,
            'totalRevenue' => $result['totalRevenue'],
            'totalPaid' => $result['totalPaid'],
            'totalRefunded' => $refunds['totalRefunded'] ?? 0,
            'orderCount' => $result['orderCount'],
            'paidOrderCount' => $result['paidOrderCount'],
            'refundedOrderCount' => $refunds['refundCount'] ?? 0,
            'currency' => $currency,
            'dateCreated' => $now,
            'dateUpdated' => $now,
        ])->execute();

        return $this->getRevenue($storeId);
    }

    /**
     * Ensure record exists for store
     */
    private function _ensureRecord(int $storeId = 1, string $currency = 'EUR'): void
    {
        $db = Craft::$app->getDb();

        $exists = $db->createCommand("
            SELECT id FROM {{%commerce_revenue}} WHERE storeId = :storeId
        ", ['storeId' => $storeId])->queryScalar();

        if (!$exists) {
            $now = date('Y-m-d H:i:s');
            $db->createCommand()->insert('{{%commerce_revenue}}', [
                'storeId' => $storeId,
                'totalRevenue' => 0,
                'totalPaid' => 0,
                'totalRefunded' => 0,
                'orderCount' => 0,
                'paidOrderCount' => 0,
                'refundedOrderCount' => 0,
                'currency' => $currency,
                'dateCreated' => $now,
                'dateUpdated' => $now,
            ])->execute();
        }
    }
}