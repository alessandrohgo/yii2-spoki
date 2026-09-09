<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Models;

use Exception;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\helpers\VarDumper;

/**
 * Modello per la tabella "spoki_account".
 *
 * `owner_reference` è un riferimento opaco, deciso dall'app ospite, a chi possiede l'account
 * Spoki (un id utente, una stringa, qualunque cosa l'host usi per identificare i propri
 * clienti) — il modulo non presuppone una tabella "user" con un certo schema.
 *
 * @property int $id
 * @property string $owner_reference
 * @property string|null $email
 * @property string|null $email_iframe
 * @property int|null $spoki_account_id
 * @property string|null $api_key
 * @property string|null $onboarding_url
 * @property string|null $private_key
 * @property int $status
 * @property int $created_at
 * @property int $updated_at
 */
class SpokiAccount extends ActiveRecord
{
    public const STATUS_PENDING_REQUEST = 5;
    public const STATUS_ONBOARDING_PENDING = 10;
    public const STATUS_ONBOARDING_CONFIRM = 15;
    public const STATUS_QUEUED_JOB = 20;
    public const STATUS_ACTIVE = 25;
    public const STATUS_ERROR = 30;

    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%spoki_account}}';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['owner_reference'], 'required'],
            [['owner_reference'], 'string', 'max' => 255],
            [['spoki_account_id', 'status', 'created_at', 'updated_at'], 'integer'],
            [['onboarding_url', 'private_key'], 'string', 'max' => 255],
            [['api_key', 'email', 'email_iframe'], 'string', 'max' => 255],
            [['status'], 'in', 'range' => [self::STATUS_PENDING_REQUEST, self::STATUS_ONBOARDING_PENDING, self::STATUS_ONBOARDING_CONFIRM, self::STATUS_QUEUED_JOB, self::STATUS_ACTIVE, self::STATUS_ERROR]],
            [['owner_reference'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'owner_reference' => Yii::t('app', 'Owner Reference'),
            'spoki_account_id' => Yii::t('app', 'Spoki Account ID'),
            'api_key' => Yii::t('app', 'API Key'),
            'onboarding_url' => Yii::t('app', 'Onboarding URL'),
            'private_key' => Yii::t('app', 'Private Key'),
            'status' => Yii::t('app', 'Status'),
            'email' => Yii::t('app', 'Email'),
            'email_iframe' => Yii::t('app', 'Email per Iframe'),
            'created_at' => Yii::t('app', 'Creato Il'),
            'updated_at' => Yii::t('app', 'Aggiornato Il'),
        ];
    }

    /**
     * {@inheritdoc}
     * @return SpokiAccountQuery the active query used by this AR class.
     */
    public static function find(): SpokiAccountQuery
    {
        return new SpokiAccountQuery(get_called_class());
    }

    /**
     * Verifica se l'account è in stato pending_request.
     */
    public function isPendingRequest(): bool
    {
        return $this->status === self::STATUS_PENDING_REQUEST;
    }

    /**
     * Verifica se l'account è in stato onboarding_pending.
     */
    public function isOnboardingPending(): bool
    {
        return $this->status === self::STATUS_ONBOARDING_PENDING;
    }

    /**
     * Verifica se l'onboarding è stato confermato.
     */
    public function isOnboardingConfirmed(): bool
    {
        return $this->status === self::STATUS_ONBOARDING_CONFIRM;
    }

    /**
     * Verifica se il job finale è in coda.
     */
    public function isQueuedJob(): bool
    {
        return $this->status === self::STATUS_QUEUED_JOB;
    }

    /**
     * Verifica se l'account è attivo.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Verifica se l'account è in errore.
     */
    public function isError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    /**
     * Spoki attivo: esiste uno `spoki_account` per l'owner con stato {@see self::STATUS_ACTIVE}.
     */
    public static function isActiveForOwner(string $ownerReference): bool
    {
        return self::find()
            ->byOwnerReference($ownerReference)
            ->byStatus(self::STATUS_ACTIVE)
            ->exists();
    }

    /**
     * Lista stati disponibili.
     */
    public static function statusList(): array
    {
        return [
            self::STATUS_PENDING_REQUEST => Yii::t('app', 'Richiesta attivazione in attesa'),
            self::STATUS_ONBOARDING_PENDING => Yii::t('app', 'Onboarding in attesa'),
            self::STATUS_ONBOARDING_CONFIRM => Yii::t('app', 'Onboarding confermato'),
            self::STATUS_QUEUED_JOB => Yii::t('app', 'Job finale in coda'),
            self::STATUS_ACTIVE => Yii::t('app', 'Attivo'),
            self::STATUS_ERROR => Yii::t('app', 'Errore'),
        ];
    }

    public static function import(array $data): ?SpokiAccount
    {
        $spokiAccount = new SpokiAccount();
        $spokiAccount->owner_reference = $data['owner_reference'];
        $spokiAccount->email = $data['email'] ?? null;
        $spokiAccount->email_iframe = $data['email_iframe'] ?? null;
        $spokiAccount->spoki_account_id = $data['spoki_account_id'] ?? null;
        $spokiAccount->api_key = $data['api_key'] ?? null;
        $spokiAccount->onboarding_url = $data['onboarding_url'] ?? null;
        $spokiAccount->status = $data['status'];

        try {
            $response = $spokiAccount->save();
            if ($response === false) {
                Yii::error('Errore di validazione spoki account ' . VarDumper::dumpAsString($spokiAccount->getErrors()));

                return null;
            }
        } catch (Exception $e) {
            Yii::error('Errore durante la creazione di un account spoki, error ' . $e->getMessage(), __METHOD__);

            return null;
        }

        return $spokiAccount;
    }
}
