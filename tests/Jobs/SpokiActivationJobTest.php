<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Tests\Jobs;

use AlessandroHgo\Yii2Spoki\Contracts\SpokiAccountRepositoryInterface;
use AlessandroHgo\Yii2Spoki\Contracts\SpokiInvoicingInterface;
use AlessandroHgo\Yii2Spoki\Contracts\SpokiNotifierInterface;
use AlessandroHgo\Yii2Spoki\Contracts\SpokiPurchaseGatewayInterface;
use AlessandroHgo\Yii2Spoki\Jobs\SpokiActivationJob;
use AlessandroHgo\Yii2Spoki\SpokiService;
use AlessandroHgo\Yii2Spoki\Tests\TestDoubles\FakeSpokiService;
use AlessandroHgo\Yii2Spoki\Tests\TestDoubles\InMemorySpokiAccountRepository;
use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiAccountState;
use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiPurchaseContext;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\di\Container;

/**
 * Grazie alle 4 interfacce, il comportamento completo del job è testabile con adapter finti
 * al posto di un vero database/API — non era possibile prima di questo refactor.
 */
class SpokiActivationJobTest extends TestCase
{
    private FakeSpokiService $spokiService;
    private InMemorySpokiAccountRepository $repository;
    public array $invoicingCalls = [];
    public array $notifierCalls = [];

    protected function setUp(): void
    {
        parent::setUp();

        Yii::$container = new Container();
        $this->spokiService = new FakeSpokiService();
        $this->repository = new InMemorySpokiAccountRepository();
        $this->invoicingCalls = [];
        $this->notifierCalls = [];

        Yii::$container->set(SpokiService::class, fn () => $this->spokiService);
        Yii::$container->set(SpokiAccountRepositoryInterface::class, fn () => $this->repository);
        Yii::$container->set(SpokiInvoicingInterface::class, fn () => new class ($this) implements SpokiInvoicingInterface {
            public function __construct(private SpokiActivationJobTest $test)
            {
            }

            public function enqueueInvoice(SpokiPurchaseContext $context): bool
            {
                $this->test->invoicingCalls[] = $context;

                return true;
            }
        });
        Yii::$container->set(SpokiNotifierInterface::class, fn () => new class ($this) implements SpokiNotifierInterface {
            public function __construct(private SpokiActivationJobTest $test)
            {
            }

            public function notify(string $eventType, string $ownerReference, array $context): void
            {
                $this->test->notifierCalls[] = [$eventType, $ownerReference, $context];
            }
        });
    }

    private function validContext(): SpokiPurchaseContext
    {
        return new SpokiPurchaseContext(
            purchaseReference: 'purchase-1',
            amountInCents: 5000,
            currency: 'EUR',
            metadata: [
                SpokiPurchaseContext::METADATA_OWNER_REFERENCE => 'owner-1',
                SpokiPurchaseContext::METADATA_EMAIL => 'cliente@esempio.com',
                SpokiPurchaseContext::METADATA_FIRST_NAME => 'Mario',
                SpokiPurchaseContext::METADATA_ACCOUNT_NAME => 'Azienda SRL',
            ],
        );
    }

    private function bindGateway(?SpokiPurchaseContext $context, bool $markFulfilledResult = true): void
    {
        Yii::$container->set(SpokiPurchaseGatewayInterface::class, fn () => new class ($context, $markFulfilledResult) implements SpokiPurchaseGatewayInterface {
            public function __construct(private ?SpokiPurchaseContext $context, private bool $markFulfilledResult)
            {
            }

            public function findPendingPurchase(string $purchaseReference): ?SpokiPurchaseContext
            {
                return $this->context;
            }

            public function markFulfilled(SpokiPurchaseContext $context): bool
            {
                return $this->markFulfilledResult;
            }
        });
    }

    public function testExecuteReturnsFalseWhenNoPendingPurchaseFound(): void
    {
        $this->bindGateway(null);
        $job = new SpokiActivationJob(['purchaseReference' => 'purchase-1']);

        $this->assertFalse($job->execute(null));
    }

    public function testExecuteReturnsFalseWhenRequiredMetadataIsMissing(): void
    {
        $this->bindGateway(new SpokiPurchaseContext('purchase-1', 1000, 'EUR', []));
        $job = new SpokiActivationJob(['purchaseReference' => 'purchase-1']);

        $this->assertFalse($job->execute(null));
    }

