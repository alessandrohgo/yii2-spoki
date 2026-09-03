<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * Modello di ricerca admin per {@see SpokiAccount}.
 */
class SpokiAccountSearch extends SpokiAccount
{
    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['id', 'spoki_account_id', 'status'], 'integer'],
            [['owner_reference', 'email', 'email_iframe', 'api_key', 'onboarding_url', 'private_key'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios(): array
    {
        return Model::scenarios();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function search(array $params): ActiveDataProvider
    {
        $query = SpokiAccount::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => ['id' => SORT_DESC],
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id' => $this->id,
            'spoki_account_id' => $this->spoki_account_id,
            'status' => $this->status,
        ]);

        $query->andFilterWhere(['like', 'owner_reference', $this->owner_reference])
            ->andFilterWhere(['like', 'email', $this->email])
            ->andFilterWhere(['like', 'email_iframe', $this->email_iframe])
            ->andFilterWhere(['like', 'api_key', $this->api_key])
            ->andFilterWhere(['like', 'onboarding_url', $this->onboarding_url])
            ->andFilterWhere(['like', 'private_key', $this->private_key]);

        return $dataProvider;
    }
}
