<?php

namespace OCA\TickyCRM\Controller;

use InvalidArgumentException;
use OCA\TickyCRM\Service\ProductService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception as DatabaseException;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ProductController extends ApiController {
    public function __construct(string $appName, IRequest $request, private ProductService $service, private LoggerInterface $logger) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(int $limit = 100, int $offset = 0, string $query = '', bool $activeOnly = false): DataResponse {
        return $this->respond(fn() => $this->service->all($limit, $offset, $query, $activeOnly));
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function show(string $productUuid): DataResponse {
        return $this->respond(fn() => $this->service->find($productUuid));
    }

    #[NoAdminRequired]
    public function create(): DataResponse {
        return $this->respond(fn() => $this->service->create($this->request->getParams()), Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function update(string $productUuid): DataResponse {
        return $this->respond(fn() => $this->service->update($productUuid, $this->request->getParams()));
    }

    #[NoAdminRequired]
    public function delete(string $productUuid): DataResponse {
        return $this->respond(function () use ($productUuid): array {
            $this->service->delete($productUuid);
            return ['success' => true];
        });
    }

    private function respond(callable $action, int $status = Http::STATUS_OK): DataResponse {
        try {
            return new DataResponse($action(), $status);
        } catch (DoesNotExistException) {
            return new DataResponse(['error' => 'product_not_found'], Http::STATUS_NOT_FOUND);
        } catch (InvalidArgumentException $e) {
            return new DataResponse(['error' => 'product_invalid_field', 'field' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (DatabaseException $e) {
            if ($e->getReason() === DatabaseException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
                return new DataResponse(['error' => 'product_duplicate_number'], Http::STATUS_CONFLICT);
            }
            return $this->failure($e);
        } catch (\Throwable $e) {
            return $this->failure($e);
        }
    }

    private function failure(\Throwable $error): DataResponse {
        $this->logger->error('Product operation failed', ['app' => 'ticky_crm', 'exception' => $error]);
        return new DataResponse(['error' => 'product_error_save'], Http::STATUS_INTERNAL_SERVER_ERROR);
    }
}
