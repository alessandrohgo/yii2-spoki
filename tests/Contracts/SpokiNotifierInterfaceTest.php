<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Tests\Contracts;

use AlessandroHgo\Yii2Spoki\Contracts\SpokiNotifierInterface;
use PHPUnit\Framework\TestCase;

class SpokiNotifierInterfaceTest extends TestCase
{
    public function testAdapterReceivesEventTypeOwnerReferenceAndContext(): void
    {
        $notifier = new class implements SpokiNotifierInterface {
            public array $calls = [];

            public function notify(string $eventType, string $ownerReference, array $context): void
            {
                $this->calls[] = [$eventType, $ownerReference, $context];
            }
        };

        $notifier->notify(SpokiNotifierInterface::EVENT_ACTIVATED, 'owner-1', ['plan' => 'light']);

        $this->assertSame([[SpokiNotifierInterface::EVENT_ACTIVATED, 'owner-1', ['plan' => 'light']]], $notifier->calls);
    }
}
