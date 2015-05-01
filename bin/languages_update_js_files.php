#!/usr/bin/php
<?php
/**
 * languagess
 */

namespace CB;

$ds = DIRECTORY_SEPARATOR;
$path = realpath(dirname(__FILE__) . $ds . '..' . $ds . 'httpsdocs') . $ds;

require_once $path . 'config_platform.php';

// select global translations
$T = array();

$cfg = Config::getPlatformDBConfig();

$languages = explode(',', $cfg['languages']);

$res = DB\dbQuery(
    'SELECT name, `' . implode('`,`', $languages) . '`
    FROM ' . \CB\PREFIX . '_casebox.translations
    WHERE `type` in (0,2)'
) or die( DB\dbQueryError() );

while ($r = $res->fetch_assoc()) {
    reset($r);
    $name = current($r);
    while (($v = next($r)) !== false) {
        $T[key($r)][] = "'".$name."':'".addcslashes($v, "'")."'";
    }
}
$res->close();

//save each translations as global language file
saveFiles($T);

echo "global language files saved\n";

//iterate cores and collect those that have custom translations
$cores = array();
$res = DB\dbQuery('SELECT name, cfg FROM ' . \CB\PREFIX . '_casebox.cores') or die(DB\dbQueryError());
while ($r = $res->fetch_assoc()) {
    $cfg = json_decode($r['cfg'], true);

    $db = empty($cfg['db_name'])
        ? \CB\PREFIX . $r['name']
        : $cfg['db_name'];

    $res2 = DB\dbQuery(
        'SELECT count(*) `count`
        FROM ' . $db . '.translations'
    ) or die(DB\dbQueryError());
    if ($r2 = $res2->fetch_assoc()) {
        if ($r2['count'] > 0) {
            $cores[$r['name']] = $db;
        }
    }
    $res2->close();
}
$res->close();

if (empty($cores)) {
    echo "No cores with custom translations.\n";
} else {
    echo "Processing " . sizeof($cores) . " cores with custom translations:\n";

    foreach ($cores as $core => $db) {
        $CT = $T;

        $res = DB\dbQuery(
            'SELECT *
            FROM ' . $db . '.translations
            WHERE `type` in (0,2)'
        ) or die( DB\dbQueryError() );

        while ($r = $res->fetch_assoc()) {
            foreach ($languages as $l) {
                if (!empty($r[$l])) {
                    $CT[$l][] = "'".$r['name']."':'".addcslashes($r[$l], "'")."'";
                }
            }
        }

        saveFiles($CT, $core . '_');
        echo '.';
    }
}

echo "\nDone";

/**
 * save translation array as files
 * @param  array &$T
 * @param  string $prefix prefix used for file names
 * @return void
 */
function saveFiles(&$T, $prefix = '')
{
    $localePath = DOC_ROOT . 'js' . DIRECTORY_SEPARATOR . 'locale' . DIRECTORY_SEPARATOR;

    foreach ($T as $l => &$v) {
        $filename = $localePath . $prefix . $l . '.js' ;

        if (file_exists($filename)) {
            unlink($filename);
        }

        file_put_contents($filename, 'L = {'.implode(',', $v).'}');
    }
}