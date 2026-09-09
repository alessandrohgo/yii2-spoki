<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki;

use Curl\Curl;
use Yii;
use yii\base\Component;

/**
 * Client API Spoki.
 *
 * Tutti i metodi pubblici ritornano SEMPRE un oggetto:
 *   (object){
 *     success: bool,
 *     status: int,
 *     message: ?string,
 *     data: mixed   // stdClass o array di stdClass
 *   }
 *
 * Configurazione: nessun caricamento automatico da file — l'app ospite fornisce `baseUrl` e
 * `apiKey` tramite il proprio binding DI (config array Yii2 standard), es.:
 *
 * ```php
 * Yii::$container->set(SpokiService::class, [
 *     'baseUrl' => 'https://api.spoki.example',
 *     'apiKey' => getenv('SPOKI_API_KEY'),
 * ]);
 * ```
 *
 * Gestione API Key:
 * - Per default, tutti i metodi usano l'API key Partner configurata su `$apiKey`.
 * - È possibile passare un'API key personalizzata come ultimo parametro opzionale di ogni
 *   metodo, utile per chiamate che richiedono l'API key Cliente (es. /partner-roles/*).
 */
class SpokiService extends Component
{
    private const TYPE_GET  = 'GET';
    private const TYPE_POST = 'POST';

    public const ADD_SV_CLIENTS             = '/partners/add_sv_clients/';
    public const CREATE_API_KEY_FOR_ACCOUNT = '/partners/create_api_key_for_account/';
    public const CREATE_SUBRECHARGE         = '/partners/create_subrecharge/';
    public const ONBOARDING                 = '/partners/onboarding/';
    public const SET_PROFITS                = '/partners/set_profits/';
    public const GENERATE_PRIVATE_KEY       = '/roles/{id}/generate_private_key/';
    public const GET_ROLES                  = '/roles/';
    public const ADD_SERVICE_USER           = '/roles/add_service_user/';
    public const GET_ACCOUNT_SUMMARY        = '/accounts/{id}/';
    public const GET_ACCOUNT_REPORT         = '/partners/get_account_report/';
    public const GET_AUTHENTICATION_TOKEN   = '/auth/get_authentication_token/';
    public const UPDATE_PARTNER_ROLE        = '/partner-roles/{id}/update_role/';

    public string $baseUrl = '';
    public ?string $apiKey = null;
    public int $timeout = 30;
    public int $connectTimeout = 10;
    public bool $log = false;

    private ?array $lastRawResponse = null;

    public function init(): void
    {
        parent::init();

        $this->baseUrl = rtrim($this->baseUrl, '/');

        if (empty($this->apiKey)) {
            Yii::warning('Spoki API key is empty. Check configuration.', __METHOD__);
        }
    }

    /**
     * Ultima risposta raw (array decodificato), utile per il debug.
     */
    public function getLastRawResponse(): ?array
    {
        return $this->lastRawResponse;
    }

    /**
     * POST /partners/add_sv_clients/
     *
     * @param array $payload Dati cliente/i.
     * @param string|null $apiKey API key da usare (opzionale, default: API key Partner configurata).
     */
    public function addSvClients(array $payload, ?string $apiKey = null): object
    {
        return $this->call(self::TYPE_POST, self::ADD_SV_CLIENTS, $payload, $apiKey);
    }

    /**
     * POST /partners/create_api_key_for_account/
     *
     * @param array $payload Dati account.
     * @param string|null $apiKey API key da usare (opzionale, default: API key Partner configurata).
     */
    public function createApiKeyForAccount(array $payload, ?string $apiKey = null): object
    {
        return $this->call(self::TYPE_POST, self::CREATE_API_KEY_FOR_ACCOUNT, $payload, $apiKey);
    }

    /**
     * POST /partners/create_subrecharge/
     *
     * @param array $payload Dati ricarica.
     * @param string|null $apiKey API key da usare (opzionale, default: API key Partner configurata).
     *                            Passa l'API key Cliente se necessario.
     */
    public function createSubrecharge(array $payload, ?string $apiKey = null): object
    {
        return $this->call(self::TYPE_POST, self::CREATE_SUBRECHARGE, $payload, $apiKey);
    }

    /**
     * POST /partners/onboarding/
     *
     * @param array $payload Dati onboarding.
     * @param string|null $apiKey API key da usare (opzionale, default: API key Partner configurata).
     */
    public function onboarding(array $payload, ?string $apiKey = null): object
    {
        return $this->call(self::TYPE_POST, self::ONBOARDING, $payload, $apiKey);
    }

