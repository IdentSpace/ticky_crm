<?php
namespace OCA\TickyCRM\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCA\TickyCRM\Listener\NavigationListener;
use OCA\TickyCRM\Middleware\AccessMiddleware;
use OCP\INavigationManager;

class Application extends App implements IBootstrap {
    public const APP_ID = 'ticky_crm';
    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
        $this->getContainer()->registerMiddleware(AccessMiddleware::class);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerNotifierService(\OCA\TickyCRM\Notification\Notifier::class);
    }

    public function boot(IBootContext $context): void {
        $context->injectFn(function (
            INavigationManager $navigationManager,
            NavigationListener $listener
        ) {
            if (!$listener->hasAccess()) {
                return;
            }

            $navigationManager->add(function () use ($listener) {
                return $listener->getNavigationEntry();
            });
        });
    }
}

