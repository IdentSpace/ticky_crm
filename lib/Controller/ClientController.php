<?php
namespace OCA\TickyCRM\Controller;

use OCA\TickyCRM\Service\ClientService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class ClientController extends ApiController {

    public function __construct(
        string $appName,
        IRequest $request,
        private ClientService $service
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): DataResponse {
        try {
            return new DataResponse($this->service->all());
        } catch (\Throwable $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function show(string $uuid): DataResponse {
        try {
            return new DataResponse($this->service->find($uuid));
        } catch (DoesNotExistException) {
            return new DataResponse([], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function create(): DataResponse {
        try {
            $client = $this->service->create($this->request->getParams());
            return new DataResponse($client, Http::STATUS_CREATED);
        } catch (\Throwable $e) {
            if ($e->getCode() === 23000 || str_contains($e->getMessage(), '1062')) {
                return new DataResponse([
                    'error'   => 'duplicate_client_number',
                    'message' => 'Diese Kundennummer wird bereits verwendet.',
                ], Http::STATUS_CONFLICT);
            }
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function update(string $uuid): DataResponse {
        try {
            return new DataResponse($this->service->update($uuid, $this->request->getParams()));
        } catch (DoesNotExistException) {
            return new DataResponse(['error' => 'Kunde nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function delete(string $uuid): DataResponse {
        try {
            $this->service->delete($uuid);
            return new DataResponse(['success' => true]);
        } catch (DoesNotExistException) {
            return new DataResponse([], Http::STATUS_NOT_FOUND);
        }
    }
}