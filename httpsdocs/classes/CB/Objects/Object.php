<?php
namespace CB\Objects;

use CB\Config;
use CB\DB;
use CB\Util;
use CB\L;
use CB\Log;
use CB\Security;

/**
 * class for generic casebox objects
 */

class Object
{
    /**
     * object id
     * @var int
     */
    protected $id = null;

    /**
     * protected flag to check if object loaded where needed
     * @var boolean
     */
    protected $loaded = false;

    /**
     * variable used to load template for this object
     * @var boolean
     */
    public $loadTemplate = true;

    /**
     * object template
     * @var CB\Template object
     */
    protected $template = null;

    /**
     * object data
     * @var array
     */
    protected $data = array();

    public function __construct($id = null, $loadTemplate = true)
    {
        if (is_numeric($id)) {
            $this->id = $id;
        }
        $this->loadTemplate = $loadTemplate;
    }

    /**
     * create an object with specified params
     * @param  array $p object properties
     * @return int   created id
     */
    public function create($p = false)
    {
        if ($p !== false) {
            if (array_key_exists('id', $p)) {
                if (is_numeric($p['id'])) {
                    $this->id = $p['id'];
                } else {
                    $this->id = null;
                    unset($p['id']);
                }
            }

            $this->data = $p;
            unset($this->linearData);

            $this->template = null;
            if (!empty($this->data['template_id']) && $this->loadTemplate) {
                $this->template = \CB\Templates\SingletonCollection::getInstance()->getTemplate($this->data['template_id']);
            }
        }

        //check if there is defaultPid specified in template config
        if (!empty($this->template)) {
            $templateData = $this->template->getData();

            if (!empty($templateData['cfg']['defaultPid'])) {
                $this->data['pid'] = $templateData['cfg']['defaultPid'];
            }
        }

        \CB\fireEvent('beforeNodeDbCreate', $this);

        $p = &$this->data;

        if (!Security::canCreateActions($p['pid'])) {
            throw new \Exception(L\get('Access_denied'));
        }

        // check input params
        if (!isset($p['pid'])) {
            throw new \Exception("No pid specified for object creation", 1);
        }

        if (empty($p['name'])) {
            throw new \Exception("No name specified for object creation", 1);
        }

        // we admit object creation without template id
        // if (empty($p['template_id']) || !is_numeric($p['template_id'])) {
        //     throw new \Exception("No template_id specified for object creation", 1);
        // }

        if (empty($p['pid'])) {
            $p['pid'] = null;
        }

        $draftPid = empty($p['draftPid']) ? null : $p['draftPid'];
        $isDraft = intval(!empty($draftPid) || !empty($p['draft']));

        if (empty($p['date_end'])) {
            $p['date_end'] = null;
        }

        if (empty($p['tag_id'])) {
            $p['tag_id'] = null;
        }

        if (empty($p['cid'])) {
            $p['cid'] = $_SESSION['user']['id'];
        }
        if (empty($p['oid'])) {
            $p['oid'] = $p['cid'];
        }

        if (empty($p['cdate'])) {
            $p['cdate'] = null;
        }

        DB\dbQuery(
            'INSERT INTO tree (
                id
                ,pid
                ,draft
                ,draft_pid
                ,template_id
                ,tag_id
                ,target_id
                ,name
                ,date
                ,date_end
                ,size
                ,cfg
                ,cid
                ,oid
                ,cdate
                ,`system`
                ,updated
            )
            VALUES (
                $1
                ,$2
                ,$3
                ,$4
                ,$5
                ,$6
                ,$7
                ,$8
                ,$9
                ,$10
                ,$11
                ,$12
                ,$13
                ,$14
                ,COALESCE($15, CURRENT_TIMESTAMP)
                ,$16
                ,1
            )',
            array(
                $this->id
                ,$p['pid']
                ,$isDraft
                ,$draftPid
                ,$p['template_id']
                ,$p['tag_id']
                ,@$p['target_id']
                ,$p['name']
                ,@$p['date']
                ,@$p['date_end']
                ,@$p['size']
                ,@Util\jsonEncode($p['cfg'])
                ,@$p['cid']
                ,@$p['oid']
                ,@$p['cdate']
                ,@intval($p['system'])
            )
        ) or die(DB\dbQueryError());

        $this->id = DB\dbLastInsertId();
        $p['id'] = $this->id;

        $this->createCustomData();

        $this->checkDraftChilds();

        //load the object from db to have all its created data
        $this->load();

        //fire create event
        \CB\fireEvent('nodeDbCreate', $this);

        // log the action
        $logParams = array(
            'type' => 'create'
            ,'new' => $this
        );

        Log::add($logParams);

