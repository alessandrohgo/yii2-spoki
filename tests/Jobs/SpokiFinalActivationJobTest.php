<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Tests\Jobs;

use AlessandroHgo\Yii2Spoki\Jobs\SpokiFinalActivationJob;
use PHPUnit\Framework\TestCase;
use yii\queue\JobInterface;

/**
 * Il comportamento reale di questo job richiede Yii::$container configurato e una connessione
 * al database — nessuno dei due disponibile in questo repository standalone. Va verificato con
 * un test funzionale dopo l'installazione in un'app Yii2 reale. Qui si verifica solo che il
 * job rispetti il contratto atteso da yii\queue.
 */
class SpokiFinalActivationJobTest extends TestCase
{
    public function testImplementsQueueJobInterface(): void
    {
        $this->assertInstanceOf(JobInterface::class, new SpokiFinalActivationJob());
    }

    /**
     * Verifica che spokiAccountId sia configurabile come richiesto da yii\queue, che
     * costruisce i job da un array di configurazione al momento della dequeue.
     */
    public function testSpokiAccountIdIsConfigurable(): void
    {
        $job = new SpokiFinalActivationJob();
        $job->spokiAccountId = 42;

        $this->assertSame(42, $job->spokiAccountId);
    }
}
