<?php
namespace Notifications;

use CB\Config;
use CB\DB;
use CB\L;
use CB\Util;
use CB\User;

class Tasks
{
    /**
     * add notifications for tasks
     * @param  array $p params passed to log
     * @return void
     */
    public static function addNotifications(&$p)
    {

        $taskObject = empty($p['new'])
            ? $p['old']
            : $p['new'];

        $taskData = $taskObject->getData();
        $taskId = $taskData['id'];

        $coreName = Config::get('core_name');
        $sender = User::getDisplayName(). " (".$coreName.") <".Config::get('sender_email').'>';

        $usersToNotify = static::getUsersToNotify($taskObject);

        foreach ($usersToNotify as $userId) {
            $u = User::getPreferences($userId);
            $l = $u['language'];
            $subject = $p['type'];

            //detect subject
            switch ($p['type']) {
                case 'create':
                    $subject = L\get('aboutTaskCreated', $l);
                    break;

                case 'update':
                    $subject = L\get('aboutTaskUpdated', $l);
                    break;

                case 'close':
                case 'complete':
                    $subject = L\get('aboutTaskComplete', $l);
                    break;

                case 'delete':
                    $subject = L\get('aboutTaskDelete', $l);
                    break;

                case 'overdue':
                    $subject = L\get('aboutTaskOverdue', $l);
                    break;

                case 'comlpetion_decline':
                    $subject = L\get('aboutTaskCompletionDecline', $l);
                    break;

                case 'completion_on_behalf':
                    $subject = L\get('aboutTaskCompletionOnBehalt', $l);
                    break;

                case 'reopen':
                    $subject = L\get('aboutTaskReopened', $l);
                    break;
            }

            //replace possible placeholders in subj
            $subject = str_replace(
                array(
                    '{owner}'
                    ,'{name}'
                    ,'{path}'
                ),
                array(
                    User::getDisplayName($taskData['cid'])
                    ,$taskData['name']
                    ,$taskData['path']
                ),
                $subject
            );

            // add core prefix and id to subj
            $subject =  '['.$coreName.' #' . $taskId . '] '.$subject;

            $notifyData = array(
                'sender' => $sender
                ,'subject' => $subject
                ,'body' => ($p['type'] == 'update')
                    ? \CB\Tasks::getTaskInfoForEmail(
                        $p['new']->getData()['id'],
                        $userId,
                        static::getRemovedUsersIds($p)
                    )
                    : \CB\Tasks::getTaskInfoForEmail(
                        $taskId,
                        $userId
                    )
            );

            // insert notification into myslq
            DB\dbQuery(
                'INSERT INTO notifications (
                    action_type
                    ,object_id
                    ,object_pid
                    ,user_id
                    ,data
                )
                VALUES (
                    $1
                    ,$2
                    ,$3
                    ,$4
                    ,$5
                )
                ON DUPLICATE KEY UPDATE
                object_pid = $3
                ,data = $4
                ,action_time = CURRENT_TIMESTAMP',
                array(
                    $p['type']
                    ,$taskId
                    ,@$taskData['pid']
                    ,$userId
                    ,json_encode($notifyData, JSON_UNESCAPED_UNICODE)
                )
            ) or die(DB\dbQueryError());
        }

    }

    /**
     * get the list of users to be notified from a task object
     * @param  array $p params passed to log
     * @return array
     */
    private static function getUsersToNotify($taskObject)
    {
        $rez = array();

        $data = $taskObject->getData();

        $rez = Util\toNumericArray(@$data['data']['assigned']);

        if (!empty($data['oid'])) {
            $rez[] = $data['oid'];
        }
        $rez = array_unique($rez);

        return $rez;
    }

    /**
     * get removed users on a task update
     * @param  array $p params passed to log
     * @return array
     */
    private static function getRemovedUsersIds(&$p)
    {
        $rez = array();
        if (empty($p['old']) || empty($p['new'])) {
            return $rez;
        }

        $oldUserIds = Util\toNumericArray(@$p['old']->getData()['data']['assigned']);
        $newUserIds = Util\toNumericArray(@$p['new']->getData()['data']['assigned']);

        $rez = array_diff($oldUserIds, $newUserIds);

        return $rez;
    }
}