    /**
     * POST /partners/set_profits/
     *
     * @param array $payload Dati margini.
     * @param string|null $apiKey API key da usare (opzionale, default: API key Partner configurata).
     */
    public function setProfits(array $payload, ?string $apiKey = null): object
    {
        return $this->call(self::TYPE_POST, self::SET_PROFITS, $payload, $apiKey);
    }

    /**
     * POST /roles/{id}/generate_private_key/
     * Genera la chiave privata finale per l'iframe.
     *
     * @param int $partnerRoleId ID del partner role (spoki_account_id).
     * @param string|null $apiKey API key da usare (opzionale, default: API key Partner configurata).
     *                            Passa l'API key Cliente se necessario.
     */
    public function generatePrivateKey(int $partnerRoleId, ?string $apiKey = null): object
    {
        $path = str_replace('{id}', (string) $partnerRoleId, self::GENERATE_PRIVATE_KEY);

        return $this->call(self::TYPE_POST, $path, [], $apiKey);
    }

    /**
     * GET /roles/
     * Recupera la lista dei ruoli disponibili, opzionalmente filtrata per email.
     *
     * @param string|null $search Email da cercare (opzionale), aggiunta come query parameter ?search=...
     * @param string|null $apiKey API key da usare (opzionale, default: API key Partner configurata).
     *                            Passa l'API key Cliente se necessario.
     */
    public function getRoles(?string $search = null, ?string $apiKey = null): object
    {
        $params = [];
        if ($search !== null && $search !== '') {
            $params['search'] = $search;
        }

        return $this->call(self::TYPE_GET, self::GET_ROLES, $params, $apiKey);
    }

    /**
     * POST /roles/add_service_user/
     * Crea un utente di servizio.
     *
     * @param array $payload Dati utente di servizio.
     * @param string|null $apiKey API key da usare (opzionale, default: API key Partner configurata).
     *                            Passa l'API key Cliente se necessario.
     */
    public function addServiceUser(array $payload, ?string $apiKey = null): object
    {
        return $this->call(self::TYPE_POST, self::ADD_SERVICE_USER, $payload, $apiKey);
    }

    /**
     * GET /accounts/{id}/
     * Recupera il riepilogo account (credito, stato, metriche principali).
     *
     * @param int $accountId ID dell'account Spoki.
     * @param string|null $apiKey API key Cliente (preferita) o Partner.
     */
    public function getAccountSummary(int $accountId, ?string $apiKey = null): object
    {
        $path = str_replace('{id}', (string) $accountId, self::GET_ACCOUNT_SUMMARY);

        return $this->call(self::TYPE_GET, $path, [], $apiKey);
    }

    /**
     * GET /partners/get_account_report/
     * Recupera il report account per intervallo.
     *
     * @param int $accountId ID dell'account Spoki.
     * @param int $granularity 2 = giornaliera (come da API partner).
     * @param string $startDate Data inizio, formato YYYY-MM-DD.
     * @param string $endDate Data fine, formato YYYY-MM-DD.
     */
    public function getAccountReport(int $accountId, int $granularity, string $startDate, string $endDate): object
    {
        $params = [
            'account_id' => $accountId,
            'granularity' => $granularity,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];

        // Nessun override API key: usa quella Partner configurata sul servizio.
        return $this->call(self::TYPE_GET, self::GET_ACCOUNT_REPORT, $params, null);
    }

    /**
     * POST /auth/get_authentication_token/
     * Genera il token di autenticazione (`token`, `uid`) usato per costruire l'URL
     * dell'iframe (`https://spoki.app/{pagina}?auth_token={token}&auth_uid={uid}`).
     *
     * @param string $email Email dell'account (spoki_account.email_iframe).
     * @param string $privateKey Private key generata con {@see generatePrivateKey()}.
     * @param string|null $apiKey API key da usare (opzionale, default: API key Partner configurata).
     *                            Nell'uso reale va sempre passata l'API key Cliente dell'account.
     */
    public function getAuthenticationToken(string $email, string $privateKey, ?string $apiKey = null): object
    {
        return $this->call(self::TYPE_POST, self::GET_AUTHENTICATION_TOKEN, [
            'email' => $email,
            'private_key' => $privateKey,
        ], $apiKey);
    }

    /**
     * POST /partner-roles/{id}/update_role/
     * Aggiorna un partner role. Il corpo esatto richiesto dall'API non è documentato con
     * certezza in questo SDK: passa i campi che l'endpoint richiede.
     *
     * @param int $partnerRoleId ID del partner role.
     * @param array $payload Dati da aggiornare.
     * @param string|null $apiKey API key da usare (opzionale, default: API key Partner configurata).
     */
    public function updatePartnerRole(int $partnerRoleId, array $payload, ?string $apiKey = null): object
    {
        $path = str_replace('{id}', (string) $partnerRoleId, self::UPDATE_PARTNER_ROLE);

        return $this->call(self::TYPE_POST, $path, $payload, $apiKey);
    }

