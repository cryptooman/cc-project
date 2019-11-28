<?php
/**
 *
 */
class ModelAdminsOrdersTypeCancel extends ModelAbstractAdminsOrdersType
{
    protected $_table = 'adminsOrdersTypeCancel';

    function __construct()
    {
        $this->_fields = [
            'orderId'       => [ Vars::UBIGINT, [1] ],
            'cancelOrderId' => [ Vars::UBIGINT, [1] ],
            'created'       => [ Vars::DATETIME ],
            'updated'       => [ Vars::DATETIME ],
        ];
        parent::__construct();
    }

    function getOrderByCancelOrderId(int $cancelOrderId, array $cols = ['*']): array
    {
        return $this->query(
            "SELECT %cols% 
            FROM $this->_table 
            WHERE cancelOrderId = :cancelOrderId",
            ['%cols%' => $cols],
            ['cancelOrderId' => $cancelOrderId]
        )->row();
    }

    function insert(int $orderId, int $cancelOrderId)
    {
        $this->_insert([
            'orderId' => $orderId,
            'cancelOrderId' => $cancelOrderId,
        ]);
    }
}