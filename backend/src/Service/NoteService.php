<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\NoteInput;
use App\Entity\Note;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Write side of notes. Input is already validated by the controller layer.
 */
final readonly class NoteService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function create(NoteInput $input): Note
    {
        $note = new Note($input->title, $input->content, $input->priority(), $this->clock->now());

        $this->entityManager->persist($note);
        $this->entityManager->flush();

        return $note;
    }

    public function update(Note $note, NoteInput $input): Note
    {
        $note->update($input->title, $input->content, $input->priority(), $this->clock->now());
        $this->entityManager->flush();

        return $note;
    }

    public function delete(Note $note): void
    {
        $this->entityManager->remove($note);
        $this->entityManager->flush();
    }
}
