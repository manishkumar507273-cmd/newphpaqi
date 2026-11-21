<?php
/**
 * Smart WeatherNet - Central Node Management & Configuration Registry
 * Allows seamless switching, table routing, and telemetry mapping between Node 1 and Node 2.
 */

if (!function_exists('smartairnet_get_available_nodes')) {
    function smartairnet_get_available_nodes(): array {
        static $nodes = null;
        if ($nodes === null) {
            $node1_path = __DIR__ . '/../node 1/config.php';
            $node2_path = __DIR__ . '/../node 2/config.php';

            $node1 = file_exists($node1_path) ? require $node1_path : [];
            $node2 = file_exists($node2_path) ? require $node2_path : [];
            $nodes = [
                '1' => $node1,
                '2' => $node2,
            ];
        }
        return $nodes;
    }
}

if (!function_exists('smartairnet_get_active_node_id')) {
    function smartairnet_get_active_node_id(): string {
        $allowed = ['1', '2'];
        // 1. Query parameter has highest priority (?node=1 or ?node=2)
        if (isset($_GET['node']) && in_array(trim((string)$_GET['node']), $allowed, true)) {
            $node = trim((string)$_GET['node']);
            // Persist choice in cookie for 365 days
            if (!headers_sent() && (!isset($_COOKIE['smartairnet_selected_node']) || $_COOKIE['smartairnet_selected_node'] !== $node)) {
                $is_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                @setcookie('smartairnet_selected_node', $node, [
                    'expires'  => time() + (86400 * 365),
                    'path'     => '/',
                    'secure'   => $is_secure,
                    'httponly' => false,
                    'samesite' => 'Lax'
                ]);
            }
            return $node;
        }

        // 2. Cookie fallback
        if (isset($_COOKIE['smartairnet_selected_node']) && in_array(trim((string)$_COOKIE['smartairnet_selected_node']), $allowed, true)) {
            return trim((string)$_COOKIE['smartairnet_selected_node']);
        }

        // 3. Default to Node 1
        return '1';
    }
}

if (!function_exists('smartairnet_get_node_config')) {
    function smartairnet_get_node_config(string $nodeId = '1'): array {
        $all = smartairnet_get_available_nodes();
        return $all[$nodeId] ?? $all['1'];
    }
}

if (!function_exists('smartairnet_get_active_node_config')) {
    function smartairnet_get_active_node_config(): array {
        $activeId = smartairnet_get_active_node_id();
        return smartairnet_get_node_config($activeId);
    }
}
