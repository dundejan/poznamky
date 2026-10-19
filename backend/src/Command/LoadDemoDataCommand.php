<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Note;
use App\Enum\Priority;
use App\Repository\NoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:demo-data', description: 'Loads sample notes into an empty database.')]
final readonly class LoadDemoDataCommand
{
    /**
     * Title, content, priority and age in hours (gives the list a realistic order).
     */
    private const array NOTES = [
        ['Objednat se na preventivní prohlídku', "Praktický lékař, poslední prohlídka byla před dvěma lety.\nVolat ráno mezi 7 a 9.", Priority::High, 2],
        ['Vyzvednout výsledky krve', 'Laboratoř na Karlově náměstí, otevřeno do 15:00.', Priority::High, 30],
        ['Připomenout očkování proti klíšťové encefalitidě', 'Třetí dávka, nejpozději do konce listopadu.', Priority::Medium, 5],
        ['Nákup do lékárničky', "Náplasti\nIbuprofen\nDezinfekce\nTeploměr (starý nefunguje)", Priority::Medium, 48],
        ['Zjistit, jestli pojišťovna proplácí brýle', '', Priority::Low, 12],
        ['Nápady na víkend', 'Výlet na Šumavu, nebo aspoň dlouhá procházka v Krči.', Priority::Low, 72],
    ];

    public function __construct(
        private NoteRepository $notes,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        if ($this->notes->count() > 0) {
            $io->note('The database already contains notes, skipping demo data.');

            return Command::SUCCESS;
        }

        $now = $this->clock->now();
        foreach (self::NOTES as [$title, $content, $priority, $hoursAgo]) {
            $this->entityManager->persist(new Note($title, $content, $priority, $now->modify(sprintf('-%d hours', $hoursAgo))));
        }
        $this->entityManager->flush();

        $io->success(sprintf('Loaded %d demo notes.', \count(self::NOTES)));

        return Command::SUCCESS;
    }
}
