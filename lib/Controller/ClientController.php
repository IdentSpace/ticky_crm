<?php
namespace OCA\TickyCRM\Controller;

use OCA\TickyCRM\Service\ClientContactService;
use OCA\TickyCRM\Service\ClientService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class ClientController extends ApiController {

    public function __construct(
        string $appName,
        IRequest $request,
        private ClientService $service,
        private ClientContactService $clientContactService,
        private IUserSession $userSession,
        private LoggerInterface $logger,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    #[\OCP\AppFramework\Http\Attribute\NoCSRFRequired]
    public function index(): DataResponse {
        try {
            return new DataResponse($this->service->all());
        } catch (\Throwable $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    #[NoAdminRequired]
    public function show(string $uuid): DataResponse {
        try {
            return new DataResponse($this->service->find($uuid));
        } catch (DoesNotExistException) {
            return new DataResponse([], Http::STATUS_NOT_FOUND);
        }
    }

    #[NoAdminRequired]
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

    #[NoAdminRequired]
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
            return new DataResponse(
                ['message' => 'error by during update: ' . $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[NoAdminRequired]
    public function delete(string $uuid): DataResponse {
        try {
            $this->service->delete($uuid);
            return new DataResponse(['success' => true]);
        } catch (DoesNotExistException) {
            return new DataResponse([], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * Liefert alle mit einem Kunden verknüpften Kontakte (Live-Daten aus CardDAV).
     */
    #[NoAdminRequired]
    public function getContacts(string $uuid): DataResponse {
        if ($this->userSession->getUser() === null) {
            return new DataResponse([], Http::STATUS_FORBIDDEN);
        }

        try {
            $client = $this->service->find($uuid);
        } catch (DoesNotExistException) {
            return new DataResponse([], Http::STATUS_NOT_FOUND);
        }

        try {
            $contacts = $this->clientContactService->getContactsForClient($client->getId());
        } catch (\Throwable $e) {
            $error = ['app' => 'ticky_crm', 'exception' => $e];
            $this->logger->error(
                'Ticky CRM: Kontakte für Kunde ' . $uuid . ' konnten nicht geladen werden: ' . $e->getMessage(),
                $error
            );
            return new DataResponse($error, Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        return new DataResponse($contacts);
    }

    /**
     * Verknüpft einen bestehenden Kontakt (per numerischer card_id) mit dem Kunden.
     */
    #[NoAdminRequired]
    public function linkContact(string $uuid): DataResponse {
        if ($this->userSession->getUser() === null) {
            return new DataResponse([], Http::STATUS_FORBIDDEN);
        }

        $cardId = (int)$this->request->getParam('cardId');
        if ($cardId <= 0) {
            return new DataResponse(['message' => 'cardId ist erforderlich.'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $client = $this->service->find($uuid);
        } catch (DoesNotExistException) {
            return new DataResponse([], Http::STATUS_NOT_FOUND);
        }

        try {
            $this->clientContactService->linkContactToClient($client->getId(), $cardId);
            $contacts = $this->clientContactService->getContactsForClient($client->getId());
            $linkedContact = current(array_filter($contacts, fn (array $c) => $c['id'] === $cardId)) ?: null;

            return new DataResponse($linkedContact, Http::STATUS_CREATED);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Ticky CRM: Kontakt konnte nicht verknüpft werden: ' . $e->getMessage(),
                ['app' => 'ticky_crm', 'exception' => $e]
            );
            return new DataResponse(['message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Löst die Verknüpfung eines Kontakts von diesem Kunden (löscht nicht die vCard selbst).
     */
    #[NoAdminRequired]
    public function unlinkContact(string $uuid, int $cardId): DataResponse {
        if ($this->userSession->getUser() === null) {
            return new DataResponse([], Http::STATUS_FORBIDDEN);
        }

        try {
            $client = $this->service->find($uuid);
        } catch (DoesNotExistException) {
            return new DataResponse([], Http::STATUS_NOT_FOUND);
        }

        $this->clientContactService->unlinkContactFromClient($client->getId(), $cardId);

        return new DataResponse(['success' => true]);
    }
}