<?php
namespace OCA\TickyCRM\Listener;

use OCP\IURLGenerator;
use OCP\IUserSession;
use OCA\TickyCRM\Service\AccessService;

class NavigationListener {

    public function __construct(
        private IUserSession $userSession,
        private AccessService $accessService,
        private IURLGenerator $urlGenerator
    ) {}

    public function hasAccess(): bool {
        $user = $this->userSession->getUser();
        return $this->accessService->canAccess($user?->getUID());
    }

    public function getNavigationEntry(): array {
        try {
            $icon = $this->urlGenerator->imagePath('ticky_crm', 'ticky.svg');
        } catch (\RuntimeException $e) {
            $icon = $this->urlGenerator->imagePath('core', 'places/default-app-icon.svg');
        }

        return [
            'id'    => 'ticky_crm',
            'order' => 10,
            'href'  => $this->urlGenerator->linkToRoute('ticky_crm.page.index'),
            'icon'  => $icon,
            'name'  => 'Ticky CRM',
            'app'   => 'ticky_crm', // Pflichtfeld damit isAlwaysEnabled() nicht null bekommt
        ];
    }
}