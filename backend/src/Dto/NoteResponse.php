<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Note;
use App\Enum\Priority;

/**
 * Public JSON shape of a note; keeps the entity out of the API contract.
 */
final readonly class NoteResponse
{
    public function __construct(
        public int $id,
        public string $title,
        public string $content,
        public Priority $priority,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromEntity(Note $note): self
    {
        return new self(
            id: $note->getId() ?? throw new \LogicException('Only a persisted note has an id.'),
            title: $note->getTitle(),
            content: $note->getContent(),
            priority: $note->getPriority(),
            createdAt: $note->getCreatedAt(),
            updatedAt: $note->getUpdatedAt(),
        );
    }
}
