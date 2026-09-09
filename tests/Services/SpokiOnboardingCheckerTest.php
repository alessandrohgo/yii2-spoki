<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Tests\Services;

use AlessandroHgo\Yii2Spoki\Jobs\SpokiFinalActivationJob;
use AlessandroHgo\Yii2Spoki\Services\SpokiOnboardingChecker;
use AlessandroHgo\Yii2Spoki\Tests\TestDoubles\FakeSpokiService;
use AlessandroHgo\Yii2Spoki\Tests\TestDoubles\InMemorySpokiAccountRepository;
use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiAccountState;
use PHPUnit\Framework\TestCase;

/**
 * Sottoclasse di test: sovrascrive pushFinalActivationJob() per registrare il job accodato
 * invece di richiedere una vera coda Yii2 configurata — stesso punto di estensione pensato
 * per un host la cui coda non è il componente `queue` di default.
 */
final class RecordingSpokiOnboardingChecker extends SpokiOnboardingChecker
{
    /** @var SpokiFinalActivationJob[] */
    public array $pushedJobs = [];

    protected function pushFinalActivationJob(SpokiFinalActivationJob $job): void
    {
        $this->pushedJobs[] = $job;
    }
}

class SpokiOnboardingCheckerTest extends TestCase
{
    private FakeSpokiService $spokiService;
    private InMemorySpokiAccountRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->spokiService = new FakeSpokiService();
        $this->repository = new InMemorySpokiAccountRepository();
    }

    private function checker(): RecordingSpokiOnboardingChecker
    {
        return new RecordingSpokiOnboardingChecker($this->spokiService, $this->repository);
    }

    public function testCheckDoesNothingWhenAccountNotFound(): void
    {
        $this->checker()->check('owner-1');

        $this->assertSame([], $this->spokiService->calls);
    }

    public function testCheckDoesNothingWhenAccountNotInOnboardingPendingStatus(): void
    {
        $this->repository->save(new SpokiAccountState(ownerReference: 'owner-1', status: SpokiAccountState::STATUS_ACTIVE));

        $this->checker()->check('owner-1');

        $this->assertSame([], $this->spokiService->calls);
    }

    public function testCheckDoesNothingWhenOnboardingNotYetActive(): void
    {
        $this->repository->save(new SpokiAccountState(
            ownerReference: 'owner-1',
            spokiAccountId: 555,
            apiKey: 'key-abc',
            status: SpokiAccountState::STATUS_ONBOARDING_PENDING,
        ));
        $this->spokiService->responses = ['getAccountSummary' => FakeSpokiService::success((object) ['status' => 'Onboarding Started'])];

        $checker = $this->checker();
        $checker->check('owner-1');

        $this->assertSame(SpokiAccountState::STATUS_ONBOARDING_PENDING, $this->repository->findByOwnerReference('owner-1')->status);
        $this->assertSame([], $checker->pushedJobs);
    }

    public function testCheckQueuesFinalActivationJobWhenOnboardingActive(): void
    {
        $this->repository->save(new SpokiAccountState(
            ownerReference: 'owner-1',
            spokiAccountId: 555,
            apiKey: 'key-abc',
            status: SpokiAccountState::STATUS_ONBOARDING_PENDING,
        ));
        $this->spokiService->responses = ['getAccountSummary' => FakeSpokiService::success((object) ['status' => 'Active'])];

        $checker = $this->checker();
        $checker->check('owner-1');

        $this->assertCount(1, $checker->pushedJobs);
        $this->assertSame('owner-1', $checker->pushedJobs[0]->ownerReference);
        $this->assertSame(SpokiAccountState::STATUS_QUEUED_JOB, $this->repository->findByOwnerReference('owner-1')->status);
    }

    /**
     * Verifica che il criterio "attivo" riconosca anche il formato con canale whatsapp esplicito,
     * non solo il campo status generico.
     */
    public function testCheckRecognizesActiveStatusFromWhatsappChannel(): void
    {
        $this->repository->save(new SpokiAccountState(
            ownerReference: 'owner-1',
            spokiAccountId: 555,
            apiKey: 'key-abc',
            status: SpokiAccountState::STATUS_ONBOARDING_PENDING,
        ));
        $this->spokiService->responses = ['getAccountSummary' => FakeSpokiService::success((object) [
            'channels' => [(object) ['type' => 'whatsapp', 'status' => 'Active']],
        ])];

        $checker = $this->checker();
        $checker->check('owner-1');

        $this->assertCount(1, $checker->pushedJobs);
    }
}
