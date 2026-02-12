<?php

/**
 * Craft Commerce - Create Paid Order via CLI
 * 
 * Run with: ddev exec php create-paid-order.php
 */

// Bootstrap Craft
define('CRAFT_BASE_PATH', __DIR__);
define('CRAFT_VENDOR_PATH', CRAFT_BASE_PATH . '/vendor');

require_once CRAFT_VENDOR_PATH . '/autoload.php';

// Load dotenv
if (class_exists('Dotenv\Dotenv') && file_exists(CRAFT_BASE_PATH . '/.env')) {
    Dotenv\Dotenv::createUnsafeImmutable(CRAFT_BASE_PATH)->safeLoad();
}

// Load Craft
define('CRAFT_ENVIRONMENT', getenv('CRAFT_ENVIRONMENT') ?: 'production');
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

// ============================================
// CONFIGURATION - Modify these values as needed
// ============================================
$config = [
    'email' => 'test@example.com',
    'variantId' => 135,           // TRAIN-001 variant
    'qty' => 2,
    'unitPrice' => 499.00,
    'sku' => 'TRAIN-001',
    'description' => 'Craft CMS Training Course',
    'storeId' => 1,
    'gatewayId' => 1,
    'customerId' => 1,
    'orderStatusId' => 1,         // "New" status
    'taxCategoryId' => 1,
    'shippingCategoryId' => 1,
    'siteId' => 1,
    'currency' => 'EUR',
];

// Calculate totals
$itemTotal = $config['qty'] * $config['unitPrice'];

echo "Creating paid order...\n";
echo "Product: {$config['description']}\n";
echo "Quantity: {$config['qty']}\n";
echo "Total: {$config['currency']} " . number_format($itemTotal, 2) . "\n\n";

try {
    $db = Craft::$app->getDb();
    $now = date('Y-m-d H:i:s');
    $orderNumber = md5(uniqid(mt_rand(), true));
    $orderReference = substr($orderNumber, 0, 7);
    $uid = \craft\helpers\StringHelper::UUID();
    
    // Start transaction
    $transaction = $db->beginTransaction();
    
    // ============================================
    // 1. INSERT INTO elements
    // ============================================
    $db->createCommand()->insert('elements', [
        'type' => 'craft\\commerce\\elements\\Order',
        'enabled' => 1,
        'archived' => 0,
        'dateCreated' => $now,
        'dateUpdated' => $now,
        'uid' => $uid,
    ])->execute();
    
    $orderId = $db->getLastInsertID();
    echo "✓ Created element ID: {$orderId}\n";
    
    // ============================================
    // 2. INSERT INTO elements_sites
    // ============================================
    $db->createCommand()->insert('elements_sites', [
        'elementId' => $orderId,
        'siteId' => $config['siteId'],
        'title' => "Order {$orderReference}",
        'slug' => null,
        'uri' => null,
        'enabled' => 1,
        'dateCreated' => $now,
        'dateUpdated' => $now,
        'uid' => \craft\helpers\StringHelper::UUID(),
    ])->execute();
    
    echo "✓ Created elements_sites record\n";
    
    // ============================================
    // 3. INSERT INTO commerce_orders
    // ============================================
    $db->createCommand()->insert('commerce_orders', [
        'id' => $orderId,
        'storeId' => $config['storeId'],
        'gatewayId' => $config['gatewayId'],
        'customerId' => $config['customerId'],
        'orderStatusId' => $config['orderStatusId'],
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
        'orderSiteId' => $config['siteId'],
        'registerUserOnOrderComplete' => 0,
        'saveBillingAddressOnOrderComplete' => 0,
        'makePrimaryBillingAddress' => 0,
        'saveShippingAddressOnOrderComplete' => 0,
        'makePrimaryShippingAddress' => 0,
        'dateCreated' => $now,
        'dateUpdated' => $now,
        'uid' => $uid,
    ])->execute();
    
    echo "✓ Created commerce_orders record\n";
    
    // ============================================
    // 4. INSERT INTO commerce_lineitems
    // ============================================
    $lineItemUid = \craft\helpers\StringHelper::UUID();
    
    // Build snapshot (JSON of product data at time of purchase)
    $snapshot = json_encode([
        'productId' => 133,
        'sku' => $config['sku'],
        'description' => $config['description'],
        'price' => $config['unitPrice'],
        'cpEditUrl' => '#',
    ]);
    
    $db->createCommand()->insert('commerce_lineitems', [
        'orderId' => $orderId,
        'type' => 'purchasable',
        'purchasableId' => $config['variantId'],
        'taxCategoryId' => $config['taxCategoryId'],
        'shippingCategoryId' => $config['shippingCategoryId'],
        'description' => $config['description'],
        'options' => '[]',
        'optionsSignature' => 'd751713988987e9331980363e24189ce',  // md5('[]')
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
        'snapshot' => $snapshot,
        'dateCreated' => $now,
        'dateUpdated' => $now,
        'uid' => $lineItemUid,
    ])->execute();
    
    $lineItemId = $db->getLastInsertID();
    echo "✓ Created commerce_lineitems record (ID: {$lineItemId})\n";
    
    // ============================================
    // 5. INSERT INTO commerce_transactions (payment)
    // ============================================
    $transactionHash = md5(uniqid(mt_rand(), true));
    $transactionUid = \craft\helpers\StringHelper::UUID();
    
    $db->createCommand()->insert('commerce_transactions', [
        'orderId' => $orderId,
        'gatewayId' => $config['gatewayId'],
        'hash' => substr($transactionHash, 0, 32),
        'type' => 'purchase',
        'amount' => $itemTotal,
        'paymentAmount' => $itemTotal,
        'currency' => $config['currency'],
        'paymentCurrency' => $config['currency'],
        'paymentRate' => 1.0000,
        'status' => 'success',
        'reference' => 'CLI-' . strtoupper(substr($transactionHash, 0, 8)),
        'code' => '200',
        'message' => 'Payment successful (CLI Script)',
        'response' => json_encode(['status' => 'success', 'source' => 'cli_script']),
        'dateCreated' => $now,
        'dateUpdated' => $now,
        'uid' => $transactionUid,
    ])->execute();
    
    $transactionId = $db->getLastInsertID();
    echo "✓ Created commerce_transactions record (ID: {$transactionId})\n";
    
    // Commit transaction
    $transaction->commit();
    
    echo "\n========================================\n";
    echo "✅ SUCCESS! Paid order created!\n";
    echo "========================================\n";
    echo "Order ID: {$orderId}\n";
    echo "Order Number: {$orderNumber}\n";
    echo "Reference: {$orderReference}\n";
    echo "Status: PAID\n";
    echo "Date: " . date('Y-m-d H:i:s') . "\n";
    echo "Total: {$config['currency']} " . number_format($itemTotal, 2) . "\n";
    echo "\nView in admin: /admin/commerce/orders/{$orderId}\n";
    
} catch (\Exception $e) {
    if (isset($transaction)) {
        $transaction->rollBack();
    }
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
