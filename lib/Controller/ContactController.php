<?php

namespace OCA\TickyCRM\Controller;

use OCA\DAV\CardDAV\CardDavBackend;
use OCA\TickyCRM\Service\AccessService;
use OCA\TickyCRM\Service\ClientContactService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class ContactController extends ApiController {

    private const SEARCH_PROPERTIES = ['FN', 'EMAIL', 'TEL', 'ORG', 'NICKNAME'];
    private const MIN_QUERY_LENGTH = 2;
    private const MAX_RESULTS = 15;

    public function __construct(
        string $appName,
        IRequest $request,
        private CardDavBackend $cardDavBackend,
        private AccessService $accessService,
        private ClientContactService $clientContactService,
        private IUserSession $userSession,
        private LoggerInterface $logger,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function search(string $query): DataResponse {
        if ($this->userSession->getUser() === null) {
            return new DataResponse([], 403);
        }

        if (mb_strlen(trim($query)) < self::MIN_QUERY_LENGTH) {
            return new DataResponse([]);
        }

        $addressBook = $this->accessService->ensureSystemAddressBookProperties();
        if ($addressBook === null) {
            return new DataResponse([]);
        }

        try {
            $results = $this->cardDavBackend->search(
                $addressBook['id'],
                $query,
                self::SEARCH_PROPERTIES,
                ['limit' => self::MAX_RESULTS]
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Ticky CRM: Kontaktsuche fehlgeschlagen: ' . $e->getMessage(),
                ['app' => 'ticky_crm', 'exception' => $e]
            );
            return new DataResponse([], 500);
        }

        $contacts = array_map(
            fn (array $card) => $this->clientContactService->mapSearchResultToContact($card),
            $results
        );

        return new DataResponse($contacts);
    }
}