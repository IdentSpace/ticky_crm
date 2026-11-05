<?php

namespace OCA\TickyCRM\Service;

use OCA\TickyCRM\DB\Client;
use OCA\TickyCRM\DB\ClientProduct;
use Psr\Log\LoggerInterface;

class ProductActivityService {
    public function __construct(private ActivityService $activities, private LoggerInterface $logger) {
    }

    public function log(string $objectType, string $action, string $name, int $objectId, array $params = []): void {
        try {
            $this->activities->log($objectType, $action, $name, $objectId, $params);
        } catch (\Throwable $e) {
            // Match contact activities: a logging failure must not undo the operation.
            $this->logger->warning('Ticky CRM: product activity could not be published', [
                'app' => 'ticky_crm', 'exception' => $e, 'action' => $action, 'objectId' => $objectId,
            ]);
        }
    }

    public function association(ClientProduct $position, Client $client, string $action): void {
        $params = [
            'clientUuid' => $client->getUuid(), 'positionUuid' => $position->getUuid(),
            'productNumber' => $position->getProductNumber(), 'quantity' => $position->getQuantity(),
            'salesPriceNet' => $position->getSalesPriceNet(), 'currency' => $position->getCurrency(),
            'salesTaxRate' => $position->getSalesTaxRate(), 'unit' => $position->getUnit(),
            'purchasePriceNet' => $position->getPurchasePriceNet(), 'purchaseTaxRate' => $position->getPurchaseTaxRate(),
        ];
        $this->log('client', 'product_' . $action, $position->getName(), $client->getId(), $params);
        if ($position->getProductId() !== null) {
            $this->log('product', 'client_' . $action, $client->getName(), $position->getProductId(), $params);
        }
    }
}
