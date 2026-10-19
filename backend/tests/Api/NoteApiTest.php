<?php

declare(strict_types=1);

namespace App\Tests\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Functional tests against a real MariaDB test database.
 * Every test runs in a transaction that is rolled back afterwards (DAMA DoctrineTestBundle).
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

    public function testListIsEmptyWithoutNotes(): void
    {
        $this->request('GET', '/api/notes');

        self::assertResponseIsSuccessful();
        self::assertSame(['data' => []], $this->json());
    }

    public function testListIsSortedByPriorityThenByLastChange(): void
    {
        $this->createNote('Old low', 'low');
        $this->createNote('Old high', 'high');
        self::mockTime('+1 hour');
        $this->createNote('New medium', 'medium');
        $this->createNote('New high', 'high');

        $this->request('GET', '/api/notes');

        self::assertSame(['New high', 'Old high', 'New medium', 'Old low'], $this->titles());
    }

    public function testListCanBeFilteredByPriority(): void
    {
        $this->createNote('A', 'low');
        $this->createNote('B', 'high');
        $this->createNote('C', 'low');

        $this->request('GET', '/api/notes?priority=low');

        self::assertResponseIsSuccessful();
        self::assertSame(['C', 'A'], $this->titles());
    }

    public function testListRejectsUnknownPriorityFilter(): void
    {
        $this->request('GET', '/api/notes?priority=urgent');

        self::assertResponseStatusCodeSame(400);
        self::assertSame('validation_failed', $this->error()['code']);
        self::assertArrayHasKey('priority', $this->error()['fields'] ?? []);
    }

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
        ], $this->json());
    }

    public function testShowReturns404ForMissingNote(): void
    {
        $this->request('GET', '/api/notes/999999');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('not_found', $this->error()['code']);
    }

    public function testCreateReturnsNoteWithLocation(): void
    {
        $this->request('POST', '/api/notes', ['title' => '  Nákup  ', 'content' => 'Mléko', 'priority' => 'high']);

        self::assertResponseStatusCodeSame(201);
        $note = $this->note();
        self::assertResponseHeaderSame('Location', '/api/notes/'.$note['id']);
        self::assertSame('Nákup', $note['title'], 'Title is trimmed.');
        self::assertSame('high', $note['priority']);
    }

    public function testCreateAllowsEmptyContent(): void
    {
        $this->request('POST', '/api/notes', ['title' => 'Jen název', 'priority' => 'low']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('', $this->note()['content']);
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
        $error = $this->error();
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

    public function testCreateRejectsMalformedJson(): void
    {
        $this->client->request('POST', '/api/notes', server: ['CONTENT_TYPE' => 'application/json'], content: '{"title": "A",');

        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_json', $this->error()['code']);
    }

    public function testCreateRejectsNonJsonBody(): void
    {
        $this->client->request('POST', '/api/notes', ['title' => 'A', 'priority' => 'low']);

        self::assertResponseStatusCodeSame(415);
        self::assertSame('unsupported_media_type', $this->error()['code']);
    }

    public function testUpdateReplacesNoteAndMovesItUp(): void
    {
        $first = $this->createNote('First', 'medium');
        $this->createNote('Second', 'medium');
        self::mockTime('+10 minutes');

        $this->request('PUT', "/api/notes/$first", ['title' => 'First edited', 'content' => 'Nový obsah', 'priority' => 'medium']);

        self::assertResponseIsSuccessful();
        self::assertSame('2026-10-01T12:10:00+00:00', $this->note()['updatedAt']);
        self::assertSame('2026-10-01T12:00:00+00:00', $this->note()['createdAt']);

        $this->request('GET', '/api/notes');
        self::assertSame(['First edited', 'Second'], $this->titles());
    }

    public function testUpdateWithoutChangesKeepsTimestamp(): void
    {
        $id = $this->createNote('Same', 'low', 'Text');
        self::mockTime('+10 minutes');

        $this->request('PUT', "/api/notes/$id", ['title' => 'Same', 'content' => 'Text', 'priority' => 'low']);

        self::assertResponseIsSuccessful();
        self::assertSame('2026-10-01T12:00:00+00:00', $this->note()['updatedAt']);
    }

    public function testUpdateValidatesInput(): void
    {
        $id = $this->createNote('Valid', 'low');

        $this->request('PUT', "/api/notes/$id", ['title' => '', 'priority' => 'low']);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('title', $this->error()['fields'] ?? []);
    }

    public function testUpdateReturns404ForMissingNote(): void
    {
        $this->request('PUT', '/api/notes/999999', ['title' => 'A', 'priority' => 'low']);

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteRemovesNote(): void
    {
        $id = $this->createNote('To delete', 'low');

        $this->request('DELETE', "/api/notes/$id");
        self::assertResponseStatusCodeSame(204);

        $this->request('GET', "/api/notes/$id");
        self::assertResponseStatusCodeSame(404);
    }

    public function testUpdateWithOnlyWhitespaceAroundTitleKeepsTimestamp(): void
    {
        $id = $this->createNote('Same', 'low');
        self::mockTime('+10 minutes');

        $this->request('PUT', "/api/notes/$id", ['title' => '  Same  ', 'priority' => 'low']);

        self::assertResponseIsSuccessful();
        self::assertSame('2026-10-01T12:00:00+00:00', $this->note()['updatedAt']);
    }

    public function testDeleteReturns404ForMissingNote(): void
    {
        $this->request('DELETE', '/api/notes/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testCreateRejectsJsonThatIsNotAnObject(): void
    {
        $this->client->request('POST', '/api/notes', server: ['CONTENT_TYPE' => 'application/json'], content: '"just a string"');

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('title', $this->error()['fields'] ?? []);
    }

    public function testCreateRejectsEmptyBody(): void
    {
        $this->client->request('POST', '/api/notes', server: ['CONTENT_TYPE' => 'application/json'], content: '');

        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_json', $this->error()['code']);
    }

    public function testPatchIsNotSupported(): void
    {
        $id = $this->createNote('A', 'low');

        $this->request('PATCH', "/api/notes/$id", ['title' => 'B']);

        self::assertResponseStatusCodeSame(405);
        self::assertSame('method_not_allowed', $this->error()['code']);
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function request(string $method, string $uri, ?array $body = null): void
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR),
        );
    }

    private function createNote(string $title, string $priority, string $content = ''): int
    {
        $this->request('POST', '/api/notes', ['title' => $title, 'content' => $content, 'priority' => $priority]);
        self::assertResponseStatusCodeSame(201);

        return $this->note()['id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @return array{id: int, title: string, content: string, priority: string, createdAt: string, updatedAt: string}
     */
    private function note(): array
    {
        /** @var array{id: int, title: string, content: string, priority: string, createdAt: string, updatedAt: string} $note */
        $note = $this->json();

        return $note;
    }

    /**
     * @return array{code: string, message: string, fields?: array<string, list<string>>}
     */
    private function error(): array
    {
        $data = $this->json();
        self::assertArrayHasKey('error', $data);

        /** @var array{code: string, message: string, fields?: array<string, list<string>>} $error */
        $error = $data['error'];

        return $error;
    }

    /**
     * @return list<string>
     */
    private function titles(): array
    {
        /** @var array{data: list<array{title: string}>} $list */
        $list = $this->json();

        return array_column($list['data'], 'title');
    }
}
