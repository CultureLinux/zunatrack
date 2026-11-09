<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

final readonly class WebPushDelivery
{
    public function __construct(private InstanceConfigurationService $configuration)
    {
    }

    /** @param array{title:string,body:string,url:string,tag:string,icon:?string,image:?string} $payload */
    public function send(PushSubscription $subscription, array $payload): bool
    {
        $settings = $this->configuration->current();
        $private = $this->configuration->privateVapidKey();
        if (!PushEndpoint::isAllowed($subscription->getEndpoint()) || !$settings->getVapidSubject() || !$settings->getVapidPublicKey() || !$private) {
            return false;
        }$push = new WebPush(['VAPID' => ['subject' => $settings->getVapidSubject(), 'publicKey' => $settings->getVapidPublicKey(), 'privateKey' => $private]]);
        $report = $push->sendOneNotification(Subscription::create(['endpoint' => $subscription->getEndpoint(), 'publicKey' => $subscription->getP256dh(), 'authToken' => $subscription->getAuth()]), json_encode($payload, JSON_THROW_ON_ERROR));

        return $report->isSuccess();
    }
}
