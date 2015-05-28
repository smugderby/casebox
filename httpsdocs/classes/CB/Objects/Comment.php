<?php
namespace CB\Objects;

use CB\Config;
use CB\Util;
use CB\User;
use CB\Objects;
use CB\Log;

class Comment extends Object
{

    /**
     * internal function used by create method for creating custom data
     * @return void
     */
    public function create($p = false)
    {
        if ($p === false) {
            $p = &$this->data;
        }

        // if (!empty($p['data']['_title'])) {
        //     //all data is html escaped when indexed in solr
        //     //so no need to encode it here
        //     $msg = $this->processAndFormatMessage($p['data']['_title']);
        //     $p['name'] = $msg;
        //     $p['data']['_title'] = $msg;
        // }

        //disable default log from parent Object class
        //we'll set comments add as comment action for parent
        Config::setFlag('disableActivityLog', true);

        $rez = parent::create($p);

        Config::setFlag('disableActivityLog', false);

        //add user to parent object followers and update its sys_data if needed
        $parentObj = Objects::getCachedObject($p['pid']);
        $posd = $parentObj->getSysData();

        $fu = empty($posd['fu'])
            ? array()
            : $posd['fu'];
        $uid = User::getId();

        if (!in_array($uid, $fu)) {
            $fu[] = intval($uid);
            $posd['fu'] = $fu;
            $parentObj->updateSysData($posd);
        }

        // log the action
        $logParams = array(
            'type' => 'comment'
            ,'new' => $parentObj
            ,'comment' => $p['data']['_title']
        );

        Log::add($logParams);

        return $rez;
    }

    /**
     * update comment
     * @param  array   $p optional properties. If not specified then $this-data is used
     * @return boolean
     */
    public function update($p = false)
    {
        //disable default log from parent Object class
        //we'll set comments add as comment action for parent
        Config::setFlag('disableActivityLog', true);

        $rez = parent::update($p);

        Config::setFlag('disableActivityLog', false);

        // log the action
        $logParams = array(
            'type' => 'comment_update'
            ,'new' => Objects::getCachedObject($p['pid'])
            ,'comment' => $p['data']['_title']
        );

        Log::add($logParams);

        return $rez;

    }

    /**
     * process a message:
     *     - replace urls with links
     *     - replace object references with links
     * @param varchar $message
     */
    public static function processAndFormatMessage($message, $replacements = 'user,object,url')
    {
        if (empty($message)) {
            return $message;
        }

        $replacements = Util\toTrimmedArray($replacements);

        // replace urls with links
        if (in_array('url', $replacements)) {
            $message = \Kwi\UrlLinker::getInstance()->linkUrlsAndEscapeHtml($message);
        }

        //replace object references with links
        if (in_array('object', $replacements) && preg_match_all('/(.?)#(\d+)(.?)/', $message, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                // check if not a html code
                if (($match[1] == '&') && ($match[3] == ';')) {
                    continue;
                }

                $templateId = Objects::getTemplateId($match[2]);
                $name = Objects::getName($match[2]);
                $name = (strlen($name) > 30)
                    ? mb_substr($name, 0, 30) . '&hellip;'
                    : $name;

                $message = str_replace(
                    $match[0],
                    $match[1] .
                    '<a class="click obj-ref" itemid="' . $match[2] .
                    '" templateid= "' . $templateId .
                    '" title="' . $name . '"' .
                    '>#' . $match[2] . '</a>' .
                    $match[3],
                    $message
                );
            }
        }

        //replace users with their names
        if (in_array('user', $replacements) &&preg_match_all('/@([\w\.\-]+[\w])/', $message, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $userId = User::exists($match[1]);
                if (is_numeric($userId)) {
                    $userName = $match[1];
                    $message = str_replace(
                        $match[0],
                        '<span class="cDB user-ref" title="' . User::getDisplayName($userId) . '">@' . $userName . '</span>',
                        $message
                    );
                }
            }
        }

        return $message;
    }
}
