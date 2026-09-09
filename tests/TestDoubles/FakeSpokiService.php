<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Tests\TestDoubles;

use AlessandroHgo\Yii2Spoki\SpokiService;

/**
 * Sostituisce SpokiService nei test dei job/servizi: nessuna chiamata HTTP reale, risposte
 * configurabili per metodo, e registrazione delle chiamate ricevute per verificarle nei test.
 */
final class FakeSpokiService extends SpokiService
{
    /** @var array<string, object> Risposta da restituire per ciascun metodo, per nome. */
    public array $responses = [];

    /** @var array<int, array{method: string, args: array}> Chiamate ricevute, in ordine. */
    public array $calls = [];

    private function respond(string $method, array $args): object
    {
        $this->calls[] = ['method' => $method, 'args' => $args];

        return $this->responses[$method] ?? self::success();
    }

    public static function success(mixed $data = null): object
    {
        return (object) ['success' => true, 'status' => 200, 'message' => null, 'data' => $data ?? (object) []];
    }

    public static function failure(string $message = 'errore'): object
    {
        return (object) ['success' => false, 'status' => 400, 'message' => $message, 'data' => []];
    }

    public function addSvClients(array $payload, ?string $apiKey = null): object
    {
        return $this->respond('addSvClients', func_get_args());
    }

    public function createApiKeyForAccount(array $payload, ?string $apiKey = null): object
    {
        return $this->respond('createApiKeyForAccount', func_get_args());
    }

    public function createSubrecharge(array $payload, ?string $apiKey = null): object
    {
        return $this->respond('createSubrecharge', func_get_args());
    }

    public function onboarding(array $payload, ?string $apiKey = null): object
    {
        return $this->respond('onboarding', func_get_args());
    }

    public function setProfits(array $payload, ?string $apiKey = null): object
    {
        return $this->respond('setProfits', func_get_args());
    }

    public function generatePrivateKey(int $partnerRoleId, ?string $apiKey = null): object
    {
        return $this->respond('generatePrivateKey', func_get_args());
    }

    public function getRoles(?string $search = null, ?string $apiKey = null): object
    {
        return $this->respond('getRoles', func_get_args());
    }

    public function addServiceUser(array $payload, ?string $apiKey = null): object
    {
        return $this->respond('addServiceUser', func_get_args());
    }

    public function getAccountSummary(int $accountId, ?string $apiKey = null): object
    {
        return $this->respond('getAccountSummary', func_get_args());
    }

    public function getAccountReport(int $accountId, int $granularity, string $startDate, string $endDate): object
    {
        return $this->respond('getAccountReport', func_get_args());
    }

    public function getAuthenticationToken(string $email, string $privateKey, ?string $apiKey = null): object
    {
        return $this->respond('getAuthenticationToken', func_get_args());
    }

    public function updatePartnerRole(int $partnerRoleId, array $payload, ?string $apiKey = null): object
    {
        return $this->respond('updatePartnerRole', func_get_args());
    }
}
