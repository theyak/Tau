<?php

namespace TauSessionSample;

include '../../Tau.php';
include 'Session.php';
include 'User.php';

$server = new \TauDbServer('test', 'root', '');
$db = \TauDB::init('mysqli', $server);

Session::setDb($db);
User::setDb($db);

$session = new Session();
if (!$session->logged_in) {
	$user = User::login("someuser", "password");
} else {
	$user = User::get($session->user_id);
}

\Tau::dump($session);
\Tau::dump($user);