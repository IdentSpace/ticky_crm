<?php
namespace OCA\TickyCRM\Notification;

use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {

    public function __construct(
        private IFactory $l10nFactory,
        private IURLGenerator $urlGenerator
    ) {}

    public function getID(): string {
        return 'ticky_crm';
    }

    public function getName(): string {
        return 'Ticky CRM';
    }

    public function prepare(INotification $notification, string $languageCode): INotification {
        if ($notification->getApp() !== 'ticky_crm') {
            throw new UnknownNotificationException();
        }

        $l = $this->l10nFactory->get('ticky_crm', $languageCode);

        if ($notification->getSubject() === 'ticky_mention_subject') {
            $params = $notification->getSubjectParameters();
            $translated = $l->t('ticky_mention_subject');
            $parsed = str_replace('{sender}', $params['sender'], $translated);
            $notification->setParsedSubject($parsed);
            return $notification;
        }

        throw new UnknownNotificationException();
    }
}