<?php

declare(strict_types=1);

namespace App\Tests\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Runs against the MariaDB test database; DAMA rolls back each test.
 */
final class NoteApiTest extends WebTestCase
{
    use ClockSensitiveTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        self::mockTime('2026-10-01 12:00:00');
    }

    // --- list ------------------------------------------------------------------

    public function testListIsEmptyWithoutNotes(): void
    {
        $this->request('GET', '/api/notes');

        self::assertResponseIsSuccessful();
        self::assertSame(['data' => []], $this->responseJson());
    }

    public function testListIsSortedByPriorityThenByLastChange(): void
    {
        $this->createNote('Old low', 'low');
        $this->createNote('Old high', 'high');
        self::mockTime('+1 hour');
        $this->createNote('New medium', 'medium');
        $this->createNote('New high', 'high');

        $this->request('GET', '/api/notes');

        self::assertSame(['New high', 'Old high', 'New medium', 'Old low'], $this->listedTitles());
    }

    public function testListCanBeFilteredByPriority(): void
    {
        $this->createNote('A', 'low');
        $this->createNote('B', 'high');
        $this->createNote('C', 'low');

        $this->request('GET', '/api/notes?priority=low');

        self::assertResponseIsSuccessful();
        self::assertSame(['C', 'A'], $this->listedTitles());
    }

    public function testListRejectsUnknownPriorityFilter(): void
    {
        $this->request('GET', '/api/notes?priority=urgent');

        self::assertResponseStatusCodeSame(400);
        $error = $this->responseError();
        self::assertSame('validation_failed', $error['code']);
        self::assertArrayHasKey('priority', $error['fields'] ?? []);
    }

    // --- show ------------------------------------------------------------------

    public function testShowReturnsNote(): void
    {
        $id = $this->createNote('Detail', 'medium', 'Obsah poznámky');

        $this->request('GET', "/api/notes/$id");

        self::assertResponseIsSuccessful();
        self::assertSame([
            'id' => $id,
            'title' => 'Detail',
            'content' => 'Obsah poznámky',
            'priority' => 'medium',
            'createdAt' => '2026-10-01T12:00:00+00:00',
            'updatedAt' => '2026-10-01T12:00:00+00:00',
        ], $this->responseJson());
    }

    public function testShowReturns404ForMissingNote(): void
    {
        $this->request('GET', '/api/notes/999999');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('not_found', $this->responseError()['code']);
    }

    // --- create ----------------------------------------------------------------

    public function testCreateReturnsNoteWithLocation(): void
    {
        $this->request('POST', '/api/notes', ['title' => '  Nákup  ', 'content' => 'Mléko', 'priority' => 'high']);

        self::assertResponseStatusCodeSame(201);
        $note = $this->responseNote();
        self::assertResponseHeaderSame('Location', '/api/notes/'.$note['id']);
        self::assertSame('Nákup', $note['title'], 'Title is trimmed.');
        self::assertSame('high', $note['priority']);
    }

    public function testCreateAllowsEmptyContent(): void
    {
        $this->request('POST', '/api/notes', ['title' => 'Jen název', 'priority' => 'low']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('', $this->responseNote()['content']);
    }

    public function testLengthLimitCountsCharactersNotBytes(): void
    {
        $this->request('POST', '/api/notes', [
            'title' => str_repeat('ř', 200),
            'content' => str_repeat('😀', 10_000),
            'priority' => 'low',
        ]);

        self::assertResponseStatusCodeSame(201);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPayloads')]
    public function testCreateRejectsInvalidInput(array $payload, string $field): void
    {
        $this->request('POST', '/api/notes', $payload);

        self::assertResponseStatusCodeSame(422);
        $error = $this->responseError();
        self::assertSame('validation_failed', $error['code']);
        self::assertArrayHasKey($field, $error['fields'] ?? []);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'missing title' => [['priority' => 'low'], 'title'];
        yield 'blank title' => [['title' => '   ', 'priority' => 'low'], 'title'];
        yield 'title too long' => [['title' => str_repeat('a', 201), 'priority' => 'low'], 'title'];
        yield 'title not a string' => [['title' => 42, 'priority' => 'low'], 'title'];
        yield 'content too long' => [['title' => 'A', 'content' => str_repeat('a', 10_001), 'priority' => 'low'], 'content'];
        yield 'missing priority' => [['title' => 'A'], 'priority'];
        yield 'unknown priority' => [['title' => 'A', 'priority' => 'urgent'], 'priority'];
    }

    public function testCreateRejectsJsonThatIsNotAnObject(): void
    {
        $this->sendRaw('POST', '/api/notes', '"just a string"');

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('title', $this->responseError()['fields'] ?? []);
    }

    public function testCreateRejectsMalformedJson(): void
    {
        $this->sendRaw('POST', '/api/notes', '{"title": "A",');

        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_json', $this->responseError()['code']);
    }

    public function testCreateRejectsEmptyBody(): void
    {
        $this->sendRaw('POST', '/api/notes', '');

        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_json', $this->responseError()['code']);
    }

    public function testCreateRejectsNonJsonBody(): void
    {
        $this->client->request('POST', '/api/notes', ['title' => 'A', 'priority' => 'low']);

        self::assertResponseStatusCodeSame(415);
        self::assertSame('unsupported_media_type', $this->responseError()['code']);
    }

    // --- update ----------------------------------------------------------------

    public function testUpdateReplacesNoteAndMovesItUp(): void
    {
        $first = $this->createNote('First', 'medium');
        $this->createNote('Second', 'medium');
        self::mockTime('+10 minutes');

        $this->request('PUT', "/api/notes/$first", ['title' => 'First edited', 'content' => 'Nový obsah', 'priority' => 'medium']);

        self::assertResponseIsSuccessful();
        $note = $this->responseNote();
        self::assertSame('2026-10-01T12:00:00+00:00', $note['createdAt']);
        self::assertSame('2026-10-01T12:10:00+00:00', $note['updatedAt']);

        $this->request('GET', '/api/notes');
        self::assertSame(['First edited', 'Second'], $this->listedTitles());
    }

    public function testUpdateWithoutContentClearsIt(): void
    {
        $id = $this->createNote('A', 'low', 'Text');

        $this->request('PUT', "/api/notes/$id", ['title' => 'A', 'priority' => 'low']);

        self::assertResponseIsSuccessful();
        self::assertSame('', $this->responseNote()['content'], 'PUT replaces the whole note.');
    }

    public function testUpdateWithoutChangesKeepsTimestamp(): void
    {
        $id = $this->createNote('Same', 'low', 'Text');
        self::mockTime('+10 minutes');

        $this->request('PUT', "/api/notes/$id", ['title' => '  Same  ', 'content' => 'Text', 'priority' => 'low']);

        self::assertResponseIsSuccessful();
        self::assertSame('2026-10-01T12:00:00+00:00', $this->responseNote()['updatedAt']);
    }

    public function testUpdateValidatesInput(): void
    {
        $id = $this->createNote('Valid', 'low');

        $this->request('PUT', "/api/notes/$id", ['title' => '', 'priority' => 'low']);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('title', $this->responseError()['fields'] ?? []);
    }

    public function testUpdateReturns404ForMissingNote(): void
    {
        $this->request('PUT', '/api/notes/999999', ['title' => 'A', 'priority' => 'low']);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('not_found', $this->responseError()['code']);
    }

    public function testPatchIsNotSupported(): void
    {
        $id = $this->createNote('A', 'low');

        $this->request('PATCH', "/api/notes/$id", ['title' => 'B']);

        self::assertResponseStatusCodeSame(405);
        self::assertSame('method_not_allowed', $this->responseError()['code']);
    }

    // --- delete ----------------------------------------------------------------

    public function testDeleteRemovesNote(): void
    {
        $id = $this->createNote('To delete', 'low');

        $this->request('DELETE', "/api/notes/$id");
        self::assertResponseStatusCodeSame(204);

        $this->request('GET', "/api/notes/$id");
        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteReturns404ForMissingNote(): void
    {
        $this->request('DELETE', '/api/notes/999999');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('not_found', $this->responseError()['code']);
    }

    // --- helpers ---------------------------------------------------------------

    /**
     * @param array<string, mixed>|null $body
     */
    private function request(string $method, string $uri, ?array $body = null): void
    {
        $this->sendRaw($method, $uri, null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR));
    }

    private function sendRaw(string $method, string $uri, ?string $content): void
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: $content,
        );
    }

    private function createNote(string $title, string $priority, string $content = ''): int
    {
        $this->request('POST', '/api/notes', ['title' => $title, 'content' => $content, 'priority' => $priority]);
        self::assertResponseStatusCodeSame(201);

        return $this->responseNote()['id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseJson(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @return array{id: int, title: string, content: string, priority: string, createdAt: string, updatedAt: string}
     */
    private function responseNote(): array
    {
        /** @var array{id: int, title: string, content: string, priority: string, createdAt: string, updatedAt: string} $note */
        $note = $this->responseJson();

        return $note;
    }

    /**
     * @return array{code: string, message: string, fields?: array<string, list<string>>}
     */
    private function responseError(): array
    {
        $data = $this->responseJson();
        self::assertArrayHasKey('error', $data);

        /** @var array{code: string, message: string, fields?: array<string, list<string>>} $error */
        $error = $data['error'];

        return $error;
    }

    /**
     * @return list<string>
     */
    private function listedTitles(): array
    {
        /** @var array{data: list<array{title: string}>} $list */
        $list = $this->responseJson();

        return array_column($list['data'], 'title');
    }
}
