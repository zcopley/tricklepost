<?php
/**
 * Tricklepost - Resyndicate an RSS/Atom feed to a microblog acct, slowly
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
$config = parse_ini_file('tricklepost.ini');
$feeds = parse_ini_file('feeds.ini', true);

$connection = mysql_connect($config['mysql_host'], $config['mysql_user'],
    $config['mysql_passwd']) or die('Could not connect: ' . mysql_error());
mysql_select_db($config['mysql_db']) or die('Could not select database');

foreach ($feeds as $feed) {

    print 'Feed........................' . $feed['uri'] . "\n";

    $delay = (int) $feed['delay'];
    print "Delay: $delay minutes.\n";

    $delay = $delay * 60;

    $qry = 'SELECT published FROM item WHERE feed = ';
    $qry .= '\'' . $feed['uri'] . '\' ORDER BY published DESC LIMIT 0, 1';

    $result = mysql_query($qry) or die('Query failed: ' . mysql_error());

    $last_pub_date = date(strtotime('now'));
    $now = date(strtotime('now'));

    $row = mysql_fetch_array($result);

    if ($row) {
        $last_pub_date = strtotime($row['published']);
    }

    print 'Last publish time...........' . date('Y-m-d H:i:s', $last_pub_date) . "\n";
    print 'Next publish time...........';
    print date('Y-m-d H:i:s', $last_pub_date + $delay) . "\n";
    print 'Now.........................';
    print date('Y-m-d H:i:s', $now) . "\n";

    if (($last_pub_date + $delay) < $now || ($last_pub_date == $now)) {

        $qry = 'SELECT * FROM item WHERE feed = ';
        $qry .= '\'' . $feed['uri'] . '\' AND published = \'0000-00-00 00:00:00\' ';
        $qry .= 'ORDER BY created, modified ASC ';
        $qry .= 'LIMIT 0, 1';

        //print $qry;

        $result = mysql_query($qry) or die('Query failed: ' . mysql_error());
        $row = mysql_fetch_array($result);

        if (!$row) {
            print "No new items to publish.\n\n";
            continue;
        }

        $title = truncate(urldecode($row['title']));
        $short_link = ur1Shorten($row['link']);
        $status = "$title $short_link";

        if (post($status, $row['username'], $row['password'], $row['endpoint'])) {

            $qry = 'UPDATE item SET published = ';
            $qry .= '\'' . date('Y-m-d H:i:s', $now) . '\', ';
            $qry .= 'short_link = \'' . $short_link . '\', ';
            $qry .= 'modified = \'' . date('Y-m-d H:i:s', $now) . '\' ';
            $qry .= 'WHERE hash = \'' . $row['hash'] . '\'';

            mysql_query($qry) or die('Query failed: ' . mysql_error());
        }

    } else {
        print "Too soon to send again.\n\n";
    }

}

mysql_close($connection);

function truncate($str) {

	if (strlen($str) > 100) {
		// truncate at 100 chars -- Hey, we have to leave some room for the link
		$str = substr($str, 0, 100) . '...';
	}

	return trim(htmlspecialchars_decode($str));
}

function ur1Shorten($url)
{

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_USERAGENT, "ur1shorten");
	curl_setopt($ch, CURLOPT_URL,"http://ur1.ca");
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, "longurl=$url");
	curl_setopt($ch, CURLOPT_FAILONERROR, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	$html = curl_exec($ch);

	if (!$html) {
		printf("url1shorten - cURL error: %s\n", curl_error($ch));
		curl_close($ch);
		return NULL;
	}

	curl_close($ch);

	$dom = new DOMDocument();
	@$dom->loadHTML($html);
	$xpath = new DOMXPath($dom);
	$hrefs = $xpath->evaluate("/html/body/p[@class='success']/a");

	return $hrefs->item(0)->getAttribute('href');
}

function post($status, $username, $password, $endpoint) {

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_USERAGENT, "triklepost");
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FAILONERROR, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	curl_setopt($ch, CURLOPT_URL, $endpoint);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS,
	    array('status' => $status, 'source' => 'tricklepost'));
	curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");

	$buffer = curl_exec($ch);

	if (!$buffer) {
		printf("Couldn't post - cURL error: %s\n", curl_error($ch));
		curl_close($ch);
		return NULL;
	}

	curl_close($ch);

	print "Posted: $status\n\n";

	return true;
}

?>