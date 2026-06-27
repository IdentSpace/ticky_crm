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

class NoteController extends Controller {

    public function __construct(
        string $appName,
        IRequest $request,
        private NoteService $service,
        private IUserSession $userSession,
        private LoggerInterface $logger
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     */
    public function index(int $clientId): JSONResponse {
        return new JSONResponse($this->service->findAll($clientId));
    }

    /**
     * @NoAdminRequired
     */
    public function create(int $clientId, string $content, string $title = '', string $type = 'note'): JSONResponse {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $note = $this->service->create($clientId, $user->getUID(), $content, $title, $type);
            return new JSONResponse($note, Http::STATUS_CREATED);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create note: {error}', [
                'error' => $e->getMessage(),
                'app'   => 'ticky_crm',
            ]);
            return new JSONResponse(['error' => 'Internal Server Error'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @NoAdminRequired
     */
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

    /**
     * @NoAdminRequired
     */
    public function destroy(int $id): JSONResponse {
        try {
            $this->service->delete($id);
            return new JSONResponse(['success' => true]);
        } catch (DoesNotExistException) {
            return new JSONResponse(['error' => 'Note not found'], Http::STATUS_NOT_FOUND);
        }
    }
}