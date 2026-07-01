<?php

namespace OCA\TickyCRM\Service;

use OCP\Activity\IManager;
use OCP\AppFramework\Db\Entity;
use OCP\IUserSession;

class ActivityService {

    private IManager $activityManager;
    private IUserSession $userSession;

    public function __construct(IManager $activityManager, IUserSession $userSession) {
        $this->activityManager = $activityManager;
        $this->userSession = $userSession;
    }

    public function log(string $objectType, string $action, string $displayName, ?int $objectId = null, array $params = []): void {
        try {
            $user = $this->userSession->getUser();
            $userId = $user ? $user->getUID() : 'system';

            $subject = $objectType . '_' . $action;
            $subjectParams = array_merge(['name' => $displayName], $params);

            $id = $objectId ?? 0;

            $activity = $this->activityManager->generateEvent();
            $activity->setApp('ticky_crm')
                ->setType('ticky_crm_' . $objectType)
                ->setAffectedUser($userId)
                ->setSubject($subject, $subjectParams)
                ->setObject($objectType, $id, $displayName);

            $this->activityManager->publish($activity);

        } catch (\Throwable $e) {
            throw $e;
        }
    }
}