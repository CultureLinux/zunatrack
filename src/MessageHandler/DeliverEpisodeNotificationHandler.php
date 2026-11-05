<?php
declare(strict_types=1);
namespace App\MessageHandler;
use App\Entity\PushDelivery;use App\Message\DeliverEpisodeNotification;use App\Service\EpisodePushPayload;use App\Service\WebPushDelivery;use Doctrine\ORM\EntityManagerInterface;use Symfony\Component\Messenger\Attribute\AsMessageHandler;
#[AsMessageHandler] final readonly class DeliverEpisodeNotificationHandler {public function __construct(private EntityManagerInterface $em,private WebPushDelivery $push){} public function __invoke(DeliverEpisodeNotification $message):void{$delivery=$this->em->find(PushDelivery::class,$message->deliveryId);if(!$delivery instanceof PushDelivery)return;$episode=$delivery->getEpisode();if($this->push->send($delivery->getSubscription(),EpisodePushPayload::forEpisode($episode))){$delivery->markDelivered();$this->em->flush();}}}