        return $this->id;
    }

    /**
     * internal function used by create method for creating custom data
     * @return void
     */
    protected function createCustomData()
    {
        $p = &$this->data;

        $p['data'] = Util\toJSONArray(@$p['data']);
        $p['sys_data'] = Util\toJSONArray(@$p['sys_data']);
        $sd = &$p['sys_data'];

        //add creator as follower by default, but not for folder template
        if (empty($sd['fu'])) {
            $sd['fu'] = [];
        }

        if ($p['template_id'] != Config::get('default_folder_template')) {
            if (!in_array($p['cid'], $sd['fu'])) {
                $sd['fu'][] = intval($p['cid']);
            }
        }

        //filter fields
        $this->filterHTMLFields($p['data']);

        $this->collectSolrData();

        DB\dbQuery(
            'INSERT INTO objects (id ,`data`, `sys_data`)
            VALUES($1, $2, $3)
            ON DUPLICATE KEY
            UPDATE  `data` = $2,`sys_data` = $3',
            array(
                $this->id
                ,Util\jsonEncode($p['data'])
                ,Util\jsonEncode($p['sys_data'])
            )
        ) or die(DB\dbQueryError());

    }

    /**
     * method to check if this object has a draftId set in properties
     * and check if there exists other objects that point to this draftId
     *
     * @return void
     */
    protected function checkDraftChilds()
    {
        if (empty($this->data['draftId'])) {
            return;
        }

        $cildren = array();

        $res = DB\dbQuery(
            'SELECT id
            FROM tree
            WHERE draft = 1
                AND draft_pid = $1',
            $this->data['draftId']
        ) or die(DB\dbQueryError());

        while ($r = $res->fetch_assoc()) {
            $children[] = $r['id'];
        }
        $res->close();

        if (!empty($children)) {
            DB\dbQuery(
                'UPDATE tree
                SET draft = 0
                    ,draft_pid = null
                    ,pid = $1
                    ,updated = 1
                WHERE id in (' . implode(',', $children) . ')',
                $this->id
            ) or die(DB\dbQueryError());
        }
    }

    /**
     * load object data into $this->data
     * @param  int   $id
     * @return array loaded data
     */
    public function load($id = null)
    {
        if (!is_numeric($id)) {
            if (!is_numeric($this->id)) {
                throw new \Exception("No object id specified for load", 1);
            }
            $id = $this->id;
        } else {
            $this->id = $id;
        }

        $this->data = array();
        $this->template = null;
        unset($this->linearData);

        $res = DB\dbQuery(
            'SELECT t.*
                ,ti.pids
                ,ti.path
                ,ti.case_id
                ,ti.acl_count
                ,ti.security_set_id
            FROM tree t
            JOIN tree_info ti
                ON t.id = ti.id
            WHERE t.id = $1',
            $id
        ) or die(DB\dbQueryError());

        if ($r = $res->fetch_assoc()) {
            $r['cfg'] = Util\toJSONArray($r['cfg']);
            $this->data = $r;
            if (!empty($this->data['template_id']) && $this->loadTemplate) {
                $this->template = \CB\Templates\SingletonCollection::getInstance()->getTemplate($this->data['template_id']);
            }
        }
        $res->close();

        $this->loadCustomData();

        $this->loaded = true;

        return $this->data;
    }

    /**
     * load custom data for $this->id
     *
     * in this partucular case, for objects, this method sets
     * data into $this->data
     * @return void
     */
    protected function loadCustomData()
    {
        /* load custom data from objects table */
        $res = DB\dbQuery(
            'SELECT data, sys_data
            FROM objects
            WHERE id = $1',
            $this->id
        ) or die(DB\dbQueryError());

        if ($r = $res->fetch_assoc()) {
            $this->data['data'] = Util\toJSONArray($r['data']);
            $this->data['sys_data'] = Util\toJSONArray($r['sys_data']);
            unset($this->linearData);
        }
        $res->close();
    }

    /**
     * update object
     * @param  array   $p optional properties. If not specified then $this-data is used
     * @return boolean
     */
    public function update($p = false)
    {
        if ($p !== false) {
            $this->data = $p;
            unset($this->linearData);

            if (array_key_exists('id', $p)) {
                $this->id = $p['id'];
            }
            $this->template = null;
            if (!empty($this->data['template_id']) && $this->loadTemplate) {
                $this->template = \CB\Templates\SingletonCollection::getInstance()->getTemplate($this->data['template_id']);
            }
        }
        if (!is_numeric($this->id)) {
            throw new \Exception("No object id specified for update", 1);
        }

        //load current object from db into a variable to be passed to log and events
        $this->oldObject = clone $this;
        $this->oldObject->load($this->id);

        \CB\fireEvent('beforeNodeDbUpdate', $this);

        $p = &$this->data;

        if (empty($p['cid'])) {
            $p['cid'] = $_SESSION['user']['id'];
        }

        if (empty($p['oid'])) {
            $p['oid'] = $p['cid'];
        }

        $tableFields = array(
            'pid'
            ,'user_id'
            ,'system'
            ,'template_id'
            ,'tag_id'
            ,'target_id'
            ,'name'
            ,'date'
            ,'date_end'
            ,'size'
            ,'cfg'
            ,'oid'
            ,'did'
            ,'dstatus'
        );
        $saveFields = array();
        $saveValues = array($this->id, $_SESSION['user']['id']);
        $params = array(
            '`uid` = $2'
            ,'`udate` = CURRENT_TIMESTAMP'
            ,'`draft` = 0'
            ,'updated = 1'
        );
        $i = 3;
        foreach ($tableFields as $fieldName) {
            if (array_key_exists($fieldName, $p) && ($p[$fieldName] !== 'id')) {
                $saveFields[] = $fieldName;
                $saveValues[] = (is_scalar($p[$fieldName]) || is_null($p[$fieldName]))
                    ? $p[$fieldName]
                    : Util\jsonEncode($p[$fieldName]);
                $params[] = "`$fieldName` = \$$i";
                $i++;
            }
        }
        if (!empty($saveFields)) {
            DB\dbQuery(
                'UPDATE tree
                SET '.implode(',', $params).'
                WHERE id = $1',
                $saveValues
            ) or die(DB\dbQueryError());
        }

        DB\dbQuery(
            'call `p_mark_all_child_drafts_as_active`($1)',
            $this->id
        ) or die(DB\dbQueryError());

        $this->updateCustomData();

        // set/update this object to cache
        \CB\Cache::set('Objects[' . $this->id . ']', $this);

        \CB\fireEvent('nodeDbUpdate', $this);

        // log the action
        $logParams = array(
            'type' => 'update'
            ,'old' => $this->oldObject
            ,'new' => $this
        );

        Log::add($logParams);

        return true;
    }

    /**
     * update objects custom data
     * @return boolean
     */
    protected function updateCustomData()
    {
        $d = &$this->data;

        if (!empty($this->template)) {
            $templateData = $this->template->getData();
        }

        if (empty($d['data'])) {
            $d['data'] = array();
        }
        if (empty($d['sys_data'])) {
            $d['sys_data'] = array();
        }

        $this->filterHTMLFields($d['data']);

        $this->collectSolrData();

        unset($this->linearData);

        @DB\dbQuery(
            'INSERT INTO objects
            (id, `data`, sys_data)
            VALUES ($1, $2, $3)
            ON DUPLICATE KEY UPDATE
                `data` = $2
                ,sys_data = $3',
            array(
                $d['id']
                ,Util\jsonEncode($d['data'])
                ,Util\jsonEncode($d['sys_data'])
            )
        ) or die(DB\dbQueryError());
        /* end of updating object properties into the db */

        return true;
    }

    /**
     * get objects system data (sysData field)
     * return sysData form this class if loaded or reads directly from db
     *
     * @return array
     */
    public function getSysData()
    {
        $rez = array();

        if ($this->loaded) {
            $rez = Util\toJSONArray(@$this->data['sys_data']);
        } else {
            $res = DB\dbQuery(
                'SELECT sys_data
                FROM objects
                WHER id = $1',
                $this->data['id']
            ) or die(DB\dbQueryError());
            if ($r = $res->fetch_assoc()) {
                $rez = Util\toJSONArray($r['sys_data']);
            }
            $res->close();
        }

        return $rez;
    }

    /**
     * update objects system data (sysData field)
     * this method updates data directly and desnt fire update events
     * @param variant $sysData array or json encoded string
     *        if not specified then sysTada from current class will be used for update
     * @return boolean
     */
    public function updateSysData($sysData = false)
    {
        $d = &$this->data;

        if ($sysData !== false) {
            $d['sys_data'] = Util\toJSONArray($sysData);
        }

        $this->collectSolrData();

        @DB\dbQuery(
            'INSERT INTO objects
            (id, sys_data)
            VALUES ($1, $2)
            ON DUPLICATE KEY UPDATE
                sys_data = $2',
            array(
                $d['id']
                ,Util\jsonEncode($d['sys_data'])
            )
        ) or die(DB\dbQueryError());

        //mark the item as updated so that it would be reindexed to solr
        DB\dbQuery(
            'UPDATE tree
            SET updated = (updated | 1)
            WHERE id = $1',
            $d['id']
        ) or die(DB\dbQueryError());

        return true;
    }

    /**
     * get a property from system data of the object (sysData field)
     *
     * @param varchar $propertyName
     *
     * @return variant | null
     */
    public function getSysDataProperty($propertyName)
    {
        $rez = null;

        if (empty($propertyName) || !is_scalar($propertyName)) {
            return $rez;
        }

        $d = $this->getSysData();

        if (isset($d[$propertyName])) {
            $rez = $d[$propertyName];
        }

        return $rez;
    }

    /**
     * update a property system data of the object (sysData field)
     * if value is null the property is unset from sys_data
     *
     * @param varchar $propertyName
     * @param variant $propertyValue
     *
     * @return boolean
     */
    public function setSysDataProperty($propertyName, $propertyValue = null)
    {
        if (empty($propertyName) || !is_scalar($propertyName)) {
            return false;
        }

        $d = $this->getSysData();

        if (is_null($propertyValue)) {
            unset($d[$propertyName]);
        } else {
            $d[$propertyName] = $propertyValue;
        }

        $this->updateSysData($d);

        return true;
    }

    /**
     * method to collect solr data from object data
     * according to template fields configuration
     * and store it in sys_data onder "solr" property
     * @return void
     */
    protected function collectSolrData()
    {
        //iterate template fields and collect fieldnames
        //to be indexed in solr, as well as title fields
        $rez = array();

        $d = &$this->data;
        $sd = &$this->data;

        $tpl = $this->getTemplate();

        $languages = Config::get('languages');

        $defaultLanguage = Config::get('default_language');

        if (!empty($tpl)) {
            $fields = $tpl->getFields();

            //create a list of possible title fields that should be added to solr
            $titleFields = array();//will go into title property
            foreach ($languages as $l) {
                $titleFields[] = 'title_' . $l;
            }

            foreach ($fields as $f) {
                if (empty($f['solr_column_name']) && in_array($f['name'], $titleFields)) {
                    $sfn = $f['name'] . '_t';//solr field name

                    $value = $this->getFieldValue($f['name'], 0);
                    if (!empty($value['value'])) {
                        $rez[$sfn] = $value['value'];
                    }

                } elseif (!empty($f['cfg']['faceting']) || //backward compatible check
                    !empty($f['cfg']['indexed'])
                ) {
                    $values = $this->getFieldValue($f['name']);

                    $resultValue = array();

                    //iterate each duplicate
                    foreach ($values as $v) {
                        $value = $this->prepareValueforSolr($f['type'], $v);
                        if (!empty($value)) {
                            $resultValue[] = $value;
                        }
                    }

                    //check result value
                    //if its a single value then set as is
                    //if multiple values then merge into an array
                    if (!empty($resultValue)) {
                        $finalValue = null;
                        if (sizeof($resultValue) == 1) {
                            $finalValue = array_shift($resultValue);
                        } else {
                            $finalValue = array();
                            foreach ($resultValue as $value) {
                                if (is_array($value)) {
                                    $finalValue = array_merge($finalValue, $value);
                                } else {
                                    $finalValue[] = $value;
                                }

                            }
                        }

                        $rez[$f['solr_column_name']] = $finalValue;
                    }
                }
            }
        }

        // add last comment info if present
        if (!empty($sd['lastComment'])) {
            $rez['comment_user_id'] = $sd['lastComment']['user_id'];
            $rez['comment_date'] = $sd['lastComment']['date'];
        }

        $this->data['sys_data']['solr'] = $rez;
    }

    /**
     * just for update purposes only
     * Should be removed in future
     * @return void
     */
    public function updateSolrData()
    {
        $this->load();
        $this->collectSolrData();
        $this->updateSysData();
    }

    /**
     * prepare a given value for solr according to its type
     * @param  varchar $type  (checkbox,combo,date,datetime,float,html,int,memo,_objects,text,time,timeunits,varchar)
     * @param  variant $value
     * @return variant
     */
    protected function prepareValueforSolr($type, $value)
    {
        if (empty($value) || empty($value['value'])) {
            return null;
        }

        $value = $value['value'];
        switch ($type) {
            case 'boolean': //not used
            case 'checkbox':
                $value = empty($value) ? false : true;
                break;

            case 'date':
            case 'datetime':
                if (!empty($value)) {
                    //check if there is only date, without time
                    if (strlen($value) == 10) {
                        $value .= 'T00:00:00';
                    }

                    if (substr($value, -1) != 'Z') {
                        $value .= 'Z';
                    }

                    if (@$value[10] == ' ') {
                        $value[10] = 'T';
                    }
                }
                break;

                /** time values are stored as seconds representation in solr */
            case 'time':
                if (!empty($value)) {
                    $a = explode(':', $value);
                    @$value = $a[0] * 3600 + $a[1] * 60 + $a[2];
                }
                break;

            case 'combo':
            case 'int':
            case '_objects':

                $arr = Util\toNumericArray($value);
                $value = array();

                //remove zero values
                foreach ($arr as $v) {
                    if (!empty($v)) {
                        $value[] = $v;
                    }
                }

                $value = array_unique($value);

                if (empty($value)) {
                    $value = null;

                } elseif (sizeof($value) == 1) {
                    //set just value if 1 element array
                    $value = array_shift($value);
                }
                break;

            case 'html':
                $value = strip_tags($value);
                break;

        }

        return $value;
    }

    /**
     *  get action flags that a user can do this object
     * @param  int   $userId
     * @return array
     */
    public function getActionFlags($userId = false)
    {
        return array();
    }

    /**
     * delete an object from tree or marks it as deleted
     * @param boolean $permanent Specify true to delete the object permanently.
     *                            Default to false.
     * @return void
     */
    public function delete($permanent = false)
    {
        // we need to load this object before delete
        // for passing it to log and/or events

        if (!is_numeric($this->id)) {
            return;
        }

        if (!$this->loaded) {
            $this->load();
        }
        \CB\fireEvent('beforeNodeDbDelete', $this);

        if ($permanent) {
            DB\dbQuery(
                'DELETE from tree WHERE id = $1',
                $this->id
            ) or die(DB\dbQueryError());
            $solrClient = new \CB\Solr\Client();
            $solrClient->deleteByQuery('id:' . $this->id);
        } else {
            DB\dbQuery(
                'UPDATE tree
                SET did = $2
                    ,dstatus = 1
                    ,ddate = CURRENT_TIMESTAMP
                    ,updated = (updated | 1)
                WHERE id = $1',
                array(
                    $this->id
                    ,$_SESSION['user']['id']
                )
            ) or die(DB\dbQueryError());
            DB\dbQuery(
                'CALL p_mark_all_childs_as_deleted($1, $2)',
                array(
                    $this->id
                    ,$_SESSION['user']['id']
                )
            ) or die(DB\dbQueryError());
        }

        $this->deleteCustomData($permanent);

        \CB\fireEvent('nodeDbDelete', $this);

        // log the action
        $logParams = array(
            'type' => 'delete'
            ,'old' => $this
        );

        //dont add log action if permanently deleted
        if (!$permanent) {
            Log::add($logParams);
        }
    }

    /**
     * delete custom data for an object
     *
     * use this method (overwrite it) for descendant classes
     * when there is need to delete custom data on object delete
     * @return coid
     */
    protected function deleteCustomData()
    {

    }

    /**
     * restore a deleted object
     * @return void
     */
    public function restore()
    {
        \CB\fireEvent('beforeNodeDbRestore', $this);

        DB\dbQuery(
            'UPDATE tree
            SET did = NULL
                ,dstatus = 0
                ,ddate = NULL
                ,updated = (updated | 1)
            WHERE id = $1',
            $this->id
        ) or die(DB\dbQueryError());

        DB\dbQuery('CALL p_mark_all_childs_as_active($1)', $this->id) or die(DB\dbQueryError());

        $this->restoreCustomData();

        \CB\fireEvent('nodeDbRestore', $this);

        // we need to load this object on restore
        // for passing it to log and/or events
        if (!$this->loaded) {
            $this->load();
        }

        // log the action
        $logParams = array(
            'type' => 'restore'
            ,'new' => $this
        );

        Log::add($logParams);

    }

    /**
     * restore custom data for an object
     *
     * use this method (overwrite it) for descendant classes
     * when there is need to restore custom data on object restore
     * @return coid
     */
    protected function restoreCustomData()
    {

    }

    /**
     * return the owner of the object
     * @param int $userId
     */
    public function getOwner()
    {
        $d = &$this->data;

        return @Util\coalesce($d['oid'], $d['cid']);
    }

    /**
     * check if given user is owner of the task
     * @param int $userId
     */
    public function isOwner($userId = false)
    {
        $d = &$this->data;

        if ($userId === false) {
            $userId = $_SESSION['user']['id'];
        }

        return ($d['cid'] == $userId);
    }

    /**
     * get a field value from current objects data ($this->data)
     *
     * This function return an array of values for duplicate fields
     *
     * @param  varchar $fieldName  field name
     * @param  integer $valueIndex optional value duplication index. default false
     * @return array
     */
    public function getFieldValue($fieldName, $valueIndex = false)
    {
        $rez = array();
        $ld = $this->getAssocLinearData();
        if (!empty($ld[$fieldName])) {
            $rez = $ld[$fieldName];
        }
        if ($valueIndex !== false) {
            $rez = @$rez[$valueIndex];
        }

        return $rez;
    }

    /**
     * get object data
     *
     * @return array object properties
     */
    public function getData()
    {
        // Its not correct to automatically load data
        // because there could be other data set through update or create
        // and by autoloading will override the data set.
        // load method should be called manually when needed
        //
        // if (!$this->loaded && !empty($this->id)) {
        //     $this->load();
        // }
        //
        return $this->data;
    }

    /**
     * get solr data property
     *
     * @return array
     */
    public function getSolrData()
    {
        $rez = array();
        if (!empty($this->data['sys_data']['solr'])) {
            $rez = $this->data['sys_data']['solr'];
        }

        return $this->data;
    }

    /**
     * get linear array of properties of object properties
     * @param boolean $sorted true to sort data according to template fields order
     * @param array   $data   template properties
     */
    public function getLinearData($sorted = false)
    {
        $paramName = 'linearData' . ($sorted ? 'sorted' : '');
        if (!empty($this->$paramName)) {
            return $this->$paramName;
        }

        $this->$paramName = $this->getLinearNodesData($this->data['data'], $sorted);

        return $this->$paramName;
    }

    /**
     * get an associative linear array of field values
     *
     * @param array $data template properties
     */
    public function getAssocLinearData()
    {
        $rez = array();
        $linearData = $this->getLinearData();
        foreach ($linearData as $field) {
            $value = array_intersect_key(
                $field,
                array(
                    'value' => 1
                    ,'info' => 1
                    ,'files' => 1
                    ,'cond' => 1
                )
            );

            $rez[$field['name']][] = $value;
        }

        return $rez;
    }

    /**
     * private function used to sort an array(using php usort function) of field elements
     * according to their template order from template
     * @param  array $a
     * @param  array $b
     * @return int
     */
    protected function fieldsArraySorter($a, $b)
    {
        if (!empty($this->template)) {
            $o1 = $this->template->getFieldOrder($a['name']);
            $o2 = $this->template->getFieldOrder($b['name']);
            if ($o1 < $o2) {
                return -1;
            } elseif ($o1 > $o2) {
                return 1;
            }
        }

        return 0;
    }

    protected function getLinearNodesData(&$data, $sorted = false)
    {
        $rez = array();
        if (empty($data)) {
            return $rez;
        }

        foreach ($data as $fieldName => $fieldValue) {
            if ($this->isFieldValue($fieldValue)) {
                $fieldValue = array($fieldValue);
            }

            foreach ($fieldValue as $fv) {
                $value = array('name' => $fieldName);
                if (is_scalar($fv) ||
                    is_null($fv)
                ) {
                    $value['value'] = $fv;
                } else {
                    $value = array_merge($value, $fv);
                }
                $rez[] = $value;
            }
        }

        if ($sorted) {
            usort($rez, array($this, 'fieldsArraySorter'));
        }

        $sortedRez = array();
        //iterate fields and insert childs if present
        foreach ($rez as $fv) {
            $sortedRez[] = $fv;
            if (!empty($fv['childs'])) {
                $sortedRez = array_merge($sortedRez, $this->getLinearNodesData($fv['childs'], $sorted));
            }
        }

        return $sortedRez;
    }

    /**
     * set object data
     *
     * @param array $data template properties
     */
    public function setData($data)
    {
        $this->data = $data;
        unset($this->linearData);

        if (array_key_exists('id', $data)) {
            $this->id = $data['id'];
            $this->loaded = true;
        }

        if (!empty($this->data['data'])) {
            $this->filterHTMLFields($this->data['data']);
        }
    }

    /**
     * get object template property
     *
     * @return array object properties
     */
    public function getTemplate()
    {
        if (empty($this->template) && $this->loadTemplate && !empty($this->data['template_id'])) {
            $this->template = \CB\Templates\SingletonCollection::getInstance()->getTemplate($this->data['template_id']);
        }

        return $this->template;
    }

    /**
     * get template name of the object
     * @return varchar | null
     */
    public function getTemplateName()
    {
        $template = $this->getTemplate();
        if (empty($template)) {
            return null;
        }

        $templateData = $template->getData();

        return @$templateData['name'];
    }

    /**
     * get name of the object corresponding to current user language
     * @param  varchar $language
     * @return varchar
     */
    public function getName($language = false)
    {
        $d = &$this->data;

        $rez = $d['name'];

        if ($language === false) {
            $language = Config::get('user_language');
        }

        if (is_string($language) && !empty($d['sys_data']['solr']['title_' . $language . '_t'])) {
            $rez = $d['sys_data']['solr']['title_' . $language . '_t'];
        }

        return $rez;
    }

    /**
     * get html safe name
     * @param  varchar $language
     * @return varchar
     */
    public function getHtmlSafeName($language = false)
    {
        $rez = $this->getName($language);

        $rez = htmlspecialchars($rez, ENT_COMPAT);

        return $rez;
    }

    /**
     * get object type from template
     *
     * @return varchar
     */
    public function getType()
    {
        $template = $this->getTemplate();
        if (empty($template)) {
            return null;
        }
        $data = $template->getData();

        return @$data['type'];
    }

    /**
     * detect if a given value is a generic field value
     * from json array stored in data fields
     *
     * @param  variant $value
     * @return boolean
     */
    public static function isFieldValue($value)
    {
        if (is_scalar($value) ||
            is_null($value)
        ) {
            return true;
        }
        // analize array values
        if (is_array($value)) {
            // non associative array
            if (array_values($value) === $value) {
                return false;
            } else { //associative array
                $keys = array_keys($value);
                $diff = array_diff($keys, array('name', 'value', 'info', 'files', 'childs', 'cond'));

                return empty($diff);
            }
        }

        // not detected case;
        return null;
    }
    /**
     * copy an object to $pid or over $targetId
     *
     * better way to copy an object over another one is to delete the target,
     * but this could be very dangerous. We could delete required/important data
     * so i suggest to just mark overwriten object with dstatus = 3.
     * But in this situation appears another problem with child nodes.
     * Childs should be moved to new parent.
     *
     * @param  int $pid      if not specified then will be set to pid of targetId
     * @param  int $targetId
     * @return int the id of copied object
     */
    public function copyTo($pid = false, $targetId = false)
    {
        // check input params
        if (!is_numeric($this->id) ||
            (!is_numeric($pid) && !is_numeric($targetId))
        ) {
            return false;
        }

        /* security check */
        if (!\CB\Security::canRead($this->id)) {
            return false;
        }
        /* end of security check */

        if (is_numeric($targetId)) {
            /* target security check */
            if (!\CB\Security::canWrite($targetId)) {
                return false;
            }
            /* end of target security check */
            // marking overwriten object with dstatus = 3
            DB\dbQuery(
                'UPDATE tree
                SET  updated = 1
                    ,dstatus = 3
                    ,did = $2
                WHERE id = $1',
                array(
                    $targetId
                    ,$_SESSION['user']['id']
                )
            ) or die(DB\dbQueryError());

            //get pid from target if not specified
            $res = DB\dbQuery(
                'SELECT pid FROM tree WHERE id = $1',
                $targetId
            ) or die(DB\dbQueryError());

            if ($r = $res->fetch_assoc()) {
                $pid = $r['pid'];
            }
            $res->close();
        } else {
            /* pid security check */
            if (!\CB\Security::canWrite($pid)) {
                return false;
            }
            /* end of pid security check */
        }

        /* check again if we have pid set
            It can be unset when not existent $targetId is specified
        */
        if (!is_numeric($pid)) {
            return false;
        }

        // copying the object to $pid

        DB\dbQuery(
            'INSERT INTO `tree`
                (`id`
                ,`pid`
                ,`user_id`
                ,`system`
                ,`type`
                ,`template_id`
                ,`tag_id`
                ,`target_id`
                ,`name`
                ,`date`
                ,`date_end`
                ,`size`
                ,`is_main`
                ,`cfg`
                ,`inherit_acl`
                ,`cid`
                ,`cdate`
                ,`uid`
                ,`udate`
                ,`updated`
                ,`oid`
                ,`did`
                ,`ddate`
                ,`dstatus`)
            SELECT
                NULL
                ,$2
                ,`user_id`
                ,`system`
                ,`type`
                ,`template_id`
                ,`tag_id`
                ,`target_id`
                ,`name`
                ,`date`
                ,`date_end`
                ,`size`
                ,`is_main`
                ,`cfg`
                ,`inherit_acl`
                ,`cid`
                ,`cdate`
                ,$3
                ,CURRENT_TIMESTAMP
                ,1
                ,`oid`
                ,`did`
                ,`ddate`
                ,`dstatus`
            FROM `tree` t
            WHERE id = $1',
            array(
                $this->id
                ,$pid
                ,$_SESSION['user']['id']
            )
        ) or die(DB\dbQueryError());

        $objectId = DB\dbLastInsertId();

        /* we have now object created, so we start copy all its possible data:
            - tree_info is filled automaticly by trigger
            - custom security rules from tree_acl
            - custom object data
        */

        // copy node custom security rules if set
        \CB\Security::copyNodeAcl($this->id, $objectId);

        $this->copyCustomDataTo($objectId);

        // move childs from overwriten targetId (which has been marked with dstatus = 3)
        // to newly copied object
        if (is_numeric($targetId)) {
            DB\dbQuery(
                'UPDATE tree
                SET updated = 1
                    ,pid = $2
                WHERE pid = $1 AND
                    dstatus = 0',
                array(
                    $targetId
                    ,$this->id
                )
            ) or die(DB\dbQueryError());
        }

        return $objectId;
    }

    protected function copyCustomDataTo($targetId)
    {
        // copy data from objects table
        DB\dbQuery(
            'INSERT INTO `objects`
                (`id`
                ,`data`
                ,`sys_data`)
            SELECT
                $2
                ,`data`
                ,`sys_data`
            FROM `objects`
            WHERE id = $1',
            array(
                $this->id
                ,$targetId
            )
        ) or die(DB\dbQueryError());
    }

    /**
     * move an object to $pid or over $targetId
     *
     * we'll use the same principle as for copy
     *
     * @param  int $pid      if not specified then will be set to pid of targetId
     * @param  int $targetId
     * @return int the id of moved object or false
     */
    public function moveTo($pid = false, $targetId = false)
    {
        // check input params
        if (!is_numeric($this->id) ||
            (!is_numeric($pid) && !is_numeric($targetId))
        ) {
            return false;
        }

        /* security check */
        if (!\CB\Security::canRead($this->id)) {
            return false;
        }
        /* end of security check */

        //load current object from db into a variable to be passed to log and events
        $this->oldObject = clone $this;
        $this->oldObject->load($this->id);

        if (is_numeric($targetId)) {
            /* target security check */
            if (!\CB\Security::canWrite($targetId)) {
                return false;
            }
            /* end of target security check */
            // marking overwriten object with dstatus = 3
            DB\dbQuery(
                'UPDATE tree
                SET updated = 1
                    ,dstatus = 3
                    ,did = $2
                WHERE id = $1',
                array(
                    $targetId
                    ,$_SESSION['user']['id']
                )
            ) or die(DB\dbQueryError());

            //get pid from target if not specified
            $res = DB\dbQuery(
                'SELECT pid FROM tree WHERE id = $1',
                $targetId
            ) or die(DB\dbQueryError());

            if ($r = $res->fetch_assoc()) {
                $pid = $r['pid'];
            }
            $res->close();
        } else {
            /* pid security check */
            if (!\CB\Security::canWrite($pid)) {
                return false;
            }
            /* end of pid security check */
        }

        /* check again if we have pid set
            It can be unset when not existent $targetId is specified
        */
        if (!is_numeric($pid)) {
            return false;
        }

        // moving the object to $pid

        DB\dbQuery(
            'UPDATE tree
            SET updated = 1
                ,pid = $2
            WHERE id = $1',
            array($this->id, $pid)
        ) or die(DB\dbQueryError());

        $this->moveCustomDataTo($pid);

        // move childs from overwriten targetId (which has been marked with dstatus = 3)
        // to newly copied object
        if (is_numeric($targetId)) {
            DB\dbQuery(
                'UPDATE tree
                SET updated = 1
                    ,pid = $2
                WHERE pid = $1 AND
                    dstatus = 0',
                array(
                    $targetId
                    ,$this->id
                )
            ) or die(DB\dbQueryError());
        }

        $this->load();

        // log the action
        $logParams = array(
            'type' => 'move'
            ,'old' => $this->oldObject
            ,'new' => $this
        );

        Log::add($logParams);

        return $this->id;
    }

    /**
     *  method that should be overwriten in descendants classes
     * if any custom actions should be made on objects move
    */
    protected function moveCustomDataTo($targetId)
    {

    }

    /**
     * filter html field with through purify library
     *
     * @param  array   $fieldsArray
     * @param  boolean $htmlEncode  set true to encode all special chars from string fields
     * @return void
     */
    protected function filterHTMLFields(&$fieldsArray, $htmlEncode = false)
    {
        $template = $this->getTemplate();

        if (!is_array($fieldsArray) || !is_object($template)) {
            return;
        }

        foreach ($fieldsArray as $fn => $fv) {

            //if dont need to encode special chars then process only html fields
            if ($htmlEncode == false) {
                $templateField = $template->getField($fn);

                if ($templateField['type'] !== 'html') {
                    continue;
                }
            }

            $purify = ($templateField['type'] == 'html');

            // analize value
            if ($this->isFieldValue($fv)) {
                if (is_string($fv)) {
                    $fieldsArray[$fn] = $this->filterFieldValue($fv, $purify, $htmlEncode);

                } elseif (is_array($fv) && !empty($fv['value'])) {
                    $fieldsArray[$fn]['value'] = $this->filterFieldValue($fv['value'], $purify, $htmlEncode);
                    if (!empty($fv['childs'])) {
                        $this->filterHTMLFields($fieldsArray[$fn]['childs']);
                    }
                }
            } elseif (is_array($fv)) { //multivalued field
                for ($i=0; $i < sizeof($fv); $i++) {
                    if (is_string($fv[$i])) {
                        $fieldsArray[$fn][$i] = $this->filterFieldValue($fv[$i], $purify, $htmlEncode);

                    } elseif (is_array($fv[$i]) && !empty($fv[$i]['value'])) {
                        $fieldsArray[$fn][$i]['value'] = $this->filterFieldValue($fv[$i]['value'], $purify, $htmlEncode);
                        if (!empty($fv[$i]['childs'])) {
                            $this->filterHTMLFields($fieldsArray[$fn][$i]['childs']);
                        }
                    }
                }
            }
        }
    }

    /**
     * filter a given value
     * @param  varchar $value
     * @param  boolean $purify
     * @param  boolean $htmlEncode
     * @return varchar
     */
    protected function filterFieldValue($value, $purify = false, $htmlEncode = false)
    {
        if ($purify) {
            $value = \CB\Purify::html($value);
        }

        if ($htmlEncode) {
            $value = htmlspecialchars($value, ENT_COMPAT);
        }

        return $value;
    }

    /**
     * method to generate preview blocks and return them as an array
     * now there are only top and bottom blocks
     * top contains fields from grid, bottom - complex fileds (html, text) edited outside the grid
     *
     * @return array
     */
    public function getPreviewBlocks()
    {
        $top = '';
        $body = '';
        $bottom = '';
        $gf = array();

        if (!$this->loaded && !empty($this->id)) {
            $this->load();
        }

        $linearData = $this->getLinearData(true);

        $template = $this->getTemplate();

        //group fields in display blocks
        foreach ($linearData as $field) {
            $tf = $template->getField($field['name']);

            if (empty($tf)) {
                //fantom data of deleted or moved fields
                continue;
            }

            if (empty($tf['cfg'])) {
                $group = 'body';
            } elseif (@$tf['cfg']['showIn'] == 'top') {
                $group = 'body'; //top
            } elseif (@$tf['cfg']['showIn'] == 'tabsheet') {
                $group = 'bottom';
            } else {
                $group = 'body';
            }
            $field['tf'] = $tf;
            $gf[$group][] = $field;
        }

        $eventParams = array(
            'object' => &$this
            ,'groupedFields' => &$gf
        );

        \CB\fireEvent('beforeGeneratePreview', $eventParams);

        if (!empty($gf['top'])) {
            foreach ($gf['top'] as $f) {
                if ($f['name'] == '_title') {
                    continue;
                }

                $v = $template->formatValueForDisplay($f['tf'], $f); //['value']
                if (is_array($v)) {
                    $v = implode(', ', $v);
                }
                if (!empty($v)) {
                    $top .= '<tr><td class="prop-key">'.$f['tf']['title'] . '</td><td class="prop-val">' . $v . '</td></tr>';
                }
            }
        }

        if (!empty($gf['body'])) {
            $previousHeader = '';
            foreach ($gf['body'] as $f) {
                $v = $template->formatValueForDisplay($f['tf'], @$f);
                if (is_array($v)) {
                    $v = implode('<br />', $v);
                }

                if (!empty($f['tf']['cfg']['hidePreview']) ||
                    (empty($v) && empty($f['info']))
                ) {
                    continue;
                }

                $headerField = $template->getHeaderField($f['tf']['id']);
                if (!empty($headerField) && ($previousHeader != $headerField)) {
                    $body .= '<tr class="prop-header"><th colspan="3"'.(
                        empty($headerField['level'])
                        ? ''
                        : ' style="padding-left: '.($headerField['level'] * 20).'px"'
                    ) . '>' . $headerField['title'] . '</th></tr>';
                }
                $previousHeader = $headerField;

                $body .= '<tr>';
                if (empty($f['tf']['cfg']['noHeader'])) {
                    $body .= '<td'.(
                        empty($f['tf']['level'])
                        ? ''
                        : ' style="padding-left: '.($f['tf']['level'] * 20).'px"'
                    ) . ' class="prop-key">'.$f['tf']['title'].'</td>' .
                    '<td class="prop-val">';
                } else {
                    $body .= '<td class="prop-val" colspan="2">';
                }

                $body .= $v.
                    (empty($f['info'])
                        ? ''
                        : '<p class="prop-info">'.$f['info'].'</p>'
                    ) . '</td></tr>';
            }
        }

        if (!empty($gf['bottom'])) {
            foreach ($gf['bottom'] as $f) {
                $v = $template->formatValueForDisplay($f['tf'], $f);
                if (empty($v)) {
                    continue;
                }
                $bottom .=  '<div class="obj-preview-h">' . $f['tf']['title'] . '</div>' .
                    '<div style="padding: 0 5px">' . $v . '</div><br />';
            }
        }

        $top .= $body;

        if (!empty($top)) {
            $top = '<table class="obj-preview"><tbody>'.$top.'</tbody></table><br />';
        }

        $rez = array($top, $bottom);

        $eventParams['result'] = &$rez;

        \CB\fireEvent('generatePreview', $eventParams);

        return $rez;
    }
}
