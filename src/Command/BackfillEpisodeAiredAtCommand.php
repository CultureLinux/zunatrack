<?php

namespace App\Command;

use App\Entity\CatalogSource;
use App\Entity\Episode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:catalog:backfill-air-datetimes', description: 'Backfill episode air timestamps from local TVmaze snapshots.')]
final class BackfillEpisodeAiredAtCommand extends Command
{
    public function __construct(private EntityManagerInterface $em) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $updated = 0;
        foreach ($this->em->getRepository(CatalogSource::class)->findAll() as $source) {
            $episodes = [];
            foreach ($source->getSeries()->getSeasons() as $season) foreach ($season->getEpisodes() as $episode) $episodes[$season->getNumber().':'.$episode->getNumber()] = $episode;
            foreach (($source->getPayload()['_embedded']['episodes'] ?? []) as $row) {
                $stamp = $row['airstamp'] ?? null;
                $episode = $episodes[((int) ($row['season'] ?? -1)).':'.((int) ($row['number'] ?? -1))] ?? null;
                if (!$episode instanceof Episode || !is_string($stamp) || $stamp === '') continue;
                $date = new \DateTimeImmutable($stamp);
                if ($episode->getAiredAt()?->format(DATE_ATOM) === $date->format(DATE_ATOM)) continue;
                $episode->setAiredAt($date); ++$updated;
            }
        }
        $this->em->flush();
        $output->writeln(sprintf('%d épisode(s) horodaté(s).', $updated));
        return Command::SUCCESS;
    }
}
