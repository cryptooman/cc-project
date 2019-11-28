<?php
/**
 *
 */
class ModelTest extends Model
{
    protected $_table = 'test';

    function __construct()
    {
        $this->_fields = [
            'id'    => [ Vars::UINT ],
            //'name'  => [ Vars::STR, [0,100], function($v) { return Str::unquote($v); } ],
            'name'  => [ Vars::REGX, ['!.+!'] ],
        ];
        parent::__construct();
    }

    function insert(int $id, string $name): int
    {
        //$q = $this->query(
        //    "INSERT INTO $this->_table
        //    SET %set%",
        //    ['%set%' => $this->filter([
        //        'id' => $id,
        //        'name' => $name,
        //    ])]
        //);
        $q = $this->query(
            "INSERT INTO $this->_table
            SET id = :id, name = :name",
            $this->filter([
                'id' => $id,
                'name' => $name,
            ])
        );
        _pr($q->strf());
        $q->exec();
        $lastId = $q->lastId();
        return $lastId;
    }
}