<?php

namespace OCA\TickyCRM\Controller;

use OCA\TickyCRM\Service\ClientProductService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ClientProductController extends ApiController {
    public function __construct(string $appName, IRequest $request, private ClientProductService $service, private LoggerInterface $logger) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(string $clientUuid): DataResponse {
        return $this->respond(fn() => $this->service->all($clientUuid));
    }

    #[NoAdminRequired]
    public function create(string $clientUuid): DataResponse {
        return $this->respond(fn() => $this->service->create($clientUuid, $this->request->getParams()), Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function update(string $clientUuid, string $positionUuid): DataResponse {
        return $this->respond(fn() => $this->service->update($clientUuid, $positionUuid, $this->request->getParams()));
    }

    #[NoAdminRequired]
    public function delete(string $clientUuid, string $positionUuid): DataResponse {
        return $this->respond(function () use ($clientUuid, $positionUuid): array {
            $this->service->delete($clientUuid, $positionUuid);
            return ['success' => true];
        });
    }

    private function respond(callable $action, int $status = Http::STATUS_OK): DataResponse {
        try {
            return new DataResponse($action(), $status);
        } catch (DoesNotExistException) {
            return new DataResponse(['error' => 'client_product_not_found'], Http::STATUS_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['error' => 'product_invalid_field', 'field' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\Throwable $e) {
            $this->logger->error('Client product operation failed', ['app' => 'ticky_crm', 'exception' => $e]);
            return new DataResponse(['error' => 'client_product_error_save'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
}
