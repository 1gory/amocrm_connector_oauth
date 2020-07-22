<?php

require_once './config.php';
require_once './settings.php';
require_once './Connector.php';

$connector = new Connector();

$orderId = $connector->createLead('test', 100);
$connector->createContact($orderId, 'Contact Name');
