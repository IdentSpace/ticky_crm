<?php
namespace OCA\TickyCRM\Service;

use OCA\TickyCRM\DB\ClientNote;
use OCA\TickyCRM\DB\ClientNoteMapper;
use OCP\IURLGenerator;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;
use DateTime;
use Exception;


class NoteService {

    public function __construct(
        private ClientNoteMapper $mapper,
        private INotificationManager $notificationManager,
        private LoggerInterface $logger,
        private IURLGenerator $urlGenerator,
        private \OCP\IDBConnection $db,
        private ClientService $clientService
    ) {}

    public function findAll(int $clientId): array {
        return $this->mapper->findAllByClientId($clientId);
    }

    public function create(int $clientId, string $userId, string $content, string $title = '', string $type = 'note', ?array $contactUpdate = null): ClientNote {
        if (trim($content) === '' || !in_array($type, ['note', 'phone', 'meeting'], true)) {
            throw new \InvalidArgumentException('note');
        }
        $note = new ClientNote();
        $note->setClientId($clientId);
        $note->setUserId($userId);
        $note->setType($type);
        $note->setTitle($title);
        $note->setContent($content);
        $note->setCreatedAt(new DateTime());
        $note->setUpdatedAt(new DateTime());

        $this->db->beginTransaction();
        try {
            $this->clientService->findById($clientId);
            if ($contactUpdate !== null) $this->clientService->updateFollowUp($clientId, $contactUpdate);
            $savedNote = $this->mapper->insert($note);
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }

        $this->notifyMentions($savedNote, $this->extractMentions($content), $userId);

        return $savedNote;
    }

    public function update(int $id, string $content, string $title = '', string $type = 'note', string $editorId = ''): ClientNote {
        $note = $this->mapper->findById($id);
        $oldMentions = $this->extractMentions($note->getContent());

        $note->setContent($content);
        $note->setTitle($title);
        $note->setType($type);
        $note->setUpdatedAt(new DateTime());

        $updatedNote = $this->mapper->update($note);

        $senderId = $editorId ?: $updatedNote->getUserId();
        $newMentions = array_diff($this->extractMentions($content), $oldMentions);
        $this->notifyMentions($updatedNote, $newMentions, $senderId);

        return $updatedNote;
    }

    public function delete(int $id): void {
        $this->mapper->delete($this->mapper->findById($id));
    }

    private function extractMentions(string $content): array {
        if (!preg_match_all('/\[@(.*?)\]/', $content, $matches)) {
            return [];
        }
        return array_unique($matches[1]);
    }

    private function notifyMentions(ClientNote $note, array $userIds, string $senderId): void {
        foreach ($userIds as $userId) {
            if ($userId === $senderId) {
                continue;
            }

            try {
                $notification = $this->notificationManager->createNotification();
                $notification
                    ->setApp('ticky_crm')
                    ->setUser($userId)
                    ->setDateTime(new DateTime())
                    ->setSubject('ticky_mention_subject', ['sender' => $senderId])
                    ->setObject('note', (string)$note->getId())
                    ->setLink($this->urlGenerator->getAbsoluteURL(
                        $this->urlGenerator->linkToRoute('ticky_crm.page.index') . '?client=' . $note->getClientId() . '&tab=notes'
                    ));

                $this->notificationManager->notify($notification);
            } catch (Exception $e) {
                $this->logger->error('Failed to send mention notification to {user}: {error}', [
                    'user'  => $userId,
                    'error' => $e->getMessage(),
                    'app'   => 'ticky_crm',
                ]);
            }
        }
    }
}