<?php
/**
 * Smart WeatherNet - Node 2 Dedicated Soil Live Page Alias
 * Points directly to Live Soil sub-tab for Node 2.
 * Path: php-frontend/node 2/soil.php
 */
$_GET['tab'] = 'soil';
$active_node_id = '2';
$is_node_2 = true;
$active_live_subtab = 'soil';
require __DIR__ . '/live.php';
