<?php

declare(strict_types=1);

namespace App\Tests\Behat\Context;

use App\Kernel;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use JsonException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

final class ApiContext implements Context
{
    private ?Kernel $kernel = null;
    private ?Response $response = null;

    /**
     * @When I send a :method request to :path
     */
    public function iSendARequestTo(string $method, string $path): void
    {
        $client = $this->createClient();
        $client->jsonRequest($method, $path);

        $this->response = $client->getResponse();
    }

    /**
     * @When I send a :method request to :path with JSON:
     */
    public function iSendARequestToWithJson(string $method, string $path, PyStringNode $payload): void
    {
        try {
            $decodedPayload = json_decode($payload->getRaw(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Step payload is not valid JSON.', previous: $exception);
        }

        if (!\is_array($decodedPayload) || array_is_list($decodedPayload)) {
            throw new RuntimeException('Step payload must be a JSON object.');
        }

        $client = $this->createClient();
        $client->jsonRequest($method, $path, $decodedPayload);

        $this->response = $client->getResponse();
    }

    /**
     * @When I send a :method request to :path with raw body:
     */
    public function iSendARequestToWithRawBody(string $method, string $path, PyStringNode $payload): void
    {
        $client = $this->createClient();
        $client->request(
            method: $method,
            uri: $path,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $payload->getRaw(),
        );

        $this->response = $client->getResponse();
    }

    /**
     * @Then the response status code should be :statusCode
     */
    public function theResponseStatusCodeShouldBe(int $statusCode): void
    {
        $actualStatusCode = $this->getResponse()->getStatusCode();

        if ($actualStatusCode !== $statusCode) {
            throw new RuntimeException(sprintf('Expected status code %d, got %d.', $statusCode, $actualStatusCode));
        }
    }

    /**
     * @Then the response should be a valid health check payload
     * @throws JsonException
     */
    public function theResponseShouldBeAValidHealthCheckPayload(): void
    {
        $response = $this->getResponse();
        $contentType = (string) $response->headers->get('Content-Type', '');

        if (!str_contains($contentType, 'application/json')) {
            throw new RuntimeException(sprintf('Expected a JSON response, got "%s".', $contentType));
        }

        $responseContent = $response->getContent();
        if ($responseContent === false) {
            throw new RuntimeException('Could not read response body content.');
        }

        try {
            $payload = json_decode($responseContent, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Response body is not valid JSON.', previous: $exception);
        }

        if (!is_array($payload)) {
            throw new RuntimeException('Response payload must decode to a JSON object.');
        }

        if ($payload !== ['ok' => true]) {
            throw new RuntimeException(sprintf(
                'Expected health payload %s, got %s.',
                json_encode(['ok' => true], JSON_THROW_ON_ERROR),
                json_encode($payload, JSON_THROW_ON_ERROR),
            ));
        }
    }

    /**
     * @Then the JSON response should contain:
     * @throws JsonException
     */
    public function theJsonResponseShouldContain(PyStringNode $expectedPayload): void
    {
        try {
            $decodedExpectedPayload = json_decode($expectedPayload->getRaw(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Expected JSON payload in feature step is invalid.', previous: $exception);
        }

        if (!\is_array($decodedExpectedPayload) || array_is_list($decodedExpectedPayload)) {
            throw new RuntimeException('Expected JSON payload must be an object.');
        }

        $decodedResponsePayload = $this->decodeJsonResponse();
        $this->assertJsonSubset($decodedExpectedPayload, $decodedResponsePayload, '$');
    }

    /**
     * @Then the JSON response should have exactly the keys :keys
     * @throws JsonException
     */
    public function theJsonResponseShouldHaveExactlyTheKeys(string $keys): void
    {
        $expectedKeys = array_values(array_filter(array_map(
            static fn (string $key): string => trim($key),
            explode(',', $keys),
        ), static fn (string $key): bool => $key !== ''));

        $actualPayload = $this->decodeJsonResponse();
        $actualKeys = array_keys($actualPayload);

        sort($expectedKeys);
        sort($actualKeys);

        if ($expectedKeys !== $actualKeys) {
            throw new RuntimeException(sprintf(
                'Expected JSON response keys %s, got %s.',
                json_encode($expectedKeys, JSON_THROW_ON_ERROR),
                json_encode($actualKeys, JSON_THROW_ON_ERROR),
            ));
        }
    }

    /**
     * @AfterScenario
     */
    public function resetKernel(): void
    {
        $this->response = null;
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    /**
     * @BeforeScenario @registration
     * @throws Exception
     */
    public function resetUsersForRegistrationScenarios(): void
    {
        $client = $this->createClient();
        $connection = $client->getContainer()->get(Connection::class);

        if (!$connection instanceof Connection) {
            throw new RuntimeException(sprintf(
                'Expected "%s" service to be %s, got %s.',
                Connection::class,
                Connection::class,
                get_debug_type($connection),
            ));
        }

        $connection->executeStatement('DELETE FROM users');
    }

    private function createClient(): KernelBrowser
    {
        $this->kernel?->shutdown();

        $this->kernel = new Kernel('test', false);
        $this->kernel->boot();

        $client = $this->kernel->getContainer()->get('test.client');

        if (!$client instanceof KernelBrowser) {
            throw new RuntimeException(sprintf(
                'Expected "test.client" to be an instance of %s, got %s.',
                KernelBrowser::class,
                get_debug_type($client),
            ));
        }

        return $client;
    }

    private function getResponse(): Response
    {
        if (!$this->response instanceof Response) {
            throw new RuntimeException('No HTTP response is available. Send a request first.');
        }

        return $this->response;
    }

    /** @return array<string, mixed> */
    private function decodeJsonResponse(): array
    {
        $response = $this->getResponse();
        $responseContent = $response->getContent();

        if ($responseContent === false) {
            throw new RuntimeException('Could not read response body content.');
        }

        try {
            $decodedResponsePayload = json_decode($responseContent, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Response body is not valid JSON.', previous: $exception);
        }

        if (!\is_array($decodedResponsePayload)) {
            throw new RuntimeException('Response payload must decode to a JSON object or array.');
        }

        return $decodedResponsePayload;
    }

    /**
     * @param array<string, mixed> $expectedSubset
     * @param array<string, mixed> $actualPayload
     * @throws JsonException
     */
    private function assertJsonSubset(array $expectedSubset, array $actualPayload, string $path): void
    {
        foreach ($expectedSubset as $key => $expectedValue) {
            if (!array_key_exists($key, $actualPayload)) {
                throw new RuntimeException(sprintf('Expected key "%s.%s" is missing in response payload.', $path, $key));
            }

            $actualValue = $actualPayload[$key];

            if ($expectedValue === '*') {
                if (!\is_string($actualValue) || trim($actualValue) === '') {
                    throw new RuntimeException(sprintf('Expected "%s.%s" to be a non-empty string.', $path, $key));
                }
                continue;
            }

            if (\is_array($expectedValue)) {
                if (!\is_array($actualValue)) {
                    throw new RuntimeException(sprintf('Expected "%s.%s" to be a JSON object/array.', $path, $key));
                }

                /** @var array<string, mixed> $expectedArray */
                $expectedArray = $expectedValue;
                /** @var array<string, mixed> $actualArray */
                $actualArray = $actualValue;
                $this->assertJsonSubset($expectedArray, $actualArray, sprintf('%s.%s', $path, $key));
                continue;
            }

            if ($actualValue !== $expectedValue) {
                throw new RuntimeException(sprintf(
                    'Expected "%s.%s" to equal %s, got %s.',
                    $path,
                    $key,
                    json_encode($expectedValue, JSON_THROW_ON_ERROR),
                    json_encode($actualValue, JSON_THROW_ON_ERROR),
                ));
            }
        }
    }
}
