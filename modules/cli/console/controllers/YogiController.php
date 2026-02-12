<?php
namespace modules\cli\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\User;
use craft\commerce\elements\Order;
use craft\helpers\StringHelper;
use yii\console\ExitCode;
use modules\cli\jobs\CreateOrderJob;

class YogiController extends Controller
{
    // Options for create-order command
    public string $email = 'test@example.com';
    public int $qty = 1;
    public int $variantId = 135;
    public int $count = 10;

    /**
     * Define command options
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'create-order') {
            $options[] = 'email';
            $options[] = 'qty';
            $options[] = 'variantId';
        }

        if ($actionID === 'bulk-orders') {
            $options[] = 'email';
            $options[] = 'qty';
            $options[] = 'variantId';
            $options[] = 'count';
        }

        return $options;
    }

    // Add this new action
    /**
     * Create multiple orders using Queue
     * Usage: ddev exec php craft cli/yogi/bulk-orders --count=1000
     * Usage: ddev exec php craft cli/yogi/bulk-orders --count=100 --email=test@shop.com
     */
    public function actionBulkOrders(): int
    {
        $this->stdout("Adding {$this->count} orders to queue...\n\n");

        for ($i = 1; $i <= $this->count; $i++) {
            Craft::$app->getQueue()->push(new CreateOrderJob([
                'email' => $this->email,
                'variantId' => $this->variantId,
                'qty' => $this->qty,
                'batchNumber' => $i,
            ]));

            if ($i % 100 === 0) {
                $this->stdout("✓ Queued {$i} orders...\n");
            }
        }

        $this->stdout("\n========================================\n");
        $this->stdout("✅ SUCCESS! {$this->count} orders added to queue!\n");
        $this->stdout("========================================\n\n");
        $this->stdout("Run the queue with:\n");
        $this->stdout("  ddev exec php craft queue/run\n\n");
        $this->stdout("Or watch progress in admin:\n");
        $this->stdout("  /admin/utilities/queue-manager\n");

        return ExitCode::OK;
    }

    /**
     * List all users
     */
    public function actionListUsers(): int
    {
        $users = User::find()->all();

        if (!$users) {
            $this->stdout("No users found.\n");
            return ExitCode::OK;
        }

        $this->stdout("Found " . count($users) . " users:\n\n");

        foreach ($users as $user) {
            $this->stdout(sprintf(
                "- %s | %s | Admin: %s | Status: %s\n",
                $user->username,
                $user->email,
                $user->admin ? 'yes' : 'no',
                $user->status
            ));
        }

        return ExitCode::OK;
    }

    /**
     * List recent orders
     */
    public function actionListOrders(int $limit = 10): int
    {
        $orders = Order::find()
            ->isCompleted(true)
            ->orderBy('dateOrdered DESC')
            ->limit($limit)
            ->all();

        if (!$orders) {
            $this->stdout("No orders found.\n");
            return ExitCode::OK;
        }

        $this->stdout("Found " . count($orders) . " orders:\n\n");

        foreach ($orders as $order) {
            $this->stdout(sprintf(
                "- #%s | %s | %s %.2f | %s | %s\n",
                $order->reference,
                $order->email,
                $order->currency,
                $order->total,
                $order->paidStatus,
                $order->dateOrdered->format('Y-m-d H:i')
            ));
        }

        return ExitCode::OK;
    }

    /**
     * Show system info
     */
    public function actionInfo(): int
    {
        $this->stdout("=== Craft CMS Info ===\n");
        $this->stdout("Version: " . Craft::$app->getVersion() . "\n");
        $this->stdout("Environment: " . Craft::$app->env . "\n");
        $this->stdout("PHP: " . PHP_VERSION . "\n");

        return ExitCode::OK;
    }

    /**
     * Show total revenue
     */
    public function actionRevenue(): int
    {
        $orders = Order::find()->isCompleted(true)->all();

        if (!$orders) {
            $this->stdout("No completed orders found.\n");
            return ExitCode::OK;
        }

        $totalRevenue = 0;
        $totalPaid = 0;

        foreach ($orders as $order) {
            $totalRevenue += (float) $order->total;
            $totalPaid += (float) $order->totalPaid;
        }

        $this->stdout("=== Revenue Report ===\n\n");
        $this->stdout(sprintf("Total Orders: %d\n", count($orders)));
        $this->stdout(sprintf("Total Revenue: EUR %.2f\n", $totalRevenue));
        $this->stdout(sprintf("Total Paid: EUR %.2f\n", $totalPaid));
        $this->stdout(sprintf("Outstanding: EUR %.2f\n", $totalRevenue - $totalPaid));

        return ExitCode::OK;
    }

