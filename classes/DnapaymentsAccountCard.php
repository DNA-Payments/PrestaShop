<?php

class DnapaymentsAccountCard extends ObjectModel {
    
    public $accountId;
    public $cardTokenId;
    public $cardPanStarred;
    public $cardSchemeId;
    public $cardSchemeName;
    public $cardAlias;
    public $cardExpiryDate;
    public $cardholderName;
    public $date_add;
    public $date_upd;

    public static $definition = [
        'table' => 'dnapayments_account_cards',
        'primary' => 'id',
        'fields' => [
            'accountId' => [
                'type' => self::TYPE_STRING,
                'size' => 100
            ],
            'cardTokenId' => [
                'type' => self::TYPE_STRING,
                'size' => 100
            ],
            'cardPanStarred' => [
                'type' => self::TYPE_STRING,
                'size' => 100
            ],
            'cardSchemeId' => [
                'type' => self::TYPE_STRING,
                'size' => 100
            ],
            'cardSchemeName' => [
                'type' => self::TYPE_STRING,
                'size' => 100
            ],
            'cardAlias' => [
                'type' => self::TYPE_STRING,
                'size' => 100
            ],
            'cardExpiryDate' => [
                'type' => self::TYPE_STRING,
                'size' => 10
            ],
            'cardholderName' => [
                'type' => self::TYPE_STRING,
                'size' => 100
            ],
            'date_add' => [
                'type' => self::TYPE_DATE
            ],
            'date_upd' => [
                'type' => self::TYPE_DATE
            ]
        ]
    ];

    public function getCard($accountId, $cardTokenId) {
        $query = new DbQuery();

        $query->select('*')
            ->from($this->table)
            ->where('accountId = "' . $accountId . '" AND cardTokenId = "' . $cardTokenId . '"')
            ->orderBy('id');

        $result = Db::getInstance()->getRow($query->build());

        if ($result == false) {
            $this->accountId = $accountId;
            $this->cardTokenId = $cardTokenId;
            return $this;
        }

        $this->hydrate($result);

        return $this;
    }

    public static function getAccountCards($accountId) {
        $query = new DbQuery();

        $query->select('*')
            ->from('dnapayments_account_cards')
            ->where('accountId = "' . $accountId . '"')
            ->orderBy('id');

        $result = Db::getInstance()->ExecuteS($query->build());

        return $result;
    }
}
