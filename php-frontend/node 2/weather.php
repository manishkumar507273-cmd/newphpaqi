<?php
/**
 * Smart WeatherNet - Node 2 Weather Page Alias
 * Points directly to Live Weather sub-tab for Node 2.
 * Path: php-frontend/node 2/weather.php
 */
$_GET['tab'] = 'weather';
$active_node_id = '2';
$is_node_2 = true;
$active_live_subtab = 'weather';
require __DIR__ . '/live.php';
