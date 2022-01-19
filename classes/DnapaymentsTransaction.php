<?php

class DnapaymentsTransaction extends ObjectModel {
    
    public $status;
    public $id_customer;
    public $id_cart;
    public $id_order;
    public $dnaOrderId;
    public $id_transaction;
    public $amount;
    public $currency;
    public $date_add;
    public $date_upd;

    public static $definition = [
        'table' => 'dnapayments_transactions',
        'primary' => 'id',
        'fields' => [
            'status' => [
                'type' => self::TYPE_INT,
                'size' => 2
            ],
            'id_customer' => [
                'type' => self::TYPE_INT,
                'size' => 11,
                'validate' => 'isunsignedInt'
            ],
            'id_cart' => [
                'type' => self::TYPE_INT,
                'size' => 11,
                'validate' => 'isunsignedInt'
            ],
            'id_order' => [
                'type' => self::TYPE_INT,
                'size' => 11,
                'validate' => 'isunsignedInt'
            ],
            'dnaOrderId' => [
                'type' => self::TYPE_STRING,
                'size' => 100
            ],
            'id_transaction' => [
                'type' => self::TYPE_STRING,
                'size' => 100
            ],
            'amount' => [
                'type' => self::TYPE_FLOAT,
                'validate' => 'isFloat'
            ],
            'currency' => [
                'type' => self::TYPE_STRING,
                'size' => 10
            ],
            'date_add' => [
                'type' => self::TYPE_DATE
            ],
            'date_upd' => [
                'type' => self::TYPE_DATE
            ]
        ]
    ];

    public function isExists() {
        return !empty($this->id) && !empty($this->id_transaction);
    }

    public function isCompleted() {
        return $this->status == (int)Configuration::get('PS_OS_PAYMENT') ||
            $this->status == (int)Configuration::get('PS_OS_ERROR');
    }

    public function getDnapaymentsTransactionByCart($id_cart)
    {
        $query = new DbQuery();

        $query->select('*')
            ->from($this->table)
            ->where('id_cart = ' . (int)$id_cart);

        $result = Db::getInstance()->getRow($query->build());
        if ($result == false) {
            return $this;
        }

        $this->hydrate($result);

        return $this;
    }

    public function getDnapaymentsTransactionByDnaOrderId($dnaOrderId)
    {
        $query = new DbQuery();

        $query->select('*')
            ->from($this->table)
            ->where('dnaOrderId = "' . $dnaOrderId . '"');

        $result = Db::getInstance()->getRow($query->build());

        if ($result == false) {
            return $this;
        }

        $this->hydrate($result);

        return $this;
    }
}
