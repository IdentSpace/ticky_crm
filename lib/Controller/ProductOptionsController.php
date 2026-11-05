<?php

namespace OCA\TickyCRM\Controller;

use OCA\TickyCRM\Service\ProductOptionsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

class ProductOptionsController extends Controller {
    public function __construct(string $appName, IRequest $request, private ProductOptionsService $service, private IUserSession $session, private IGroupManager $groups) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): JSONResponse {
        $uid = $this->session->getUser()?->getUID();
        return new JSONResponse([
            'options' => $this->service->get(), 'defaults' => $this->service->defaults(),
            'can_manage' => $uid !== null && $this->groups->isAdmin($uid),
        ]);
    }

    // Nextcloud requires admin privileges by default. CSRF protection remains enabled.
    public function save(array $options): JSONResponse {
        try {
            return new JSONResponse(['options' => $this->service->save($options)]);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => 'product_options_invalid', 'field' => $e->getMessage()], 400);
        }
    }
}
