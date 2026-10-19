<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Priority;
use App\Repository\NoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NoteRepository::class)]
#[ORM\Index(name: 'idx_note_priority_updated_at', columns: ['priority', 'updated_at'])]
class Note
{
    public const int TITLE_MAX_LENGTH = 200;
    public const int CONTENT_MAX_LENGTH = 10_000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: self::TITLE_MAX_LENGTH)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, length: 65_535)]
    private string $content;

    #[ORM\Column(length: 16, enumType: Priority::class)]
    private Priority $priority;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $title, string $content, Priority $priority, \DateTimeImmutable $now)
    {
        $this->title = trim($title);
        $this->content = $content;
        $this->priority = $priority;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    /**
     * Saving an unchanged note keeps its timestamp, so it does not jump to the top of the list.
     */
    public function update(string $title, string $content, Priority $priority, \DateTimeImmutable $now): void
    {
        $title = trim($title);

        if ($title === $this->title && $content === $this->content && $priority === $this->priority) {
            return;
        }

        $this->title = $title;
        $this->content = $content;
        $this->priority = $priority;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getPriority(): Priority
    {
        return $this->priority;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