    /**
     * Esegue la chiamata e normalizza la risposta nel formato standard del client.
     */
    protected function call(string $method, string $path, array $payload, ?string $apiKey): object
    {
        $res = $this->request($method, $path, $payload, $apiKey);
        $data = is_array($res->data) ? $this->arrayToObjectDeep($res->data) : $res->data;

        return (object) [
            'success' => $res->success,
            'status' => $res->status,
            'message' => $res->message,
            'data' => $data,
        ];
    }

    /**
     * Esegue la richiesta HTTP verso l'API Spoki e ne normalizza la risposta.
     */
    protected function request(string $method, string $path, array $params, ?string $apiKey): object
    {
        $curl = new Curl();

        $curl->setOpt(CURLOPT_FOLLOWLOCATION, true);
        $curl->setOpt(CURLOPT_RETURNTRANSFER, true);
        $curl->setOpt(CURLOPT_TIMEOUT, $this->timeout);
        $curl->setOpt(CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);

        $curl->setHeaders($this->buildHeaders($apiKey));

        $url = $this->baseUrl . '/' . ltrim($path, '/');

        if ($this->log) {
            Yii::info(['url' => $url, 'method' => $method, 'params' => $params], static::class . '::request');
        }

        $response = match ($method) {
            self::TYPE_GET => $curl->get($url, $params),
            self::TYPE_POST => $curl->post($url, json_encode($params, JSON_UNESCAPED_UNICODE)),
        };

        $payload = $this->decodeResponse($response, $curl);
        $this->lastRawResponse = $payload;

        $status = (int) ($curl->httpStatusCode ?? 0);
        $success = $status >= 200 && $status < 300;

        if (!$success && $this->log) {
            Yii::warning(['url' => $url, 'status' => $status, 'payload' => $payload], static::class . '::response');
        } elseif ($this->log) {
            Yii::info(['url' => $url, 'status' => $status, 'payload' => $payload], static::class . '::response');
        }

        return (object) [
            'success' => $success,
            'status' => $status,
            'message' => $success ? null : $this->extractErrorMessage($payload),
            'data' => $payload,
        ];
    }

    /**
     * Header di richiesta: Content-Type, ed eventuale X-Spoki-Api-Key (override o default).
     */
    protected function buildHeaders(?string $apiKey): array
    {
        $headers = ['Content-Type' => 'application/json'];

        $effectiveApiKey = $apiKey ?? $this->apiKey;
        if (!empty($effectiveApiKey)) {
            $headers['X-Spoki-Api-Key'] = $effectiveApiKey;
        }

        return $headers;
    }

    /**
     * Decodifica la risposta Spoki gestendo array, oggetti (stdClass) e stringhe JSON.
     */
    protected function decodeResponse(mixed $response, Curl $curl): array
    {
        $decoded = $this->tryDecode($response);
        if ($decoded !== null) {
            return $decoded;
        }

        if (isset($curl->response)) {
            $decoded = $this->tryDecode($curl->response);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Prova a normalizzare un valore di risposta (array, oggetto o stringa) in un array.
     * Restituisce null se il valore non è utilizzabile (es. stringa vuota).
     */
    protected function tryDecode(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            $decoded = json_decode(json_encode($value), true);

            return is_array($decoded) ? $decoded : null;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            if (json_last_error() === JSON_ERROR_NONE && is_string($decoded)) {
                return ['value' => $decoded];
            }

            return ['value' => $value];
        }

        return null;
    }

    /**
     * Estrae il messaggio di errore dal formato specifico Spoki: { "title": "...", "detail": "..." }
     * o dal formato generico { "message": "..." }.
     */
    protected function extractErrorMessage(array $payload): ?string
    {
        $message = $payload['message'] ?? null;
        if (!empty($message)) {
            return $message;
        }

        $title = $payload['title'] ?? null;
        $detail = $payload['detail'] ?? null;

        if ($title || $detail) {
            return implode(' - ', array_filter([$title, $detail]));
        }

        return null;
    }

    /**
     * Converte ricorsivamente array in stdClass, mantenendo gli array-lista come array di oggetti.
     */
    protected function arrayToObjectDeep(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $isList = array_keys($value) === range(0, count($value) - 1);
        if ($isList) {
            return array_map(fn (mixed $item) => $this->arrayToObjectDeep($item), $value);
        }

        $object = new \stdClass();
        foreach ($value as $key => $item) {
            $object->{$key} = $this->arrayToObjectDeep($item);
        }

        return $object;
    }
}
