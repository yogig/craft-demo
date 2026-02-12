<?php
namespace modules\cli\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\helpers\StringHelper;

class CreateOrderJob extends BaseJob
{
    public string $email = 'test@example.com';
    public int $variantId = 135;
    public int $qty = 1;
    public int $batchNumber = 1;

    /**
     * Execute the job
     */
    public function execute($queue): void
    {
        $variant = $this->_getVariantInfo($this->variantId);

        if (!$variant) {
            throw new \Exception("Variant ID {$this->variantId} not found.");
        }

        $config = [
            'email' => $this->email,
            'variantId' => $this->variantId,
            'qty' => $this->qty,
            'unitPrice' => (float) $variant['basePrice'],
            'sku' => $variant['sku'],
            'description' => $variant['description'],
            'currency' => 'EUR',
        ];

        $this->_insertOrder($config);
    }

    /**
     * Job description for admin panel
     */
    protected function defaultDescription(): ?string
    {
        return "Creating order #{$this->batchNumber} for {$this->email}";
    }

    /**
     * Get variant info from database
     */
    private function _getVariantInfo(int $variantId): ?array
    {
        return Craft::$app->getDb()->createCommand("
            SELECT p.sku, p.description, ps.basePrice 
            FROM commerce_purchasables p
            JOIN commerce_purchasables_stores ps ON p.id = ps.purchasableId
            WHERE p.id = :variantId
        ", ['variantId' => $variantId])->queryOne() ?: null;
    }

    /**
     * Insert order into database
     */
    private function _insertOrder(array $config): void
    {
        $db = Craft::$app->getDb();
        $now = date('Y-m-d H:i:s');
        $orderNumber = md5(uniqid(mt_rand(), true));
        $orderReference = substr($orderNumber, 0, 7);
        $uid = StringHelper::UUID();
        $itemTotal = $config['qty'] * $config['unitPrice'];

        $transaction = $db->beginTransaction();

        try {
            // 1. Create element
            $db->createCommand()->insert('elements', [
                'type' => 'craft\\commerce\\elements\\Order',
                'enabled' => 1,
                'archived' => 0,
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid' => $uid,
            ])->execute();

            $orderId = $db->getLastInsertID();

            // 2. Create elements_sites
            $db->createCommand()->insert('elements_sites', [
                'elementId' => $orderId,
                'siteId' => 1,
                'title' => "Order {$orderReference}",
                'enabled' => 1,
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid' => StringHelper::UUID(),
            ])->execute();

            // 3. Create commerce_orders
            $db->createCommand()->insert('commerce_orders', [
                'id' => $orderId,
                'storeId' => 1,
                'gatewayId' => 1,
                'customerId' => 1,
                'orderStatusId' => 1,
                'number' => $orderNumber,
                'reference' => $orderReference,
                'email' => $config['email'],
                'orderCompletedEmail' => $config['email'],
                'isCompleted' => 1,
                'dateOrdered' => $now,
                'datePaid' => $now,
                'dateFirstPaid' => $now,
                'itemTotal' => $itemTotal,
                'itemSubtotal' => $itemTotal,
                'totalQty' => $config['qty'],
                'totalWeight' => 0,
                'total' => $itemTotal,
                'totalPrice' => $itemTotal,
                'totalPaid' => $itemTotal,
                'totalDiscount' => 0,
                'totalTax' => 0,
                'totalTaxIncluded' => 0,
                'totalShippingCost' => 0,
                'paidStatus' => 'paid',
                'currency' => $config['currency'],
                'paymentCurrency' => $config['currency'],
                'lastIp' => '127.0.0.1',
                'orderLanguage' => 'en',
                'origin' => 'remote',
                'recalculationMode' => 'none',
                'shippingMethodHandle' => 'freeShipping',
                'shippingMethodName' => 'Free Shipping',
                'orderSiteId' => 1,
                'registerUserOnOrderComplete' => 0,
                'saveBillingAddressOnOrderComplete' => 0,
                'makePrimaryBillingAddress' => 0,
                'saveShippingAddressOnOrderComplete' => 0,
                'makePrimaryShippingAddress' => 0,
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid' => $uid,
            ])->execute();

            // 4. Create commerce_lineitems
            $db->createCommand()->insert('commerce_lineitems', [
                'orderId' => $orderId,
                'type' => 'purchasable',
                'purchasableId' => $config['variantId'],
                'taxCategoryId' => 1,
                'shippingCategoryId' => 1,
                'description' => $config['description'],
                'options' => '[]',
                'optionsSignature' => md5('[]'),
                'price' => $config['unitPrice'],
                'salePrice' => $config['unitPrice'],
                'promotionalAmount' => 0,
                'sku' => $config['sku'],
                'weight' => 0,
                'height' => 0,
                'length' => 0,
                'width' => 0,
                'subtotal' => $itemTotal,
                'total' => $itemTotal,
                'qty' => $config['qty'],
                'hasFreeShipping' => 1,
                'isPromotable' => 1,
                'isShippable' => 1,
                'isTaxable' => 1,
                'snapshot' => json_encode([
                    'sku' => $config['sku'],
                    'description' => $config['description'],
                    'price' => $config['unitPrice'],
                ]),
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid' => StringHelper::UUID(),
            ])->execute();

            // 5. Create commerce_transactions
            $hash = md5(uniqid(mt_rand(), true));
            $db->createCommand()->insert('commerce_transactions', [
                'orderId' => $orderId,
                'gatewayId' => 1,
                'hash' => substr($hash, 0, 32),
                'type' => 'purchase',
                'amount' => $itemTotal,
                'paymentAmount' => $itemTotal,
                'currency' => $config['currency'],
                'paymentCurrency' => $config['currency'],
                'paymentRate' => 1.0000,
                'status' => 'success',
                'reference' => 'CLI-' . strtoupper(substr($hash, 0, 8)),
                'code' => '200',
                'message' => 'Payment successful (Queue)',
                'response' => json_encode(['status' => 'success', 'source' => 'queue']),
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid' => StringHelper::UUID(),
            ])->execute();

            $transaction->commit();

        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }
}