    public function testExecuteCompletesFullActivationFlow(): void
    {
        $this->bindGateway($this->validContext());
        $this->spokiService->responses = [
            'addSvClients' => FakeSpokiService::success([['id' => 555]]),
            'createApiKeyForAccount' => FakeSpokiService::success((object) ['api_key' => 'key-abc']),
            'setProfits' => FakeSpokiService::success(),
            'onboarding' => FakeSpokiService::success((object) ['redirect_url' => 'https://spoki.app/onboard/555']),
            'createSubrecharge' => FakeSpokiService::success(),
        ];

        $job = new SpokiActivationJob(['purchaseReference' => 'purchase-1']);
        $this->assertTrue($job->execute(null));

        $state = $this->repository->findByOwnerReference('owner-1');
        $this->assertSame(555, $state->spokiAccountId);
        $this->assertSame('key-abc', $state->apiKey);
        $this->assertSame('https://spoki.app/onboard/555', $state->onboardingUrl);
        $this->assertSame(SpokiAccountState::STATUS_ONBOARDING_PENDING, $state->status);

        $this->assertCount(1, $this->invoicingCalls);
        $this->assertCount(1, $this->notifierCalls);
        $this->assertSame(SpokiNotifierInterface::EVENT_PAYMENT_CONFIRMED, $this->notifierCalls[0][0]);
    }

    public function testExecuteReturnsFalseWhenAddSvClientsFails(): void
    {
        $this->bindGateway($this->validContext());
        $this->spokiService->responses = ['addSvClients' => FakeSpokiService::failure('errore partner')];

        $job = new SpokiActivationJob(['purchaseReference' => 'purchase-1']);
        $this->assertFalse($job->execute(null));
        $this->assertNull($this->repository->findByOwnerReference('owner-1')->spokiAccountId ?? null);
    }

    /**
     * Verifica che i margini di default (tutti zero) siano quelli effettivamente inviati a
     * setProfits — un host che vuole margini diversi sovrascrive profitMargins().
     */
    public function testDefaultProfitMarginsAreAllZero(): void
    {
        $this->bindGateway($this->validContext());
        $this->spokiService->responses = [
            'addSvClients' => FakeSpokiService::success([['id' => 555]]),
            'createApiKeyForAccount' => FakeSpokiService::success((object) ['api_key' => 'key-abc']),
            'onboarding' => FakeSpokiService::success((object) ['redirect_url' => 'https://x']),
        ];

        (new SpokiActivationJob(['purchaseReference' => 'purchase-1']))->execute(null);

        $setProfitsCall = array_values(array_filter($this->spokiService->calls, fn ($c) => $c['method'] === 'setProfits'))[0];
        $this->assertSame(0, $setProfitsCall['args'][0]['conversation_profit_margin']);
    }

    /**
     * Verifica che un host possa personalizzare i margini estendendo il job e sovrascrivendo
     * profitMargins() — senza toccare execute() né gli altri passi.
     */
    public function testSubclassCanOverrideProfitMargins(): void
    {
        $this->bindGateway($this->validContext());
        $this->spokiService->responses = [
            'addSvClients' => FakeSpokiService::success([['id' => 555]]),
            'createApiKeyForAccount' => FakeSpokiService::success((object) ['api_key' => 'key-abc']),
            'onboarding' => FakeSpokiService::success((object) ['redirect_url' => 'https://x']),
        ];

        $job = new class(['purchaseReference' => 'purchase-1']) extends SpokiActivationJob {
            protected function profitMargins(): array
            {
                return array_merge(parent::profitMargins(), ['conversation_profit_margin' => 40]);
            }
        };
        $job->execute(null);

        $setProfitsCall = array_values(array_filter($this->spokiService->calls, fn ($c) => $c['method'] === 'setProfits'))[0];
        $this->assertSame(40, $setProfitsCall['args'][0]['conversation_profit_margin']);
    }

    /**
     * Verifica che afterActivated() sia richiamato dopo il completamento dell'attivazione —
     * il punto di estensione pensato per un host che vuole aggiungere un passo extra.
     */
    public function testAfterActivatedHookIsCalledOnSuccess(): void
    {
        $this->bindGateway($this->validContext());
        $this->spokiService->responses = [
            'addSvClients' => FakeSpokiService::success([['id' => 555]]),
            'createApiKeyForAccount' => FakeSpokiService::success((object) ['api_key' => 'key-abc']),
            'onboarding' => FakeSpokiService::success((object) ['redirect_url' => 'https://x']),
        ];

        $job = new class(['purchaseReference' => 'purchase-1']) extends SpokiActivationJob {
            public bool $hookWasCalled = false;

            protected function afterActivated(SpokiPurchaseContext $context, SpokiAccountState $state): void
            {
                $this->hookWasCalled = true;
            }
        };

        $job->execute(null);

        $this->assertTrue($job->hookWasCalled);
    }
}
