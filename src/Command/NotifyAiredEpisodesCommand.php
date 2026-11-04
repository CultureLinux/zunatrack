<?php
declare(strict_types=1);
namespace App\Command;
use App\Service\EpisodeNotificationScheduler;use Symfony\Component\Console\Attribute\AsCommand;use Symfony\Component\Console\Command\Command;use Symfony\Component\Console\Input\InputInterface;use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'app:notify-aired-episodes',description:'Queue Web Push notifications for newly aired episodes.')]
final class NotifyAiredEpisodesCommand extends Command {public function __construct(private readonly EpisodeNotificationScheduler $scheduler){parent::__construct();}protected function execute(InputInterface $input,OutputInterface $output):int{$count=$this->scheduler->queueEligible(new \DateTimeImmutable());$output->writeln(sprintf('%d notification(s) queued.',$count));return Command::SUCCESS;}}
