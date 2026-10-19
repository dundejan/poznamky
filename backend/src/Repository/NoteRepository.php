<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Note;
use App\Enum\Priority;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Note>
 */
class NoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Note::class);
    }

    /**
     * Notes ordered by priority (high first), then by the most recent change.
     *
     * @return list<Note>
     */
    public function findForList(?Priority $priority = null): array
    {
        $qb = $this->createQueryBuilder('n');

        if (null === $priority) {
            $qb->addSelect(sprintf('%s AS HIDDEN priorityRank', self::priorityRankExpression()))
                ->orderBy('priorityRank', 'ASC');
        } else {
            // With a single priority the rank is constant; leaving it out lets the
            // (priority, updated_at) index serve the ORDER BY without a filesort.
            $qb->andWhere('n.priority = :priority')->setParameter('priority', $priority);
        }

        $qb->addOrderBy('n.updatedAt', 'DESC')
            ->addOrderBy('n.id', 'DESC');

        /** @var list<Note> $notes */
        $notes = $qb->getQuery()->getResult();

        return $notes;
    }

    /**
     * Priority is stored as a readable string, so alphabetical order would be wrong.
     * The ranks come from the enum, which keeps a single source of truth.
     */
    private static function priorityRankExpression(): string
    {
        $whens = array_map(
            static fn (Priority $p): string => sprintf("WHEN '%s' THEN %d", $p->value, $p->sortRank()),
            Priority::cases(),
        );

        return sprintf('CASE n.priority %s ELSE 99 END', implode(' ', $whens));
    }
}
