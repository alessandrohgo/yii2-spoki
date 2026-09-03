<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Tests\Contracts;

use AlessandroHgo\Yii2Spoki\Contracts\SpokiNotifierInterface;
use PHPUnit\Framework\TestCase;

class SpokiNotifierInterfaceTest extends TestCase
{
    /**
     * Verifica che un adapter riceva tipo evento, riferimento account e contesto così come
     * passati, senza che il contratto imponga un canale di notifica specifico.
     */
    public function testAdapterReceivesEventTypeAccountReferenceAndContext(): void
    {
        $notifier = new class implements SpokiNotifierInterface {
            public array $calls = [];

            public function notify(string $eventType, string $accountReference, array $context): void
            {
                $this->calls[] = [$eventType, $accountReference, $context];
            }
        };

        $notifier->notify('activated', 'account-1', ['plan' => 'light']);

        $this->assertSame([['activated', 'account-1', ['plan' => 'light']]], $notifier->calls);
    }
}
