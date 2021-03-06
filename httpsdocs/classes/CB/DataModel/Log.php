<?php

namespace CB\DataModel;

use CB\DB;

class Log extends Base
{
    /**
     * available table fields
     *
     * associative array of fieldName => type
     * that is also used for trivial validation of input values
     *
     * @var array
     */
    protected static $tableFields = array(
        'id' => 'int'
        ,'object_id' => 'int'
        ,'object_pid' => 'int'
        ,'user_pid' => 'int'
        ,'action_type' => 'enum'
        ,'action_time' => 'time'
        ,'data' => 'text'
        ,'activity_data_db' => 'text'
        ,'activity_data_solr' => 'text'
    );

    /**
     * add a record
     * @param  array $p associative array with table field values
     * @return int   created id
     */
    public static function create($p)
    {
        parent::create($p);

        //prepare params
        $params = array(
            empty($p['object_id']) ? null : $p['object_id']
            ,empty($p['object_pid']) ? null : $p['object_pid']
            ,empty($p['user_id']) ? null : $p['user_id']
            ,empty($p['action_type']) ? null : $p['action_type']
            ,empty($p['data']) ? null : $p['data']
            ,empty($p['data']) ? null : $p['data']
            ,empty($p['activity_data_db']) ? null : $p['activity_data_db']
            ,empty($p['activity_data_solr']) ? null : $p['activity_data_solr']
        );

        //add database record
        $sql = 'INSERT INTO action_log (
              `object_id`
              ,`object_pid`
              ,`user_id`
              ,`action_type`
              ,`data`
              ,`activity_data_db`
              ,`activity_data_solr`
            ) VALUES ($1, $2, $3, $4, $5, $6, $7)';

        DB\dbQuery($sql, $params) or die(DB\dbQueryError());

        $rez = DB\dbLastInsertId();

        return $rez;
    }

    /**
     * read a record from db by id
     * @param  int   $id
     * @return array | null
     */
    public static function read($id)
    {
        $rez= null;

        parent::read($p);

        //read
        $res = DB\dbQuery(
            'SELECT *
            FROM action_log
            WHERE id = $1',
            $id
        ) or die(DB\dbQueryError());

        if ($r = $res->fetch_assoc()) {
            $rez = $r;
        }
        $res->close();

        return $rez;
    }

    /**
     * update a record
     * @param  array   $p array with properties
     * @return boolean
     */
    public static function update($p)
    {
    }

    /**
     * delete a record
     * @param  int     $id
     * @return boolean
     */
    public static function delete($id)
    {
    }
}
