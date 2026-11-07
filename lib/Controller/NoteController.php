<?php
namespace OCA\TickyCRM\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IRequest;
use OCP\IUserSession;
use OCA\TickyCRM\Service\NoteService;
use Psr\Log\LoggerInterface;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

class NoteController extends Controller {

    public function __construct(
        string $appName,
        IRequest $request,
        private NoteService $service,
        private \OCA\TickyCRM\Service\ClientService $clientService,
        private IUserSession $userSession,
        private LoggerInterface $logger
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function index(int $clientId): JSONResponse {
        return new JSONResponse($this->service->findAll($clientId));
    }

    #[NoAdminRequired]
    public function create(int $clientId, string $content, string $title = '', string $type = 'note', ?array $contact_update = null): JSONResponse {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $note = $this->service->create($clientId, $user->getUID(), $content, $title, $type, $contact_update);
            $data = $note->jsonSerialize();
            if ($contact_update !== null) {
                $client = $this->clientService->present($this->clientService->findById($clientId));
                $data['client'] = array_intersect_key($client, array_flip(['id', 'uuid', 'last_contact_date', 'follow_up_mode', 'follow_up_manual', 'follow_up_status', 'updated_at']));
            }
            return new JSONResponse($data, Http::STATUS_CREATED);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'note_invalid_field', 'field' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (DoesNotExistException) {
            return new JSONResponse(['error' => 'Client not found'], Http::STATUS_NOT_FOUND);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create note: {error}', [
                'error' => $e->getMessage(),
                'app'   => 'ticky_crm',
            ]);
            return new JSONResponse(['error' => 'Internal Server Error'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    #[NoAdminRequired]
    public function update(int $id, string $content, string $title = '', string $type = 'note'): JSONResponse {
        try {
            $user = $this->userSession->getUser();
            $editorId = $user ? $user->getUID() : '';
            $updatedNote = $this->service->update($id, $content, $title, $type, $editorId);
            return new JSONResponse($updatedNote);
        } catch (DoesNotExistException) {
            return new JSONResponse(['error' => 'Note not found'], Http::STATUS_NOT_FOUND);
        }
    }

    #[NoAdminRequired]
    public function destroy(int $id): JSONResponse {
        try {
            $this->service->delete($id);
            return new JSONResponse(['success' => true]);
        } catch (DoesNotExistException) {
            return new JSONResponse(['error' => 'Note not found'], Http::STATUS_NOT_FOUND);
        }
    }
}