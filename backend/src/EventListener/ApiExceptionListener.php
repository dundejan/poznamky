<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Turns every error under /api into one JSON shape:
 * {"error": {"code": "...", "message": "...", "fields": {"title": ["..."]}}}.
 *
 * Runs after Symfony's own listener has logged the exception (priority 0)
 * and before it renders the default error page (priority -128).
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -64)]
final readonly class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();
        $isHttp = $exception instanceof HttpExceptionInterface;
        $status = $isHttp ? $exception->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR;
        $previous = $exception->getPrevious();

        $error = match (true) {
            $previous instanceof ValidationFailedException => [
                'code' => 'validation_failed',
                'message' => 'Zkontrolujte zadané údaje.',
                'fields' => self::fieldErrors($previous->getViolations()),
            ],
            $previous instanceof NotEncodableValueException => [
                'code' => 'invalid_json',
                'message' => 'Tělo požadavku není platný JSON.',
            ],
            default => self::errorForStatus($status),
        };

        $event->setResponse(new JsonResponse(['error' => $error], $status, $isHttp ? $exception->getHeaders() : []));
    }

    /**
     * @return array<string, list<string>>
     */
    private static function fieldErrors(ConstraintViolationListInterface $violations): array
    {
        $fields = [];
        foreach ($violations as $violation) {
            $fields[$violation->getPropertyPath()][] = (string) $violation->getMessage();
        }

        return $fields;
    }

    /**
     * @return array{code: string, message: string}
     */
    private static function errorForStatus(int $status): array
    {
        return match ($status) {
            Response::HTTP_BAD_REQUEST => ['code' => 'bad_request', 'message' => 'Neplatný požadavek.'],
            Response::HTTP_NOT_FOUND => ['code' => 'not_found', 'message' => 'Záznam nebyl nalezen.'],
            Response::HTTP_METHOD_NOT_ALLOWED => ['code' => 'method_not_allowed', 'message' => 'Tato metoda není pro danou adresu povolená.'],
            Response::HTTP_UNSUPPORTED_MEDIA_TYPE => ['code' => 'unsupported_media_type', 'message' => 'Pošlete data jako JSON (Content-Type: application/json).'],
            default => $status >= 500
                ? ['code' => 'internal_error', 'message' => 'Na serveru došlo k chybě. Zkuste to prosím znovu.']
                : ['code' => 'http_error', 'message' => 'Požadavek nelze zpracovat.'],
        };
    }
}
