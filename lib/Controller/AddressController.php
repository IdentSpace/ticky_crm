<?php
namespace OCA\TickyCRM\Controller;

use OCA\TickyCRM\Service\AddressService;
use OCP\AppFramework\ApiController;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;

class AddressController extends ApiController {

    private AddressService $service;

    public function __construct(string $appName, IRequest $request, AddressService $service) {
        parent::__construct($appName, $request);
        $this->service = $service;
    }

    /**
     * @NoAdminRequired
     * * GET /apps/ticky_crm/api/v1/clients/{clientUuid}/addresses
     */
    public function index(string $clientUuid): DataResponse {
        try {
            $addresses = $this->service->getAddresses($clientUuid);
            return new DataResponse($addresses, Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse(['error' => 'Fehler beim Laden der Adressen'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
}