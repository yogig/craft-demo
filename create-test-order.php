<?php

// Load shared bootstrap
require __DIR__ . '/bootstrap.php';

// Load Craft console app
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\Plugin as Commerce;
use craft\elements\Address;
use craft\elements\User;

echo "Step 1: Getting products...\n";
$products = Product::find()->all();

if (empty($products)) {
    echo "ERROR: No products found!\n";
    exit(1);
}

echo "Found " . count($products) . " products.\n\n";

echo "Step 2: Creating new order...\n";
$order = new Order();
$order->number = 'TEST-001';

// Try to find existing customer or use first user
$email = "test@example.com";
$user = Craft::$app->getUsers()->getUserByUsernameOrEmail($email);

if (!$user) {
    // Get first user in the system
    $user = User::find()->one();
}

if (!$user) {
    echo "ERROR: No users found in system!\n";
    exit(1);
}

echo "Using customer: " . $user->email . " (ID: " . $user->id . ")\n";
$order->setCustomer($user);
$order->dateOrdered = new DateTime();

echo "Step 3: Creating billing address...\n";
$address = new Address();
$address->title = 'Test Address';
$address->firstName = 'Test';
$address->lastName = 'Customer';
$address->addressLine1 = 'Wolfstrasse 21';
$address->locality = 'Giessen';
$address->postalCode = '35394';
$address->countryCode = 'DE';
$address->administrativeArea = 'Hessen';

echo "Step 4: Saving address...\n";
if (!Craft::$app->getElements()->saveElement($address)) {
    echo "ERROR: Failed to save address!\n";
    echo "Errors: " . json_encode($address->getErrors()) . "\n";
    exit(1);
}
echo "Address saved with ID: " . $address->id . "\n\n";

echo "Step 5: Setting order addresses...\n";
$order->setBillingAddress($address);
$order->setShippingAddress($address);

echo "Step 6: Adding product to order...\n";
$firstProduct = $products[0];
$variant = $firstProduct->getDefaultVariant();

echo "Adding product: " . $firstProduct->title . "\n";
echo "Variant ID: " . $variant->id . "\n";
echo "Price: " . $variant->price . "\n\n";

$lineItem = Commerce::getInstance()->getLineItems()->createLineItem(
    $order,
    $variant->id,
    [],
    1
);
$order->addLineItem($lineItem);

echo "Step 7: Saving order...\n";
if (!Craft::$app->getElements()->saveElement($order)) {
    echo "ERROR: Failed to save order!\n";
    exit(1);
}
echo "Order saved with ID: " . $order->id . "\n\n";

echo "Step 8: Marking order as complete...\n";
$order->markAsComplete();

echo "\nSUCCESS! Order created:\n";
echo "Order Number: " . $order->number . "\n";
echo "Order ID: " . $order->id . "\n";
echo "Email: " . $order->email . "\n";
echo "Total: " . $order->totalPrice . "\n";
echo "\nCheck admin: Commerce -> Orders\n";
