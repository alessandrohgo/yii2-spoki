<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Tests\Jobs;

use AlessandroHgo\Yii2Spoki\Jobs\SpokiActivationJob;
use PHPUnit\Framework\TestCase;
use yii\queue\JobInterface;

/**
 * Il comportamento reale di questo job richiede Yii::$container configurato con le 3
 * interfacce e una connessione al database (SpokiAccount è un ActiveRecord) — nessuno dei due
 * disponibile in questo repository standalone. Va verificato con un test funzionale dopo
 * l'installazione in un'app Yii2 reale. Qui si verifica solo che il job rispetti il contratto
 * atteso da yii\queue.
 */
class SpokiActivationJobTest extends TestCase
{
    public function testImplementsQueueJobInterface(): void
    {
        $this->assertInstanceOf(JobInterface::class, new SpokiActivationJob());
    }

    /**
     * Verifica che purchaseReference sia configurabile come richiesto da yii\queue, che
     * costruisce i job da un array di configurazione al momento della dequeue.
     */
    public function testPurchaseReferenceIsConfigurable(): void
    {
        $job = new SpokiActivationJob();
        $job->purchaseReference = 'purchase-123';

        $this->assertSame('purchase-123', $job->purchaseReference);
    }
}