    /**
     * Create a paid order
     * Usage: ddev exec php craft cli/yogi/create-order
     * Usage: ddev exec php craft cli/yogi/create-order --email=john@example.com --qty=3
     */
    public function actionCreateOrder(): int
    {
        // Get variant info
        $variant = $this->_getVariantInfo($this->variantId);

        if (!$variant) {
            $this->stderr("Error: Variant ID {$this->variantId} not found.\n");
            return ExitCode::UNSPECIFIED_ERROR;
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

        $this->stdout("Creating paid order...\n");
        $this->stdout("Product: {$config['description']} ({$config['sku']})\n");
        $this->stdout("Quantity: {$config['qty']} x {$config['currency']} " . number_format($config['unitPrice'], 2) . "\n");
        $this->stdout("Total: {$config['currency']} " . number_format($config['qty'] * $config['unitPrice'], 2) . "\n\n");

        try {
            $result = $this->_insertOrder($config);

            $this->stdout("✓ Created element ID: {$result['orderId']}\n");
            $this->stdout("✓ Created order record\n");
            $this->stdout("✓ Created line item record\n");
            $this->stdout("✓ Created transaction record\n");

            $this->stdout("\n========================================\n");
            $this->stdout("✅ SUCCESS! Paid order created!\n");
            $this->stdout("========================================\n");
            $this->stdout("Order ID: {$result['orderId']}\n");
            $this->stdout("Reference: {$result['reference']}\n");
            $this->stdout("Email: {$config['email']}\n");
            $this->stdout("Status: PAID\n");
            $this->stdout("\nView in admin: /admin/commerce/orders/{$result['orderId']}\n");

            return ExitCode::OK;

        } catch (\Exception $e) {
            $this->stderr("\n❌ ERROR: " . $e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    // ============================================
    // PRIVATE HELPER METHODS
    // ============================================

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
    private function _insertOrder(array $config): array
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
            $db->createCommand()->insert('commerce_orders', $this->_buildOrderData($orderId, $orderNumber, $orderReference, $uid, $config, $itemTotal, $now))->execute();

            // 4. Create commerce_lineitems
            $db->createCommand()->insert('commerce_lineitems', $this->_buildLineItemData($orderId, $config, $itemTotal, $now))->execute();

            // 5. Create commerce_transactions
            $db->createCommand()->insert('commerce_transactions', $this->_buildTransactionData($orderId, $config, $itemTotal, $now))->execute();

            $transaction->commit();

            return [
                'orderId' => $orderId,
                'orderNumber' => $orderNumber,
                'reference' => $orderReference,
            ];

        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Build order data array
     */
    private function _buildOrderData(int $orderId, string $orderNumber, string $reference, string $uid, array $config, float $itemTotal, string $now): array
    {
        return [
            'id' => $orderId,
            'storeId' => 1,
            'gatewayId' => 1,
            'customerId' => 1,
            'orderStatusId' => 1,
            'number' => $orderNumber,
            'reference' => $reference,
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
        ];
    }

    /**
     * Build line item data array
     */
    private function _buildLineItemData(int $orderId, array $config, float $itemTotal, string $now): array
    {
        return [
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
        ];
    }

    /**
     * Build transaction data array
     */
    private function _buildTransactionData(int $orderId, array $config, float $itemTotal, string $now): array
    {
        $hash = md5(uniqid(mt_rand(), true));

        return [
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
            'message' => 'Payment successful (CLI)',
            'response' => json_encode(['status' => 'success', 'source' => 'cli']),
            'dateCreated' => $now,
            'dateUpdated' => $now,
            'uid' => StringHelper::UUID(),
        ];
    }

    # CUSTOM REVENUE WIDGET SECTION
    /**
     * Initialize revenue table from existing orders
     * Run this ONCE to populate initial data
     * Usage: ddev exec php craft cli/yogi/init-revenue
     */
    public function actionInitRevenue(): int
    {
        $this->stdout("Initializing revenue from existing orders...\n");
        $this->stdout("This may take a while for large datasets...\n\n");

        $startTime = microtime(true);

        $revenue = \modules\cli\CliModule::getInstance()->revenue->initializeFromOrders();

        $elapsed = round(microtime(true) - $startTime, 2);

        $this->stdout("✓ Revenue table initialized!\n\n");
        $this->stdout("========================================\n");
        $this->stdout("Total Revenue: {$revenue['currency']} " . number_format((float)$revenue['totalRevenue'], 2) . "\n");
        $this->stdout("Total Paid: {$revenue['currency']} " . number_format((float)$revenue['totalPaid'], 2) . "\n");
        $this->stdout("Total Refunded: {$revenue['currency']} " . number_format((float)$revenue['totalRefunded'], 2) . "\n");
        $this->stdout("Order Count: " . number_format((int)$revenue['orderCount']) . "\n");
        $this->stdout("Paid Orders: " . number_format((int)$revenue['paidOrderCount']) . "\n");
        $this->stdout("========================================\n");
        $this->stdout("Completed in {$elapsed} seconds\n");

        return ExitCode::OK;
    }

    /**
     * Show current revenue stats
     * Usage: ddev exec php craft cli/yogi/show-revenue
     */
    public function actionShowRevenue(): int
    {
        $revenue = \modules\cli\CliModule::getInstance()->revenue->getRevenue();

        if (!$revenue || empty($revenue['orderCount'])) {
            $this->stdout("No revenue data found. Run 'init-revenue' first.\n");
            return ExitCode::OK;
        }

        $this->stdout("========================================\n");
        $this->stdout("       REVENUE SUMMARY (Fast)\n");
        $this->stdout("========================================\n\n");
        $this->stdout("Total Revenue:  {$revenue['currency']} " . number_format((float)$revenue['totalRevenue'], 2) . "\n");
        $this->stdout("Total Paid:     {$revenue['currency']} " . number_format((float)$revenue['totalPaid'], 2) . "\n");
        $this->stdout("Total Refunded: {$revenue['currency']} " . number_format((float)$revenue['totalRefunded'], 2) . "\n");
        $this->stdout("Net Revenue:    {$revenue['currency']} " . number_format((float)$revenue['totalPaid'] - (float)$revenue['totalRefunded'], 2) . "\n");
        $this->stdout("\n");
        $this->stdout("Total Orders:   " . number_format((int)$revenue['orderCount']) . "\n");
        $this->stdout("Paid Orders:    " . number_format((int)$revenue['paidOrderCount']) . "\n");
        $this->stdout("Refunded:       " . number_format((int)$revenue['refundedOrderCount']) . "\n");
        $this->stdout("\n");
        $this->stdout("Last Updated:   {$revenue['dateUpdated']}\n");
        $this->stdout("========================================\n");

        return ExitCode::OK;
    }

}