<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Crea la tabella spoki_account per gestire gli account Spoki.
 *
 * `owner_reference` è un riferimento opaco all'entità proprietaria dell'account, deciso
 * dall'app ospite (nessuna foreign key verso una tabella specifica): il modulo non presuppone
 * uno schema utenti particolare.
 */
class m260903_120000_create_spoki_account_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%spoki_account}}', [
            'id' => $this->primaryKey(),
            'owner_reference' => $this->string(255)->notNull()->comment('Riferimento opaco all\'entità proprietaria, deciso dall\'app ospite'),
            'email' => $this->string(255)->null()->comment('Email account Spoki'),
            'email_iframe' => $this->string(255)->null()->comment('Email da usare solo per iframe'),
            'spoki_account_id' => $this->integer()->null()->comment('ID account su Spoki (ricevuto da API)'),
            'api_key' => $this->string(255)->null()->comment('API Key per dashboard Spoki'),
            'onboarding_url' => $this->string(255)->null()->comment('URL temporaneo per onboarding'),
            'private_key' => $this->string(255)->null()->comment('Private key per iframe (crittografata)'),
            'status' => $this->tinyInteger()->notNull()->comment('Stato: 5=pending_request, 10=onboarding_pending, 25=active, 30=error'),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->createIndex('idx-spoki_account-spoki_account_id', '{{%spoki_account}}', 'spoki_account_id');
        $this->createIndex('idx-spoki_account-status', '{{%spoki_account}}', 'status');

        // Un solo account Spoki per owner_reference.
        $this->createIndex(
            'idx-unique-spoki_account-owner_reference',
            '{{%spoki_account}}',
            'owner_reference',
            true
        );
    }

    public function safeDown(): void
    {
        $this->dropIndex('idx-unique-spoki_account-owner_reference', '{{%spoki_account}}');
        $this->dropIndex('idx-spoki_account-status', '{{%spoki_account}}');
        $this->dropIndex('idx-spoki_account-spoki_account_id', '{{%spoki_account}}');

        $this->dropTable('{{%spoki_account}}');
    }
}
