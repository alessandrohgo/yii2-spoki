<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Tests\Jobs;

use AlessandroHgo\Yii2Spoki\Contracts\SpokiAccountRepositoryInterface;
use AlessandroHgo\Yii2Spoki\Contracts\SpokiNotifierInterface;
use AlessandroHgo\Yii2Spoki\Jobs\SpokiFinalActivationJob;
use AlessandroHgo\Yii2Spoki\SpokiService;
use AlessandroHgo\Yii2Spoki\Tests\TestDoubles\FakeSpokiService;
use AlessandroHgo\Yii2Spoki\Tests\TestDoubles\InMemorySpokiAccountRepository;
use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiAccountState;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\di\Container;

class SpokiFinalActivationJobTest extends TestCase
{
    private FakeSpokiService $spokiService;
    private InMemorySpokiAccountRepository $repository;
    public array $notifierCalls = [];

    protected function setUp(): void
    {
        parent::setUp();

        Yii::$container = new Container();
        $this->spokiService = new FakeSpokiService();
        $this->repository = new InMemorySpokiAccountRepository();
        $this->notifierCalls = [];

        Yii::$container->set(SpokiService::class, fn () => $this->spokiService);
        Yii::$container->set(SpokiAccountRepositoryInterface::class, fn () => $this->repository);
        Yii::$container->set(SpokiNotifierInterface::class, fn () => new class ($this) implements SpokiNotifierInterface {
            public function __construct(private SpokiFinalActivationJobTest $test)
            {
            }

            public function notify(string $eventType, string $ownerReference, array $context): void
            {
                $this->test->notifierCalls[] = [$eventType, $ownerReference, $context];
            }
        });
    }

    private function roleFoundResponse(): object
    {
        return FakeSpokiService::success((object) [
            'results' => [(object) ['user' => (object) ['firstname' => 'Mario', 'surname' => 'Rossi']]],
        ]);
    }

    public function testExecuteReturnsFalseWhenAccountNotFound(): void
    {
        $job = new SpokiFinalActivationJob(['ownerReference' => 'owner-1']);

        $this->assertFalse($job->execute(null));
    }

    public function testExecuteReturnsFalseWhenAccountMissingApiKeyOrSpokiAccountId(): void
    {
        $this->repository->save(new SpokiAccountState(ownerReference: 'owner-1', email: 'x@y.com'));

        $job = new SpokiFinalActivationJob(['ownerReference' => 'owner-1']);
        $this->assertFalse($job->execute(null));
    }

    public function testExecuteCompletesFinalActivation(): void
    {
        $this->repository->save(new SpokiAccountState(ownerReference: 'owner-1', spokiAccountId: 555, apiKey: 'key-abc', email: 'cliente@esempio.com'));
        $this->spokiService->responses = [
            'getRoles' => $this->roleFoundResponse(),
            'addServiceUser' => FakeSpokiService::success((object) ['id' => 77, 'user' => (object) ['email' => 'service@esempio.com']]),
            'generatePrivateKey' => FakeSpokiService::success((object) ['value' => 'private-key-xyz']),
        ];

        $job = new SpokiFinalActivationJob(['ownerReference' => 'owner-1']);
        $this->assertTrue($job->execute(null));

        $state = $this->repository->findByOwnerReference('owner-1');
        $this->assertSame('service@esempio.com', $state->emailIframe);
        $this->assertSame('private-key-xyz', $state->privateKey);
        $this->assertSame(SpokiAccountState::STATUS_ACTIVE, $state->status);

        $this->assertCount(1, $this->notifierCalls);
        $this->assertSame(SpokiNotifierInterface::EVENT_ACTIVATED, $this->notifierCalls[0][0]);
        $this->assertSame('owner-1', $this->notifierCalls[0][1]);
    }

    public function testExecuteSetsErrorStatusWhenGetRolesFails(): void
    {
        $this->repository->save(new SpokiAccountState(ownerReference: 'owner-1', spokiAccountId: 555, apiKey: 'key-abc', email: 'cliente@esempio.com'));
        $this->spokiService->responses = ['getRoles' => FakeSpokiService::failure()];

        $job = new SpokiFinalActivationJob(['ownerReference' => 'owner-1']);
        $this->assertFalse($job->execute(null));

        $this->assertSame(SpokiAccountState::STATUS_ERROR, $this->repository->findByOwnerReference('owner-1')->status);
    }

    /**
     * Verifica che afterActivated() sia richiamato dopo il completamento — stesso punto di
     * estensione già verificato per SpokiActivationJob.
     */
    public function testAfterActivatedHookIsCalledOnSuccess(): void
    {
        $this->repository->save(new SpokiAccountState(ownerReference: 'owner-1', spokiAccountId: 555, apiKey: 'key-abc', email: 'cliente@esempio.com'));
        $this->spokiService->responses = [
            'getRoles' => $this->roleFoundResponse(),
            'addServiceUser' => FakeSpokiService::success((object) ['id' => 77, 'user' => (object) ['email' => 'service@esempio.com']]),
            'generatePrivateKey' => FakeSpokiService::success((object) ['value' => 'private-key-xyz']),
        ];

        $job = new class(['ownerReference' => 'owner-1']) extends SpokiFinalActivationJob {
            public bool $hookWasCalled = false;

            protected function afterActivated(SpokiAccountState $state): void
            {
                $this->hookWasCalled = true;
            }
        };
        $job->execute(null);

        $this->assertTrue($job->hookWasCalled);
    }
}
