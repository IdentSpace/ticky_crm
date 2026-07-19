<?php
namespace OCA\TickyCRM\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCA\TickyCRM\Service\AccessService;
use OCP\IUserSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;

class PageController extends Controller {

    public function __construct(
        string $appName,
        IRequest $request,
        private AccessService $accessService,
        private IUserSession $userSession,
        private IURLGenerator $urlGenerator
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse|RedirectResponse  {
        $user = $this->userSession->getUser();
        $uid  = $user?->getUID();

        if (!$this->accessService->canAccess($uid)) {
            return new RedirectResponse(
                $this->urlGenerator->linkToRoute('core.PageController.defaultPage')
            );
        }
        return new TemplateResponse($this->appName, 'main');
    }
}