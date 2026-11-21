<?php
/**
 * Smart WeatherNet - Node 2 Dedicated Soil Historical Page Alias
 * Points directly to Soil Historical sub-tab for Node 2.
 * Path: php-frontend/node 2/soil-historical.php
 */
$_GET['tab'] = 'soil';
$active_node_id = '2';
$is_node_2 = true;
$req_subtab = 'soil';
require __DIR__ . '/historical.php';
