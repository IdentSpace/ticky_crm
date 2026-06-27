<?php
namespace OCA\TickyCRM\Service;

use OCA\TickyCRM\DB\Client;
use OCA\TickyCRM\DB\ClientMapper;
use DateTime;

class ClientService {

    public function __construct(private ClientMapper $mapper) {}

    public function all(): array {
        return $this->mapper->findAllClients();
    }

    public function find(string $uuid): Client {
        return $this->mapper->findByUuid($uuid);
    }

    public function create(array $data): Client {
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

        return $this->mapper->insert($client);
    }

    public function update(string $uuid, array $data): Client {
        $client = $this->mapper->findByUuid($uuid);

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

        return $this->mapper->update($client);
    }

    public function delete(string $uuid): void {
        $this->mapper->delete($this->mapper->findByUuid($uuid));
    }
}