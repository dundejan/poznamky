<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\NoteInput;
use App\Dto\NoteListQuery;
use App\Dto\NoteResponse;
use App\Entity\Note;
use App\Repository\NoteRepository;
use App\Service\NoteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notes', name: 'api_notes_', format: 'json')]
final class NoteController extends AbstractController
{
    public function __construct(
        private readonly NoteRepository $notes,
        private readonly NoteService $noteService,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        NoteListQuery $query = new NoteListQuery(),
    ): JsonResponse {
        $notes = $this->notes->findForList($query->toPriority());

        return $this->json(['data' => array_map(NoteResponse::fromEntity(...), $notes)]);
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(Note $note): JsonResponse
    {
        return $this->json(NoteResponse::fromEntity($note));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload(acceptFormat: 'json')] NoteInput $input): JsonResponse
    {
        $note = $this->noteService->create($input);

        return $this->json(
            NoteResponse::fromEntity($note),
            Response::HTTP_CREATED,
            ['Location' => $this->generateUrl('api_notes_show', ['id' => $note->getId()])],
        );
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    public function update(Note $note, #[MapRequestPayload(acceptFormat: 'json')] NoteInput $input): JsonResponse
    {
        $this->noteService->update($note, $input);

        return $this->json(NoteResponse::fromEntity($note));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(Note $note): Response
    {
        $this->noteService->delete($note);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
