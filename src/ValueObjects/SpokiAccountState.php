<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\ValueObjects;

/**
 * Stato neutro di un account Spoki, passato tra il modulo e l'adapter di storage dell'host
 * (implementazione di {@see \AlessandroHgo\Yii2Spoki\Contracts\SpokiAccountRepositoryInterface}),
 * senza esporre l'ActiveRecord reale dell'host (che può avere uno schema completamente diverso).
 *
 * Immutabile: ogni modifica di stato produce una nuova istanza tramite {@see self::with()},
 * così il job non deve mai mutare un oggetto già passato a un adapter.
 */
final class SpokiAccountState
{
    public const int STATUS_PENDING_REQUEST = 5;
    public const int STATUS_ONBOARDING_PENDING = 10;
    public const int STATUS_ONBOARDING_CONFIRM = 15;
    public const int STATUS_QUEUED_JOB = 20;
    public const int STATUS_ACTIVE = 25;
    public const int STATUS_ERROR = 30;

    public function __construct(
        public readonly string $ownerReference,
        public readonly ?int $spokiAccountId = null,
        public readonly ?string $apiKey = null,
        public readonly ?string $email = null,
        public readonly ?string $emailIframe = null,
        public readonly ?string $onboardingUrl = null,
        public readonly ?string $privateKey = null,
        public readonly int $status = self::STATUS_PENDING_REQUEST,
    ) {
    }

    /**
     * Restituisce una copia con solo i campi indicati sovrascritti, per aggiornare lo stato
     * senza dover riscrivere tutti i campi esistenti ad ogni passo del job.
     *
     * @param array<string, mixed> $changes Campi da sovrascrivere (stessi nomi del costruttore).
     */
    public function with(array $changes): self
    {
        return new self(
            ownerReference: $changes['ownerReference'] ?? $this->ownerReference,
            spokiAccountId: $changes['spokiAccountId'] ?? $this->spokiAccountId,
            apiKey: $changes['apiKey'] ?? $this->apiKey,
            email: $changes['email'] ?? $this->email,
            emailIframe: $changes['emailIframe'] ?? $this->emailIframe,
            onboardingUrl: $changes['onboardingUrl'] ?? $this->onboardingUrl,
            privateKey: $changes['privateKey'] ?? $this->privateKey,
            status: $changes['status'] ?? $this->status,
        );
    }
}
