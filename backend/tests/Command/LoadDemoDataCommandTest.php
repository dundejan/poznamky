<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Repository\NoteRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class LoadDemoDataCommandTest extends KernelTestCase
{
    public function testLoadsNotesOnlyIntoEmptyDatabase(): void
    {
        $tester = new CommandTester((new Application(self::bootKernel()))->find('app:load-demo-data'));
        $notes = self::getContainer()->get(NoteRepository::class);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(6, $notes->count());

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(6, $notes->count(), 'A second run does not add duplicates.');
        self::assertStringContainsString('skipping', $tester->getDisplay());
    }
}
