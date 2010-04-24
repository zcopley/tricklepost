<?php
/**
 * Tricklepost 0.1 - Resyndicate RSS/Atom feeds to a microblog accts, slowly
 *
 * Copyright (C) 2009 Zach Copley
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 **/
require_once('simplepie.inc');

$config = parse_ini_file('tricklepost.ini');
$feeds = parse_ini_file('feeds.ini', true);

$connection = mysql_connect($config['mysql_host'], $config['mysql_user'],
                            $config['mysql_passwd']) or die('Could not connect: ' . mysql_error());
mysql_select_db('tricklepost') or die('Could not select database');

foreach ($feeds as $feed) {

    $feed_data = new SimplePie();
    $feed_data->set_feed_url($feed['uri']);
    $feed_data->enable_cache(false);
    $feed_data->init();
    $feed_data->handle_content_type();

    print "Reading feed: $feed[uri] ...\n";

    foreach ($feed_data->get_items() as $item) {

        // make sure the item's URL is good

        $plink = $item->get_permalink();

        if (validUrl($plink)) {
            insertItem($item, $feed['uri'], $feed['username'],
                       $feed['password'], $feed['endpoint']);
        }
    }
}

mysql_close($connection);

print "Done.\n";

function insertItem($item, $feedsource, $username, $password, $endpoint)
{
    global $connection;

    $title     = $item->get_title();
    $permalink = $item->get_permalink();
    $itemdate  = $item->get_date();

    // Don't bother with incomplete items

    if (empty($title) || empty($permalink) || empty($itemdate)) {
        return;
    }

    $hashstr = $feedsource . $title . $permalink . $itemdate;

    $hash = sha1($hashstr);

    $qry = 'INSERT IGNORE INTO item (created, feed, link, title, ';
    $qry .= 'description, hash, username, password, endpoint) ';
    $qry .= 'VALUES (\'' . date('Y-m-d H:i:s', strtotime($itemdate)) . '\', ';
    $qry .= "'$feedsource', ";
    $qry .= '\'' . $permalink . '\', ';
    $qry .= '\'' . urlencode($title) . '\', ';
    $qry .= '\'' . urlencode($itemdate) . '\', ';
    $qry .= "'$hash', ";
    $qry .= "'$username', ";
    $qry .= "'$password', ";
    $qry .= "'$endpoint'";
    $qry .= ');';

    mysql_query($qry) or die(mysql_error());

    print 'Item: ' . $title . "\n";
}

function validUrl($str)
{
    return ( ! preg_match('/^(http|https|ftp):\/\/([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?/i', $str)) ? FALSE : TRUE;
}

?>
