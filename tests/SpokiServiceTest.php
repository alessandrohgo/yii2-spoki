<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Tests;

use AlessandroHgo\Yii2Spoki\SpokiService;
use PHPUnit\Framework\TestCase;

/**
 * Espone i metodi protetti di SpokiService per testarne la logica pura (parsing, header,
 * normalizzazione risposta), senza eseguire vere chiamate HTTP verso l'API Spoki.
 */
final class SpokiServiceTestDouble extends SpokiService
{
    public function publicBuildHeaders(?string $apiKey): array
    {
        return $this->buildHeaders($apiKey);
    }

    public function publicTryDecode(mixed $value): ?array
    {
        return $this->tryDecode($value);
    }

    public function publicExtractErrorMessage(array $payload): ?string
    {
        return $this->extractErrorMessage($payload);
    }

    public function publicArrayToObjectDeep(mixed $value): mixed
    {
        return $this->arrayToObjectDeep($value);
    }
}

class SpokiServiceTest extends TestCase
{
    /**
     * Verifica che init() rimuova lo slash finale da baseUrl, per evitare doppie barre
     * quando i path degli endpoint vengono concatenati.
     */
    public function testInitTrimsTrailingSlashFromBaseUrl(): void
    {
        $service = new SpokiService(['baseUrl' => 'https://api.spoki.example/']);

        $this->assertSame('https://api.spoki.example', $service->baseUrl);
    }

    /**
     * Verifica che l'header X-Spoki-Api-Key usi l'apiKey configurata di default.
     */
    public function testBuildHeadersUsesConfiguredApiKeyByDefault(): void
    {
        $service = new SpokiServiceTestDouble(['baseUrl' => 'https://x', 'apiKey' => 'partner-key']);

        $headers = $service->publicBuildHeaders(null);

        $this->assertSame('application/json', $headers['Content-Type']);
        $this->assertSame('partner-key', $headers['X-Spoki-Api-Key']);
    }

    /**
     * Verifica che un'apiKey passata esplicitamente sovrascriva quella configurata di default,
     * necessario per le chiamate che richiedono l'API key Cliente invece che Partner.
     */
    public function testBuildHeadersOverridesConfiguredApiKeyWhenProvided(): void
    {
        $service = new SpokiServiceTestDouble(['baseUrl' => 'https://x', 'apiKey' => 'partner-key']);

        $headers = $service->publicBuildHeaders('customer-key');

        $this->assertSame('customer-key', $headers['X-Spoki-Api-Key']);
    }

    /**
     * Verifica che senza alcuna apiKey configurata o passata, l'header non venga aggiunto.
     */
    public function testBuildHeadersOmitsApiKeyHeaderWhenNoneAvailable(): void
    {
        $service = new SpokiServiceTestDouble(['baseUrl' => 'https://x']);

        $headers = $service->publicBuildHeaders(null);

        $this->assertArrayNotHasKey('X-Spoki-Api-Key', $headers);
    }

    /**
     * Verifica che un array venga restituito invariato da tryDecode().
     */
    public function testTryDecodeReturnsArrayAsIs(): void
    {
        $service = new SpokiServiceTestDouble(['baseUrl' => 'https://x']);

        $this->assertSame(['a' => 1], $service->publicTryDecode(['a' => 1]));
    }

    /**
     * Verifica che una stringa JSON valida (oggetto) venga decodificata in array.
     */
    public function testTryDecodeParsesValidJsonObjectString(): void
    {
        $service = new SpokiServiceTestDouble(['baseUrl' => 'https://x']);

        $this->assertSame(['a' => 1], $service->publicTryDecode('{"a":1}'));
    }

    /**
     * Verifica che una stringa non-JSON venga avvolta in ['value' => ...], così da non perdere
     * il contenuto della risposta anche quando Spoki non restituisce JSON.
     */
    public function testTryDecodeWrapsNonJsonStringAsValue(): void
    {
        $service = new SpokiServiceTestDouble(['baseUrl' => 'https://x']);

        $this->assertSame(['value' => 'plain text'], $service->publicTryDecode('plain text'));
    }

    /**
     * Verifica che una stringa vuota non produca un risultato utilizzabile.
     */
    public function testTryDecodeReturnsNullForEmptyString(): void
    {
        $service = new SpokiServiceTestDouble(['baseUrl' => 'https://x']);

        $this->assertNull($service->publicTryDecode(''));
    }

    /**
     * Verifica che il messaggio d'errore generico "message" abbia la priorità.
     */
    public function testExtractErrorMessagePrefersGenericMessageField(): void
    {
        $service = new SpokiServiceTestDouble(['baseUrl' => 'https://x']);

        $message = $service->publicExtractErrorMessage(['message' => 'Errore generico', 'title' => 'Ignorato']);

        $this->assertSame('Errore generico', $message);
    }

    /**
     * Verifica il formato di errore specifico Spoki (title + detail), usato quando "message"
     * non è presente.
     */
    public function testExtractErrorMessageCombinesTitleAndDetailWhenNoMessage(): void
    {
        $service = new SpokiServiceTestDouble(['baseUrl' => 'https://x']);

        $message = $service->publicExtractErrorMessage(['title' => 'Bad Request', 'detail' => 'Campo mancante']);

        $this->assertSame('Bad Request - Campo mancante', $message);
    }

    /**
     * Verifica che array associativi annidati diventino stdClass, mantenendo le liste come
     * array di oggetti — comportamento richiesto dai consumatori che leggono i dati come oggetti.
     */
    public function testArrayToObjectDeepConvertsAssociativeArraysButKeepsLists(): void
    {
        $service = new SpokiServiceTestDouble(['baseUrl' => 'https://x']);

        $result = $service->publicArrayToObjectDeep([
            'account' => ['id' => 1, 'name' => 'Test'],
            'roles' => [['id' => 1], ['id' => 2]],
        ]);

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertInstanceOf(\stdClass::class, $result->account);
        $this->assertSame(1, $result->account->id);
        $this->assertIsArray($result->roles);
        $this->assertInstanceOf(\stdClass::class, $result->roles[0]);
        $this->assertSame(2, $result->roles[1]->id);
    }
}
