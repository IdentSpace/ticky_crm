<?php
namespace OCA\TickyCRM\Service;

use OCA\TickyCRM\DB\Client;
use OCA\TickyCRM\DB\ClientMapper;
use OCA\TickyCRM\Service\ActivityService;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;
use DateTime;
use Exception;

class ClientService {

    public function __construct(
        private ClientMapper $mapper,
        private AddressService $addressService,
        private IDBConnection $db,
        private ActivityService $activityService
    ) {}

    public function all(): array {
        $clients = $this->mapper->findAllClients();
        $counts = $this->mapper->getRelationCounts(array_map(fn(Client $client) => $client->getId(), $clients));
        return array_map(fn(Client $client) => array_merge($client->jsonSerialize(), [
            'product_count' => $counts[$client->getId()]['product_count'] ?? 0,
            'contact_count' => $counts[$client->getId()]['contact_count'] ?? 0,
        ]), $clients);
    }

    public function find(string $uuid): Client {
        return $this->mapper->findByUuid($uuid);
    }

    public function create(array $data): Client {
        $data = $this->validate($data, true);
        $this->db->beginTransaction();
        try {
            $client = new Client();
            $client->setUuid(bin2hex(random_bytes(16)));
            $client->setClientNumber($data['client_number']);
            $client->setName($data['name']);
            $client->setType($data['type'] ?? 'company');
            $client->setStatus($data['status'] ?? 'lead');
            $client->setContactEmail($data['contact_email'] ?? null);
            $client->setInvoiceEmail($data['invoice_email'] ?? null);
            $client->setPhone($data['phone'] ?? null);
            $client->setWebsite($data['website'] ?? null);
            $client->setVatId($data['vat_id'] ?? null);
            $client->setTaxNumber($data['tax_number'] ?? null);
            $client->setRegisterCourt($data['register_court'] ?? null);
            $client->setRegisterNumber($data['register_number'] ?? null);
            $client->setCreatedAt(new DateTime());
            $client->setUpdatedAt(new DateTime());

            $insertedClient = $this->mapper->insert($client);

            $addresses = $data['addresses'] ?? [];

            foreach ($addresses as $addressData) {
                $this->addressService->createAddress($insertedClient->getId(), $addressData);
            }

            $this->activityService->log('client', 'created', $client->getName(), $insertedClient->getId(), ['uuid' => $insertedClient->getUuid()]);

            $this->db->commit();

            return $insertedClient;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function update(string $uuid, array $data): Client {
        $data = $this->validate($data, false);
        $this->db->beginTransaction();

        try {
            $client = $this->mapper->findByUuid($uuid, false);

            $addressesData = $data['addresses'] ?? null;
            unset($data['addresses']);

            if (isset($data['name']))            $client->setName($data['name']);
            if (isset($data['client_number']))   $client->setClientNumber($data['client_number']);
            if (isset($data['type']))            $client->setType($data['type']);
            if (isset($data['status']))          $client->setStatus($data['status']);
            if (isset($data['contact_email']))   $client->setContactEmail($data['contact_email']);
            if (isset($data['invoice_email']))   $client->setInvoiceEmail($data['invoice_email']);
            if (isset($data['phone']))           $client->setPhone($data['phone']);
            if (isset($data['website']))         $client->setWebsite($data['website']);
            if (isset($data['vat_id']))          $client->setVatId($data['vat_id']);
            if (isset($data['tax_number']))      $client->setTaxNumber($data['tax_number']);
            if (isset($data['register_court']))  $client->setRegisterCourt($data['register_court']);
            if (isset($data['register_number'])) $client->setRegisterNumber($data['register_number']);

            $client->setUpdatedAt(new DateTime());
            $updatedClient = $this->mapper->update($client);

            if ($addressesData !== null) {
                $clientId = $updatedClient->getId();

                $currentDbAddresses = $this->addressService->getAddressesForClient($clientId);
                $dbAddressIds = array_map(function($addr) {
                    return is_array($addr) ? $addr['id'] : $addr->getId();
                }, $currentDbAddresses);

                $frontendAddressIds = [];

                foreach ($addressesData as $addressData) {
                    if (!empty($addressData['id'])) {
                        $addressId = (int)$addressData['id'];
                        $frontendAddressIds[] = $addressId;
                        $this->addressService->updateAddress($addressId, $addressData);
                    } else {
                        $this->addressService->createAddress($clientId, $addressData);
                    }
                }

                // 2. Löschen: Welche IDs waren in der DB, aber fehlen im Frontend?
                $idsToDelete = array_diff($dbAddressIds, $frontendAddressIds);
                foreach ($idsToDelete as $deleteId) {
                    $this->addressService->deleteAddressById($deleteId);
                }
            }

            $this->activityService->log('client', 'updated', $client->getName(), $client->getId(), ['uuid' => $client->getUuid()]);

            $this->db->commit();

            return $this->mapper->findByUuid($uuid);

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

    }

    public function delete(string $uuid): void {
        $this->mapper->delete($this->mapper->findByUuid($uuid));
    }

    private function validate(array $data, bool $creating): array {
        foreach (['client_number' => 64, 'name' => 255] as $field => $length) {
            if (!$creating && !array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field] ?? null;
            if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $length) {
                throw new \InvalidArgumentException($field);
            }
            $data[$field] = trim($value);
        }
        foreach (['contact_email', 'invoice_email'] as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                continue;
            }
            if (!is_string($data[$field]) || mb_strlen($data[$field]) > 255 || !filter_var(trim($data[$field]), FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException($field);
            }
            $data[$field] = trim($data[$field]);
        }
        foreach (['type' => ['company', 'person'], 'status' => ['lead', 'active', 'inactive']] as $field => $allowed) {
            if (array_key_exists($field, $data) && !in_array($data[$field], $allowed, true)) {
                throw new \InvalidArgumentException($field);
            }
        }
        if (isset($data['addresses']) && (!is_array($data['addresses']) || array_filter($data['addresses'], fn($address) => !is_array($address)) !== [])) {
            throw new \InvalidArgumentException('addresses');
        }
        return $data;
    }
}
