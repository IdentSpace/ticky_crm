<?php

// Run inside Nextcloud as www-data. All test rows are removed afterwards.
define('OC_CONSOLE', true);
require_once '/var/www/html/lib/base.php';

use OCA\TickyCRM\DB\Product;
use OCA\TickyCRM\DB\ProductMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception as DatabaseException;
use OCP\IDBConnection;
use OCP\Server;

// Nextcloud's web exception handler can return exit code 0 for CLI failures.
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, (string)$error . "\n");
    exit(1);
});

function check(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$mapper = new ProductMapper(Server::get(IDBConnection::class));
$token = bin2hex(random_bytes(12));
$created = [];

function makeProduct(string $uuid, string $number): Product {
    $product = new Product();
    $product->setUuid($uuid);
    $product->setProductNumber($number);
    $product->setName('Product persistence test');
    $product->setUnit('hour');
    $product->setCurrency('EUR');
    $product->setSalesPriceNet('1234.5678');
    $product->setSalesTaxRate('19.00');
    $product->setCreatedAt(new DateTime());
    $product->setUpdatedAt(new DateTime());
    return $product;
}

try {
    $product = $mapper->insert(makeProduct($token, 'TEST-' . $token));
    $created[] = $product;
    $stored = $mapper->findByUuid($token);
    check($stored->getSalesPriceNet() === '1234.5678', 'Sales price lost precision');
    check($stored->getPurchasePriceNet() === null && $stored->getPurchaseTaxRate() === null, 'Unknown purchase values must remain NULL');
    check($stored->getType() === Product::TYPE_PRODUCT, 'Missing default type');
    check($stored->getStatus() === Product::STATUS_ACTIVE, 'Missing default status');
    check($mapper->findByProductNumber('TEST-' . $token)->getId() === $product->getId(), 'Article lookup failed');

    $stored->setPurchasePriceNet('987.6543');
    $stored->setPurchaseTaxRate('7.00');
    $stored->setType(Product::TYPE_SERVICE);
    $stored->setProcurementType(Product::PROCUREMENT_EXTERNAL);
    $stored->setCategory('Consulting');
    $mapper->update($stored);
    $stored = $mapper->findById($product->getId());
    check($stored->getPurchasePriceNet() === '987.6543', 'Purchase price lost precision');
    check((float)$stored->getPurchaseTaxRate() === 7.0, 'Purchase tax round trip failed');
    check((float)$stored->getSalesTaxRate() === 19.0, 'Sales tax round trip failed');
    check($stored->getType() === Product::TYPE_SERVICE && $stored->getCategory() === 'Consulting', 'Categorization round trip failed');
    check($stored->getCreatedAt() instanceof DateTime, 'Datetime hydration failed');
    $json = json_decode(json_encode($stored, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    check($json['sales_price_net'] === '1234.5678' && $json['purchase_price_net'] === '987.6543', 'JSON must preserve decimal strings');

    $stored->setPurchasePriceNet('0');
    $stored->setPurchaseTaxRate('0');
    $mapper->update($stored);
    $stored = $mapper->findByUuid($token);
    check($stored->getPurchasePriceNet() !== null && (float)$stored->getPurchasePriceNet() === 0.0, 'Zero purchase price must differ from NULL');
    check($stored->getPurchaseTaxRate() !== null && (float)$stored->getPurchaseTaxRate() === 0.0, 'Zero tax must differ from NULL');

    $stored->setPurchasePriceNet(null);
    $stored->setPurchaseTaxRate(null);
    $stored->setStatus(Product::STATUS_ARCHIVED);
    $mapper->update($stored);
    $stored = $mapper->findByUuid($token);
    check($stored->getPurchasePriceNet() === null && $stored->getPurchaseTaxRate() === null, 'Clearing purchase values failed');
    check($stored->getStatus() === Product::STATUS_ARCHIVED, 'Archiving failed');

    foreach (['uuid', 'product_number'] as $field) {
        $duplicate = makeProduct($field === 'uuid' ? $token : $token . '-other', $field === 'product_number' ? 'TEST-' . $token : 'OTHER-' . $token);
        $rejected = false;
        try {
            $created[] = $mapper->insert($duplicate);
        } catch (DatabaseException $e) {
            if ($e->getReason() !== DatabaseException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
                throw $e;
            }
            $rejected = true;
        }
        check($rejected, 'Duplicate ' . $field . ' was accepted');
    }

    $mapper->delete($stored);
    array_shift($created);
    $missing = false;
    try {
        $mapper->findByUuid($token);
    } catch (DoesNotExistException $e) {
        $missing = true;
    }
    check($missing, 'Deleted product still exists');
    echo "Product persistence checks passed.\n";
} finally {
    foreach ($created as $product) {
        $mapper->delete($product);
    }
}
