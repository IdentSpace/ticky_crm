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
            $params = $this->request->getParams();
            $addresses = $this->request->getParam('addresses', []);
            $params['addresses'] = $addresses;
            $client = $this->service->create($params);
            return new DataResponse($client, Http::STATUS_CREATED);
        } catch (\Throwable $e) {
            $previous = $e->getPrevious();
            $errorMessage = $previous ? $previous->getMessage() : $e->getMessage();
            $errorCode = $previous ? $previous->getCode() : $e->getCode();

            if ($errorCode === 23000 || str_contains($errorMessage, '1062')) {
                return new DataResponse([
                    'error'   => 'duplicate_client_number',
                    'message' => 'Clientnumber exist already.',
                ], Http::STATUS_CONFLICT);
            }
            return new DataResponse(['error' => $errorMessage, 'message' => "Systemfehler"], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function update(string $uuid): DataResponse {
        try {
            $params = $this->request->getParams();
            $addresses = $this->request->getParam('addresses', null);
            if ($addresses !== null) {
                $params['addresses'] = $addresses;
            }
            return new DataResponse($this->service->update($uuid, $params));
        } catch (DoesNotExistException $e) {
            return new DataResponse(
                ['message' => 'client not found.'],
                Http::STATUS_NOT_FOUND
            );
        } catch (\Throwable $e) {
            // Hier fangen wir JEDEN Fehler (auch SQL/DBAL) ab und zwingen Nextcloud,
            // eine saubere JSON-DataResponse mit Status 500 zu schicken.
            return new DataResponse(
                ['message' => 'error by during update: ' . $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
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