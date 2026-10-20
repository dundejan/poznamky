<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\Priority;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query string of GET /api/notes, e.g. ?priority=high.
 */
final readonly class NoteListQuery
{
    public function __construct(
        #[Assert\Choice(
            callback: [Priority::class, 'values'],
            message: 'Neplatná priorita. Povolené hodnoty: {{ choices }}.',
        )]
        public ?string $priority = null,
    ) {
    }

    public function toPriority(): ?Priority
    {
        return null === $this->priority ? null : Priority::from($this->priority);
    }
}
