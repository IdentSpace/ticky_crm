<?php

namespace OCA\TickyCRM\Controller;

use OCA\TickyCRM\DB\ClientMapper;
use OCA\TickyCRM\DB\ProductMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

class ActivityController extends Controller {
    public function __construct(string $appName, IRequest $request, private ClientMapper $clients, private ProductMapper $products,
        private IDBConnection $db, private IFactory $l10nFactory, private LoggerInterface $logger) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function getClientActivities(string $uuid): DataResponse {
        return $this->respond(fn() => $this->load('client', $this->clients->findByUuid($uuid, false)->getId()));
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function getProductActivities(string $productUuid): DataResponse {
        return $this->respond(fn() => $this->load('product', $this->products->findByUuid($productUuid)->getId()));
    }

    private function respond(callable $action): DataResponse {
        try {
            return new DataResponse($action());
        } catch (DoesNotExistException) {
            return new DataResponse(['error' => 'activity_object_not_found'], 404);
        } catch (\Throwable $e) {
            $this->logger->error('Could not load CRM activities', ['app' => 'ticky_crm', 'exception' => $e]);
            return new DataResponse(['error' => 'activity_error_load'], 500);
        }
    }

    private function load(string $type, int $id): array {
        $query = $this->db->getQueryBuilder();
        $query->select('*')->from('activity')
            ->where($query->expr()->eq('app', $query->createNamedParameter('ticky_crm')))
            ->andWhere($query->expr()->eq('object_type', $query->createNamedParameter($type)))
            ->andWhere($query->expr()->eq('object_id', $query->createNamedParameter($id)))
            ->orderBy('timestamp', 'DESC')->addOrderBy('activity_id', 'DESC')->setMaxResults(50);
        $result = $query->executeQuery();
        $rows = $result->fetchAll();
        $result->closeCursor();
        $l10n = $this->l10nFactory->get('ticky_crm');
        return array_map(function ($row) use ($l10n): array {
            $params = json_decode($row['subjectparams'], true) ?? [];
            $replacements = [];
            foreach ($params as $key => $value) {
                if (is_scalar($value) || $value === null) $replacements['{' . $key . '}'] = (string)$value;
            }
            return [
                'id' => (int)$row['activity_id'], 'subject' => $row['subject'],
                'parsedSubject' => strtr((string)$l10n->t($row['subject']), $replacements),
                'objectName' => $params['name'] ?? null, 'timestamp' => (int)$row['timestamp'],
                'user' => $row['user'], 'parameters' => $params,
            ];
        }, $rows);
    }
}
