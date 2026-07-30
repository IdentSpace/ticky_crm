<?php

namespace OCA\TickyCRM\Migration;

use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCA\TickyCRM\Service\AccessService;

class InitializeDavResources implements IRepairStep {
    private AccessService $accessService;

    public function __construct(AccessService $accessService) {
        $this->accessService = $accessService;
    }

    public function getName(): string {
        return 'Initialisiere Ticky CRM DAV Ressourcen';
    }

    public function run(IOutput $output): void {
        $output->info('-> [Ticky CRM CLI] Starte Ressourcen-Erstellung...');

        try {
            $output->info('-> [Ticky CRM CLI] Erstelle Adressbuch...');
            $addressBook = $this->accessService->ensureSystemAddressBookProperties();

            if ($addressBook === null) {
                $output->warning('-> [Ticky CRM CLI] Adressbuch konnte nicht erstellt/geladen werden, Freigaben werden übersprungen.');
                return;
            }

            $output->info('-> [Ticky CRM CLI] Synchronisiere Freigaben...');
            $this->accessService->syncAddressBookSharesFromSettings();
            $output->info('-> [Ticky CRM CLI] Fertig.');
        } catch (\Throwable $e) {
            $output->warning('-> [Ticky CRM CLI] Fehler: ' . get_class($e) . ': ' . $e->getMessage());
            $output->warning($e->getTraceAsString());
        }
    }
}