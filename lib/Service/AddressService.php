<?php

namespace OCA\TickyCRM\Service;

use OCA\TickyCRM\DB\AddressMapper;
use OCA\TickyCRM\DB\Address;
use OCP\AppFramework\Db\DoesNotExistException;

class AddressService {

    private AddressMapper $mapper;

    public function __construct(AddressMapper $mapper) {
        $this->mapper = $mapper;
    }

    public function getAddresses(string $clientUuid): array {
        return $this->mapper->findByClientUuid($clientUuid);
    }

    /**
     * Holt alle Adressen direkt über die interne relationale ID (für den Sync-Abgleich)
     */
    public function getAddressesForClient(int $clientId): array {
        // Falls dein AddressMapper die Methode noch nicht hat,
        // greift hier Nextclouds Standard findEntities mit einem QueryBuilder
        return $this->mapper->findAddressesByClientId($clientId);
    }

    public function createAddress(int $clientId, array $data): Address {
        $address = new Address();
        $address->setClientId($clientId);
        $address->setType($data['type'] ?? 'billing');
        $address->setLabel($data['label'] ?? null);
        $address->setStreet($data['street'] ?? '');
        $address->setHouseNumber($data['house_number'] ?? null);
        $address->setAddressAddition($data['address_addition'] ?? null);
        $address->setPostalCode($data['postal_code'] ?? '');
        $address->setCity($data['city'] ?? '');
        $address->setCountryCode($data['country_code'] ?? 'DE');

        return $this->mapper->insert($address);
    }

    /**
     * Aktualisiert eine bestehende Adresse anhand ihrer ID
     * * @throws DoesNotExistException Wenn die Adresse nicht existiert
     */
    public function updateAddress(int $id, array $data): Address {
        // 1. Bestehende Entität aus der DB laden
        /** @var Address $address */
        $address = $this->mapper->findById($id);

        // 2. Nur die veränderbaren Felder aus dem Frontend-Payload überschreiben
        if (isset($data['type']))             $address->setType($data['type']);
        if (isset($data['label']))            $address->setLabel($data['label']);
        if (isset($data['street']))           $address->setStreet($data['street']);
        if (isset($data['house_number']))     $address->setHouseNumber($data['house_number']);
        if (isset($data['address_addition'])) $address->setAddressAddition($data['address_addition']);
        if (isset($data['postal_code']))      $address->setPostalCode($data['postal_code']);
        if (isset($data['city']))             $address->setCity($data['city']);
        if (isset($data['country_code']))     $address->setCountryCode($data['country_code']);

        // 3. Zurück in die DB schreiben via Nextcloud QBMapper
        return $this->mapper->update($address);
    }

    /**
     * Löscht eine gezielte Adresse anhand ihrer ID
     */
    public function deleteAddressById(int $id): void {
        try {
            $address = $this->mapper->findById($id);
            $this->mapper->delete($address);
        } catch (DoesNotExistException $e) {
            // Wenn sie bereits weg ist, müssen wir nichts tun
        }
    }
}