<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Note;
use App\Enum\Priority;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of POST and PUT /api/notes.
 */
final readonly class NoteInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Vyplňte název.', normalizer: 'trim')]
        #[Assert\Length(
            max: Note::TITLE_MAX_LENGTH,
            maxMessage: 'Název může mít nejvýše {{ limit }} znaků.',
            normalizer: 'trim',
        )]
        public string $title = '',

        #[Assert\Length(
            max: Note::CONTENT_MAX_LENGTH,
            maxMessage: 'Obsah může mít nejvýše {{ limit }} znaků.',
        )]
        public string $content = '',

        #[Assert\Sequentially([
            new Assert\NotBlank(message: 'Vyberte prioritu.'),
            new Assert\Choice(
                callback: [Priority::class, 'values'],
                message: 'Neplatná priorita. Povolené hodnoty: {{ choices }}.',
            ),
        ])]
        public string $priority = '',
    ) {
    }

    public function toPriority(): Priority
    {
        return Priority::from($this->priority);
    }
}
