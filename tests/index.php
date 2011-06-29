<?php

// require
require_once 'config.php';
require_once '../campaign_commander_member.php';

// create instance
$ccm = new CampaignCommanderMember(LOGIN, PASSWORD, KEY);

//$response = $ccm->descMemberTable();
//$response = $ccm->getMemberByEmail('spam@verkoyen.eu');
//$response = $ccm->getMemberById('1043875306833');
//$response = $ccm->getListMembersByObj(array(
//	'dynContent' => array(),
//	'memberUID' => 'FIRSTNAME:jan'
//));
//$response = $ccm->getListMembersByPage(1);
//$response = $ccm->insertMember('spam@verkoyen.eu');
//$response = $ccm->updateMember('spam@verkoyen.eu', 'FIRSTNAME', 'spam');
//$response = $ccm->insertOrUpdateMemberByObj(array('FIRSTNAME' => 'MARK'), 'spam@verkoyen.eu');
//$response = $ccm->updateMemberByObj(array('FIRSTNAME' => 'MARK'), 'spam@verkoyen.eu');
//$response = $ccm->getMemberJobStatus('134912671');
//$response = $ccm->unjoinMemberByEmail('spam@verkoyen.eu');
//$response = $ccm->unjoinMemberById('1045452342012');
//$response = $ccm->unjoinMemberByObj(array(
//	'dynContent' => array(),
//	'memberUID' => 'email:spam@verkoyen.eu'
//));
//$response = $ccm->rejoinMemberByEmail('spam@verkoyen.eu');
//$response = $ccm->rejoinMemberById('1045452342012');

// output (Spoon::dump())
ob_start();
var_dump($response);
$output = ob_get_clean();

// cleanup the output
$output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);

// print
echo '<pre>' . htmlspecialchars($output, ENT_QUOTES, 'UTF-8') . '</pre>';

?>