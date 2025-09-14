<?php

// Add QR code generation library
// require_once 'phpqrcode/qrlib.php'; // Required in a real environment

// Setting to show/hide API query (true: show, false: hide)
$show_api_query = false;

// Enable error display (for debugging, recommended to disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Controller version and API version mapping
$controller_api_map = array(
    '7.1.1' => array('v11_0', 'v11_1', 'v12_0', 'v13_0', 'v13_1'),
    '7.1.0' => array('v11_0', 'v11_1', 'v12_0', 'v13_0'),
    '7.0.0' => array('v10_0', 'v11_0', 'v11_1', 'v12_0'),
    '6.1.2' => array('v9_0', 'v9_1', 'v10_0', 'v11_0', 'v11_1'),
    '6.1.1' => array('v9_0', 'v9_1', 'v10_0', 'v11_0', 'v11_1'),
    '6.1.0' => array('v9_0', 'v9_1', 'v10_0', 'v11_0'),
    '6.0.0' => array('v9_0', 'v9_1', 'v10_0'),
    '5.2.0' => array('v6_0', 'v6_1', 'v7_0', 'v8_0', 'v8_1', 'v8_2', 'v9_0'),
    'Manual Selection' => array('v6_0', 'v6_1', 'v7_0', 'v8_0', 'v8_1', 'v8_2', 'v9_0', 'v9_1', 'v10_0', 'v11_0', 'v11_1', 'v12_0', 'v13_0', 'v13_1')
);

// Disable SSL certificate verification (not recommended for production environments)
function disable_ssl_verification($ch) {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    return $ch;
}

function get_service_ticket($sz_ip, $api_ver, $id, $passwd) {
    $url = "https://$sz_ip:8443/wsg/api/public/$api_ver/serviceTicket";
    $payload = json_encode(array(
        "username" => $id,
        "password" => $passwd
    ));
    $json_query = array();
    $json_query[] = "<p>Operation: Get Service Ticket | URL: " . htmlspecialchars($url) . " | Payload: " . htmlspecialchars(str_replace($passwd, '[HIDDEN]', $payload)) . "</p>";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $ch = disable_ssl_verification($ch);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        $json_query[] = "<p>Error: " . htmlspecialchars($error) . "</p>";
        return array('success' => false, 'message' => "<p>Error getting Service Ticket: $error</p>", 'json_query' => $json_query);
    }

    curl_close($ch);
    if ($http_code != 200) {
        $json_query[] = "<p>Error: HTTP $http_code | Response: " . htmlspecialchars($response) . "</p>";
        return array('success' => false, 'message' => "<p>Service Ticket request failed (HTTP $http_code): $response</p>", 'json_query' => $json_query);
    }

    $data = json_decode($response, true);
    if (json_last_error() != JSON_ERROR_NONE) {
        $json_query[] = "<p>Error: JSON parsing error: " . htmlspecialchars(json_last_error_msg()) . "</p>";
        return array('success' => false, 'message' => "<p>JSON parsing error: " . json_last_error_msg() . "</p>", 'json_query' => $json_query);
    }

    return array('success' => true, 'serviceTicket' => isset($data['serviceTicket']) ? $data['serviceTicket'] : null, 'json_query' => $json_query);
}

function get_zone_list($sz_ip, $api_ver, $service_ticket) {
    $url = "https://$sz_ip:8443/wsg/api/public/$api_ver/rkszones?serviceTicket=$service_ticket";
    $json_query = array();
    $json_query[] = "<p>Operation: Get Zone List | URL: " . htmlspecialchars($url) . " | Payload: -</p>";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $ch = disable_ssl_verification($ch);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        $json_query[] = "<p>Error: " . htmlspecialchars($error) . "</p>";
        return array('success' => false, 'message' => "<p>Error getting Zone list: $error</p>", 'list' => array(), 'json_query' => $json_query);
    }

    curl_close($ch);
    if ($http_code != 200) {
        $json_query[] = "<p>Error: HTTP $http_code | Response: " . htmlspecialchars($response) . "</p>";
        return array('success' => false, 'message' => "<p>Zone list request failed (HTTP $http_code): " . htmlspecialchars($response) . "</p>", 'list' => array(), 'json_query' => $json_query);
    }

    $data = json_decode($response, true);
    if (json_last_error() != JSON_ERROR_NONE) {
        $json_query[] = "<p>Error: JSON parsing error: " . htmlspecialchars(json_last_error_msg()) . "</p>";
        return array('success' => false, 'message' => "<p>JSON parsing error: " . json_last_error_msg() . "</p>", 'list' => array(), 'json_query' => $json_query);
    }

    $output = "<table class=\"zone-list-table\">";
    $output .= "<tr><th>Zone Name</th><th>Zone ID</th></tr>";
    $zone_list = array();
    foreach ($data['list'] as $item) {
        $zone_name = isset($item['name']) ? $item['name'] : 'Unknown';
        $zone_id = isset($item['id']) ? $item['id'] : 'Unknown';
        $output .= "<tr><td>" . htmlspecialchars($zone_name) . "</td><td>" . htmlspecialchars($zone_id) . "</td></tr>";
        $zone_list[] = array('name' => $zone_name, 'id' => $zone_id);
    }
    $output .= "</table>";
    return array('success' => true, 'message' => $output, 'list' => $zone_list, 'json_query' => $json_query);
}

function get_wlan_list($sz_ip, $api_ver, $service_ticket, $zone_id, $zone_list) {
    $wlan_list = array();

    $output = "<table class=\"wlan-list-table\">";
    $output .= "<tr><th>Zone</th><th>WLAN Name</th><th>SSID</th><th>WLAN ID</th></tr>";
    $json_query = array();

    if ($zone_id === 'all_zones') {
        foreach ($zone_list as $zone) {
            $current_zone_id = $zone['id'];
            $current_zone_name = $zone['name'];
            $url = "https://$sz_ip:8443/wsg/api/public/$api_ver/rkszones/$current_zone_id/wlans?serviceTicket=$service_ticket";
            $json_query[] = "<p>Operation: Get WLAN List | Zone: " . htmlspecialchars($current_zone_name) . " | URL: " . htmlspecialchars($url) . " | Payload: -</p>";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $ch = disable_ssl_verification($ch);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($response === false) {
                $error = curl_error($ch);
                $output .= "<tr><td>" . htmlspecialchars($current_zone_name) . "</td><td colspan=\"3\">Error getting WLAN list: " . htmlspecialchars($error) . "</td></tr>";
                curl_close($ch);
                $json_query[] = "<p>Error: " . htmlspecialchars($error) . "</p>";
                continue;
            }

            curl_close($ch);
            if ($http_code != 200) {
                $output .= "<tr><td>" . htmlspecialchars($current_zone_name) . "</td><td colspan=\"3\">WLAN list request failed (HTTP $http_code): " . htmlspecialchars($response) . "</td></tr>";
                $json_query[] = "<p>Error: HTTP $http_code | Response: " . htmlspecialchars($response) . "</p>";
                continue;
            }

            $data = json_decode($response, true);
            if (json_last_error() != JSON_ERROR_NONE) {
                $output .= "<tr><td>" . htmlspecialchars($current_zone_name) . "</td><td colspan=\"3\">JSON parsing error: " . htmlspecialchars(json_last_error_msg()) . "</td></tr>";
                $json_query[] = "<p>Error: JSON parsing error: " . htmlspecialchars(json_last_error_msg()) . "</p>";
                continue;
            }

            foreach ($data['list'] as $item) {
                $wlan_name = isset($item['name']) ? $item['name'] : 'Unknown';
                $ssid = isset($item['ssid']) ? $item['ssid'] : 'Unknown';
                $wlan_id = isset($item['id']) ? $item['id'] : 'Unknown';
                $output .= "<tr>";
                $output .= "<td>" . htmlspecialchars($current_zone_name) . "</td>";
                $output .= "<td>" . htmlspecialchars($wlan_name) . "</td>";
                $output .= "<td>" . htmlspecialchars($ssid) . "</td>";
                $output .= "<td>" . htmlspecialchars($wlan_id) . "</td>";
                $output .= "</tr>";
                $wlan_list[] = array(
                    'name' => $wlan_name,
                    'ssid' => $ssid,
                    'id' => $wlan_id,
                    'zone_id' => $current_zone_id,
                    'zone_name' => $current_zone_name
                );
            }
        }
    } else {
        $url = "https://$sz_ip:8443/wsg/api/public/$api_ver/rkszones/$zone_id/wlans?serviceTicket=$service_ticket";
        $zone_name = '';
        foreach ($zone_list as $zone) {
            if ($zone['id'] === $zone_id) {
                $zone_name = $zone['name'];
                break;
            }
        }
        $json_query[] = "<p>Operation: Get WLAN List | Zone: " . htmlspecialchars($zone_name) . " | URL: " . htmlspecialchars($url) . " | Payload: -</p>";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $ch = disable_ssl_verification($ch);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            $json_query[] = "<p>Error: " . htmlspecialchars($error) . "</p>";
            $output .= "<tr><td>" . htmlspecialchars($zone_name) . "</td><td colspan=\"3\">Error getting WLAN list: " . htmlspecialchars($error) . "</td></tr>";
            $output .= "</table>";
            return array('success' => false, 'message' => $output, 'list' => array(), 'json_query' => $json_query);
        }

        curl_close($ch);
        if ($http_code != 200) {
            $json_query[] = "<p>Error: HTTP $http_code | Response: " . htmlspecialchars($response) . "</p>";
            $output .= "<tr><td>" . htmlspecialchars($zone_name) . "</td><td colspan=\"3\">WLAN list request failed (HTTP $http_code): " . htmlspecialchars($response) . "</td></tr>";
            $output .= "</table>";
            return array('success' => false, 'message' => $output, 'list' => array(), 'json_query' => $json_query);
        }

        $data = json_decode($response, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            $json_query[] = "<p>Error: JSON parsing error: " . htmlspecialchars(json_last_error_msg()) . "</p>";
            $output .= "<tr><td>" . htmlspecialchars($zone_name) . "</td><td colspan=\"3\">JSON parsing error: " . htmlspecialchars(json_last_error_msg()) . "</td></tr>";
            $output .= "</table>";
            return array('success' => false, 'message' => $output, 'list' => array(), 'json_query' => $json_query);
        }

        foreach ($data['list'] as $item) {
            $wlan_name = isset($item['name']) ? $item['name'] : 'Unknown';
            $ssid = isset($item['ssid']) ? $item['ssid'] : 'Unknown';
            $wlan_id = isset($item['id']) ? $item['id'] : 'Unknown';
            $output .= "<tr>";
            $output .= "<td>" . htmlspecialchars($zone_name) . "</td>";
            $output .= "<td>" . htmlspecialchars($wlan_name) . "</td>";
            $output .= "<td>" . htmlspecialchars($ssid) . "</td>";
            $output .= "<td>" . htmlspecialchars($wlan_id) . "</td>";
            $output .= "</tr>";
            $wlan_list[] = array(
                'name' => $wlan_name,
                'ssid' => $ssid,
                'id' => $wlan_id,
                'zone_id' => $zone_id,
                'zone_name' => $zone_name
            );
        }
    }

    $output .= "</table>";
    return array('success' => true, 'message' => $output, 'list' => $wlan_list, 'json_query' => $json_query);
}

function get_wlan_details($sz_ip, $api_ver, $service_ticket, $zone_id, $wlan_id, $zone_list, $wlan_list) {
    $output = "<table class=\"wlan-details-table\">";
    $output .= "<tr><th>Zone</th><th>SSID</th><th>Type</th><th>Security<br>Standard</th><th>Encryption Method</th><th>Passphrase</th><th>SAE<br>Passphrase</th><th>QR Code</th></tr>";
    $details = array();  // Always initialize as an array
    $json_query = array();

    if ($wlan_id === 'all_wlans') {
        foreach ($wlan_list as $wlan) {
            $current_zone_id = $wlan['zone_id'];
            $current_wlan_id = $wlan['id'];
            $current_zone_name = $wlan['zone_name'];
            $current_ssid = $wlan['ssid'];

            $url = "https://$sz_ip:8443/wsg/api/public/$api_ver/rkszones/$current_zone_id/wlans/$current_wlan_id?serviceTicket=$service_ticket";
            $json_query[] = "<p>Operation: Get WLAN Details | Zone: " . htmlspecialchars($current_zone_name) . " | SSID: " . htmlspecialchars($current_ssid) . " | WLAN ID: " . htmlspecialchars($current_wlan_id) . " | URL: " . htmlspecialchars($url) . " | Payload: -</p>";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $ch = disable_ssl_verification($ch);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($response === false) {
                $error = curl_error($ch);
                $output .= "<tr><td>" . htmlspecialchars($current_zone_name) . "</td><td>" . htmlspecialchars($current_ssid) . "</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td></tr>";
                curl_close($ch);
                $json_query[] = "<p>Error: " . htmlspecialchars($error) . "</p>";
                continue;
            }

            curl_close($ch);
            if ($http_code != 200) {
                $output .= "<tr><td>" . htmlspecialchars($current_zone_name) . "</td><td>" . htmlspecialchars($current_ssid) . "</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td></tr>";
                $json_query[] = "<p>Error: HTTP $http_code | Response: " . htmlspecialchars($response) . "</p>";
                continue;
            }

            $data = json_decode($response, true);
            if (json_last_error() != JSON_ERROR_NONE) {
                $output .= "<tr><td>" . htmlspecialchars($current_zone_name) . "</td><td>" . htmlspecialchars($current_ssid) . "</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td></tr>";
                $json_query[] = "<p>Error: JSON parsing error: " . htmlspecialchars(json_last_error_msg()) . "</p>";
                continue;
            }

			$wlan_details = array(
				'type' => isset($data['type']) ? $data['type'] : '-',
				'passphrase' => isset($data['encryption']['passphrase']) ? $data['encryption']['passphrase'] : '-',
				'saePassphrase' => isset($data['encryption']['saePassphrase']) ? $data['encryption']['saePassphrase'] : '-',
				'method' => isset($data['encryption']['method']) ? $data['encryption']['method'] : 'Unknown',
				'algorithm' => isset($data['encryption']['algorithm']) ? $data['encryption']['algorithm'] : '-'
			);

            $is_changeable = ($wlan_details['type'] === 'Standard_Open' && ($wlan_details['method'] === 'WPA2' || $wlan_details['method'] === 'WPA3'));
            $qr_code_url_base = '';
            if (($wlan_details['method'] === 'WPA2' && $wlan_details['passphrase']) || ($wlan_details['method'] === 'WPA3' && $wlan_details['saePassphrase'])) {
                $password = $wlan_details['method'] === 'WPA2' ? $wlan_details['passphrase'] : $wlan_details['saePassphrase'];
                $qr_code_url_base = "generate_qr.php?ssid=" . urlencode($current_ssid) . "&password=" . urlencode($password) . "&method=" . urlencode($wlan_details['method']);
            }

            $output .= "<tr>";
            $output .= "<td>" . htmlspecialchars($current_zone_name) . "</td>";
            $output .= "<td>" . htmlspecialchars($current_ssid) . "</td>";
            $output .= "<td>" . htmlspecialchars($wlan_details['type'] ? $wlan_details['type'] : '-') . "</td>";
            $output .= "<td>" . htmlspecialchars($wlan_details['method'] ? $wlan_details['method'] : 'Unknown') . "</td>";
            $output .= "<td>" . htmlspecialchars($wlan_details['algorithm'] ? $wlan_details['algorithm'] : '-') . "</td>";
            $output .= "<td>" . htmlspecialchars($wlan_details['passphrase'] ? $wlan_details['passphrase'] : '-') . "</td>";
            $output .= "<td>" . htmlspecialchars($wlan_details['saePassphrase'] ? $wlan_details['saePassphrase'] : '-') . "</td>";
            $output .= "<td>";
            if (!empty($qr_code_url_base)) {
                $output .= "<a href='" . htmlspecialchars($qr_code_url_base) . "' target='_blank'><img src=\"" . htmlspecialchars($qr_code_url_base . "&output=image") . "\" alt=\"QR Code\" style=\"width: 60px; height: 60px;\"></a>";
            } else {
                $output .= "-";
            }
            $output .= "</td>";
            $output .= "</tr>";

            $details[] = array(
                'zone_id' => $current_zone_id,
                'zone_name' => $current_zone_name,
                'wlan_name' => $wlan['name'],
                'wlan_id' => $current_wlan_id,
                'ssid' => $current_ssid,
                'type' => $wlan_details['type'],
                'method' => $wlan_details['method'],
                'algorithm' => $wlan_details['algorithm'],
                'passphrase' => $wlan_details['passphrase'],
                'saePassphrase' => $wlan_details['saePassphrase'],
                'changeable' => $is_changeable
            );
        }
    } else {
        list($selected_zone_id, $selected_wlan_name) = explode(':', $wlan_id, 2);
        $selected_wlan_id = '';
        $zone_name = '';
        $ssid = '';
        foreach ($wlan_list as $wlan) {
            if ($wlan['name'] === urldecode($selected_wlan_name) && $wlan['zone_id'] === $selected_zone_id) {
                $selected_wlan_id = $wlan['id'];
                $zone_name = $wlan['zone_name'];
                $ssid = $wlan['ssid'];
                break;
            }
        }

        if (empty($selected_wlan_id)) {
            $json_query[] = "<p>Error: Could not find WLAN ID. | Zone: " . htmlspecialchars($zone_name) . " | SSID: " . htmlspecialchars($ssid) . "</p>";
            $output .= "<tr><td colspan=\"8\">Could not find WLAN ID.</td></tr>";
            $output .= "</table>";
            return array('success' => false, 'message' => $output, 'details' => null, 'json_query' => $json_query);
        }

        $url = "https://$sz_ip:8443/wsg/api/public/$api_ver/rkszones/$selected_zone_id/wlans/$selected_wlan_id?serviceTicket=$service_ticket";
        $json_query[] = "<p>Operation: Get WLAN Details | Zone: " . htmlspecialchars($zone_name) . " | SSID: " . htmlspecialchars($ssid) . " | WLAN ID: " . htmlspecialchars($selected_wlan_id) . " | URL: " . htmlspecialchars($url) . " | Payload: -</p>";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $ch = disable_ssl_verification($ch);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            $json_query[] = "<p>Error: " . htmlspecialchars($error) . "</p>";
            $output .= "<tr><td>" . htmlspecialchars($zone_name) . "</td><td>" . htmlspecialchars($ssid) . "</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td></tr>";
            $output .= "</table>";
            return array('success' => false, 'message' => $output, 'details' => null, 'json_query' => $json_query);
        }

        curl_close($ch);
        if ($http_code != 200) {
            $json_query[] = "<p>Error: HTTP $http_code | Response: " . htmlspecialchars($response) . "</p>";
            $output .= "<tr><td>" . htmlspecialchars($zone_name) . "</td><td>" . htmlspecialchars($ssid) . "</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td></tr>";
            $output .= "</table>";
            return array('success' => false, 'message' => $output, 'details' => null, 'json_query' => $json_query);
        }

        $data = json_decode($response, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            $json_query[] = "<p>Error: JSON parsing error: " . htmlspecialchars(json_last_error_msg()) . "</p>";
            $output .= "<tr><td>" . htmlspecialchars($zone_name) . "</td><td>" . htmlspecialchars($ssid) . "</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td></tr>";
            $output .= "</table>";
            return array('success' => false, 'message' => $output, 'details' => null, 'json_query' => $json_query);
        }

        $wlan_details = array(
            'type' => isset($data['type']) ? $data['type'] : null,
            'passphrase' => isset($data['encryption']['passphrase']) ? $data['encryption']['passphrase'] : null,
            'saePassphrase' => isset($data['encryption']['saePassphrase']) ? $data['encryption']['saePassphrase'] : null,
            'method' => isset($data['encryption']['method']) ? $data['encryption']['method'] : null,
            'algorithm' => isset($data['encryption']['algorithm']) ? $data['encryption']['algorithm'] : null
        );

        $is_changeable = ($wlan_details['type'] === 'Standard_Open' && ($wlan_details['method'] === 'WPA2' || $wlan_details['method'] === 'WPA3'));
        $qr_code_url_base = '';
        if (($wlan_details['method'] === 'WPA2' && $wlan_details['passphrase']) || ($wlan_details['method'] === 'WPA3' && $wlan_details['saePassphrase'])) {
            $password = $wlan_details['method'] === 'WPA2' ? $wlan_details['passphrase'] : $wlan_details['saePassphrase'];
            $qr_code_url_base = "generate_qr.php?ssid=" . urlencode($ssid) . "&password=" . urlencode($password) . "&method=" . urlencode($wlan_details['method']);
        }

        $output .= "<tr>";
        $output .= "<td>" . htmlspecialchars($zone_name) . "</td>";
        $output .= "<td>" . htmlspecialchars($ssid) . "</td>";
        $output .= "<td>" . htmlspecialchars($wlan_details['type'] ? $wlan_details['type'] : '-') . "</td>";
        $output .= "<td>" . htmlspecialchars($wlan_details['method'] ? $wlan_details['method'] : 'Unknown') . "</td>";
        $output .= "<td>" . htmlspecialchars($wlan_details['algorithm'] ? $wlan_details['algorithm'] : '-') . "</td>";
        $output .= "<td>" . htmlspecialchars($wlan_details['passphrase'] ? $wlan_details['passphrase'] : '-') . "</td>";
        $output .= "<td>" . htmlspecialchars($wlan_details['saePassphrase'] ? $wlan_details['saePassphrase'] : '-') . "</td>";
        $output .= "<td>";
        if (!empty($qr_code_url_base)) {
            $output .= "<a href='" . htmlspecialchars($qr_code_url_base) . "' target='_blank'><img src=\"" . htmlspecialchars($qr_code_url_base . "&output=image") . "\" alt=\"QR Code\" style=\"width: 60px; height: 60px;\"></a>";
        } else {
            $output .= "-";
        }
        $output .= "</td>";
        $output .= "</tr>";

        $details[] = array(
            'zone_id' => $selected_zone_id,
            'zone_name' => $zone_name,
            'wlan_name' => urldecode($selected_wlan_name),
            'wlan_id' => $selected_wlan_id,
            'ssid' => $ssid,
            'type' => $wlan_details['type'],
            'method' => $wlan_details['method'],
            'algorithm' => $wlan_details['algorithm'],
            'passphrase' => $wlan_details['passphrase'],
            'saePassphrase' => $wlan_details['saePassphrase'],
            'changeable' => $is_changeable
        );
    }
    
    $output .= "</table>";
    return array('success' => true, 'message' => $output, 'details' => $details, 'json_query' => $json_query);
}

function update_wlan_password($sz_ip, $api_ver, $service_ticket, $zone_id, $wlan_id, $method, $new_password, $zone_list, $wlan_list, &$wlan_details) {
    $output = "<table class=\"password-update-table\">";
    $output .= "<tr><th>Zone</th><th>SSID</th><th>Status</th><th>Encryption Method</th><th>Passphrase</th><th>SAE Passphrase</th></tr>";
    $json_query = array();

    if ($wlan_id === 'all_wlans' && isset($_POST['change_selected_wlans'])) {
        $passwords = isset($_POST['passwords']) ? $_POST['passwords'] : array();
        $methods = isset($_POST['encryption_methods']) ? $_POST['encryption_methods'] : array();
        $any_change_attempted = false;
        foreach ($wlan_details as $index => $wlan_detail) {
            $current_zone_id = $wlan_detail['zone_id'];
            $current_wlan_id = $wlan_detail['wlan_id'];
            $current_zone_name = $wlan_detail['zone_name'];
            $current_ssid = $wlan_detail['ssid'];
            $new_password = isset($passwords[$index]) ? trim($passwords[$index]) : '';
            $method = isset($methods[$index]) ? $methods[$index] : '';

            if (!$wlan_detail['changeable']) {
                $output .= "<tr><td>" . htmlspecialchars($current_zone_name) . "</td><td>" . htmlspecialchars($current_ssid) . "</td><td>Password change not possible</td><td>-</td><td>-</td><td>-</td></tr>";
                continue;
            }

            $current_passphrase = null;
            if ($wlan_detail['method'] === 'WPA3' && $wlan_detail['saePassphrase']) {
                $current_passphrase = $wlan_detail['saePassphrase'];
            } else {
                $current_passphrase = $wlan_detail['passphrase'];
            }

            if ($method === 'no_change' || empty($new_password) || ($current_passphrase && $new_password === $current_passphrase)) {
                $output .= "<tr><td>" . htmlspecialchars($current_zone_name) . "</td><td>" . htmlspecialchars($current_ssid) . "</td><td>No password changes</td><td>-</td><td>-</td><td>-</td></tr>";
                $json_query[] = "<p>Operation: Skip WLAN Password Update | Zone: " . htmlspecialchars($current_zone_name) . " | SSID: " . htmlspecialchars($current_ssid) . " | Reason: " . ($method === 'no_change' ? 'No change selected' : (empty($new_password) ? 'Empty password' : 'Same as current password')) . "</p>";
                continue;
            }

            if ($method !== 'WPA2' && $method !== 'WPA3') {
                $output .= "<tr><td>" . htmlspecialchars($current_zone_name) . "</td><td>" . htmlspecialchars($current_ssid) . "</td><td>Invalid encryption method</td><td>-</td><td>-</td><td>-</td></tr>";
                $json_query[] = "<p>Error: Invalid encryption method for Zone: " . htmlspecialchars($current_zone_name) . " | SSID: " . htmlspecialchars($current_ssid) . "</p>";
                continue;
            }

            $any_change_attempted = true;
            $url = "https://$sz_ip:8443/wsg/api/public/$api_ver/rkszones/$current_zone_id/wlans/$current_wlan_id?serviceTicket=$service_ticket";

            if ($method === 'WPA2') {
                $payload = json_encode(array(
                    'encryption' => array(
                        'method' => 'WPA2',
                        'algorithm' => 'AES',
                        'passphrase' => $new_password
                    )
                ));
            } else {
                $payload = json_encode(array(
                    'encryption' => array(
                        'method' => 'WPA3',
                        'algorithm' => 'AES',
                        'saePassphrase' => $new_password,
                        'mfp' => 'required'
                    )
                ));
            }

            $json_query[] = "<p>Operation: Update WLAN Password | Zone: " . htmlspecialchars($current_zone_name) . " | SSID: " . htmlspecialchars($current_ssid) . " | WLAN ID: " . htmlspecialchars($current_wlan_id) . " | URL: " . htmlspecialchars($url) . " | Payload: " . htmlspecialchars($payload) . "</p>";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $ch = disable_ssl_verification($ch);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($response === false) {
                $error = curl_error($ch);
                $output .= "<tr><td>" . htmlspecialchars($current_zone_name) . "</td><td>" . htmlspecialchars($current_ssid) . "</td><td>Password change error: " . htmlspecialchars($error) . "</td><td>-</td><td>-</td><td>-</td></tr>";
                $json_query[] = "<p>Error: " . htmlspecialchars($error) . "</p>";
                curl_close($ch);
                continue;
            }

            curl_close($ch);
            if ($http_code != 204) {
                $output .= "<tr><td>" . htmlspecialchars($current_zone_name) . "</td><td>" . htmlspecialchars($current_ssid) . "</td><td>Password change failed (HTTP $http_code): " . htmlspecialchars($response) . "</td><td>-</td><td>-</td><td>-</td></tr>";
                $json_query[] = "<p>Error: HTTP $http_code | Response: " . htmlspecialchars($response) . "</p>";
                continue;
            }

            $wlan_details[$index]['method'] = $method;
            $wlan_details[$index]['algorithm'] = 'AES';
            if ($method === 'WPA2') {
                $wlan_details[$index]['passphrase'] = $new_password;
                $wlan_details[$index]['saePassphrase'] = null;
            } else {
                $wlan_details[$index]['saePassphrase'] = $new_password;
                $wlan_details[$index]['passphrase'] = null;
            }
            $json_query[] = "<p>Updated WLAN Details | Zone: " . htmlspecialchars($current_zone_name) . " | SSID: " . htmlspecialchars($current_ssid) . " | Changed Encryption Method: " . htmlspecialchars($method) . " | Changed Passphrase: " . htmlspecialchars($method === 'WPA2' ? $new_password : '-') . " | Changed SAE Passphrase: " . htmlspecialchars($method === 'WPA3' ? $new_password : '-') . "</p>";

            $output .= "<tr>";
            $output .= "<td>" . htmlspecialchars($current_zone_name) . "</td>";
            $output .= "<td>" . htmlspecialchars($current_ssid) . "</td>";
            $output .= "<td>Password change successful</td>";
            $output .= "<td>" . htmlspecialchars($method) . "</td>";
            $output .= "<td>" . htmlspecialchars($method === 'WPA2' ? $new_password : '-') . "</td>";
            $output .= "<td>" . htmlspecialchars($method === 'WPA3' ? $new_password : '-') . "</td>";
            $output .= "</tr>";
        }

        if (!$any_change_attempted) {
            $output .= "<tr><td colspan=\"6\">No password to change.</td></tr>";
        }
    } elseif ($wlan_id !== 'all_wlans' && isset($_POST['update_single_wlan'])) {
        $url = "https://$sz_ip:8443/wsg/api/public/$api_ver/rkszones/$zone_id/wlans/$wlan_id?serviceTicket=$service_ticket";
        $zone_name = '';
        $ssid = '';
        foreach ($wlan_list as $wlan) {
            if ($wlan['id'] === $wlan_id && $wlan['zone_id'] === $zone_id) {
                $zone_name = $wlan['zone_name'];
                $ssid = $wlan['ssid'];
                break;
            }
        }

        $is_changeable = (isset($wlan_details['type']) && $wlan_details['type'] === 'Standard_Open' && isset($wlan_details['method']) && ($wlan_details['method'] === 'WPA2' || $wlan_details['method'] === 'WPA3'));
        if (!$is_changeable) {
            $output .= "<tr><td>" . htmlspecialchars($zone_name) . "</td><td>" . htmlspecialchars($ssid) . "</td><td>Password change not possible</td><td>-</td><td>-</td><td>-</td></tr>";
        } else {
            if (empty($new_password)) {
                $output .= "<tr><td>" . htmlspecialchars($zone_name) . "</td><td>" . htmlspecialchars($ssid) . "</td><td>Password change failed: Password is empty.</td><td>-</td><td>-</td><td>-</td></tr>";
                $json_query[] = "<p>Error: Empty password. No update attempted.</p>";
            } else {
                if ($method === 'WPA2') {
                    $payload = json_encode(array(
                        'encryption' => array(
                            'method' => 'WPA2',
                            'algorithm' => 'AES',
                            'passphrase' => $new_password
                        )
                    ));
                } else {
                    $payload = json_encode(array(
                        'encryption' => array(
                            'method' => 'WPA3',
                            'algorithm' => 'AES',
                            'saePassphrase' => $new_password,
                            'mfp' => 'required'
                        )
                    ));
                }

                $json_query[] = "<p>Operation: Update WLAN Password (Single) | Zone: " . htmlspecialchars($zone_name) . " | SSID: " . htmlspecialchars($ssid) . " | WLAN ID: " . htmlspecialchars($wlan_id) . " | URL: " . htmlspecialchars($url) . " | Payload: " . htmlspecialchars($payload) . "</p>";

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                $ch = disable_ssl_verification($ch);

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($response === false) {
                    $error = curl_error($ch);
                    $output .= "<tr><td>" . htmlspecialchars($zone_name) . "</td><td>" . htmlspecialchars($ssid) . "</td><td>Password change error: " . htmlspecialchars($error) . "</td><td>-</td><td>-</td><td>-</td></tr>";
                    $json_query[] = "<p>Error: " . htmlspecialchars($error) . "</p>";
                    curl_close($ch);
                } else {
                    curl_close($ch);
                    if ($http_code != 204) {
                        $output .= "<tr><td>" . htmlspecialchars($zone_name) . "</td><td>" . htmlspecialchars($ssid) . "</td><td>Password change failed (HTTP $http_code): " . htmlspecialchars($response) . "</td><td>-</td><td>-</td><td>-</td></tr>";
                        $json_query[] = "<p>Error: HTTP $http_code | Response: " . htmlspecialchars($response) . "</p>";
                    } else {
                        $wlan_details[0]['method'] = $method;
                        $wlan_details[0]['algorithm'] = 'AES';
                        if ($method === 'WPA2') {
                            $wlan_details[0]['passphrase'] = $new_password;
                            $wlan_details[0]['saePassphrase'] = null;
                        } else {
                            $wlan_details[0]['saePassphrase'] = $new_password;
                            $wlan_details[0]['passphrase'] = null;
                        }
                        $json_query[] = "<p>Updated WLAN Details | Zone: " . htmlspecialchars($zone_name) . " | SSID: " . htmlspecialchars($ssid) . " | Changed Encryption Method: " . htmlspecialchars($method) . " | Changed Passphrase: " . htmlspecialchars($method === 'WPA2' ? $new_password : '-') . " | Changed SAE Passphrase: " . htmlspecialchars($method === 'WPA3' ? $new_password : '-') . "</p>";

                        $output .= "<tr>";
                        $output .= "<td>" . htmlspecialchars($zone_name) . "</td>";
                        $output .= "<td>" . htmlspecialchars($ssid) . "</td>";
                        $output .= "<td>Password change successful</td>";
                        $output .= "<td>" . htmlspecialchars($method) . "</td>";
                        $output .= "<td>" . htmlspecialchars($method === 'WPA2' ? $new_password : '-') . "</td>";
                        $output .= "<td>" . htmlspecialchars($method === 'WPA3' ? $new_password : '-') . "</td>";
                        $output .= "</tr>";
                    }
                }
            }
        }
    } elseif ($wlan_id === 'all_wlans' && isset($_POST['update_all_wlans_trigger'])) {
        $any_change_attempted = false;
        $method = isset($_POST['encryption_method']) ? $_POST['encryption_method'] : '';
        $new_password = trim($_POST['new_password']);

        foreach ($wlan_details as $index => $wlan_detail) {
            $current_zone_id = $wlan_detail['zone_id'];
            $current_wlan_id = $wlan_detail['wlan_id'];
            $current_zone_name = $wlan_detail['zone_name'];
            $current_ssid = $wlan_detail['ssid'];

            if (!$wlan_detail['changeable']) {
                $output .= "<tr><td>" . htmlspecialchars($current_zone_name) . "</td><td>" . htmlspecialchars($current_ssid) . "</td><td>Password change not possible</td><td>-</td><td>-</td><td>-</td></tr>";
                continue;
            }

            $any_change_attempted = true;
            $url = "https://$sz_ip:8443/wsg/api/public/$api_ver/rkszones/$current_zone_id/wlans/$current_wlan_id?serviceTicket=$service_ticket";

            if ($method === 'WPA2') {
                $payload = json_encode(array(
                    'encryption' => array(
                        'method' => 'WPA2',
                        'algorithm' => 'AES',
                        'passphrase' => $new_password
                    )
                ));
            } else {
                $payload = json_encode(array(
                    'encryption' => array(
                        'method' => 'WPA3',
                        'algorithm' => 'AES',
                        'saePassphrase' => $new_password,
                        'mfp' => 'required'
                    )
                ));
            }

            $json_query[] = "<p>Operation: Update WLAN Password | Zone: " . htmlspecialchars($current_zone_name) . " | SSID: " . htmlspecialchars($current_ssid) . " | WLAN ID: " . htmlspecialchars($current_wlan_id) . " | URL: " . htmlspecialchars($url) . " | Payload: " . htmlspecialchars($payload) . "</p>";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $ch = disable_ssl_verification($ch);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($response === false) {
                $error = curl_error($ch);
                $output .= "<tr><td>" . htmlspecialchars($current_zone_name) . "</td><td>" . htmlspecialchars($current_ssid) . "</td><td>Password change error: " . htmlspecialchars($error) . "</td><td>-</td><td>-</td><td>-</td></tr>";
                $json_query[] = "<p>Error: " . htmlspecialchars($error) . "</p>";
                curl_close($ch);
                continue;
            }

            curl_close($ch);
            if ($http_code != 204) {
                $output .= "<tr><td>" . htmlspecialchars($current_zone_name) . "</td><td>" . htmlspecialchars($current_ssid) . "</td><td>Password change failed (HTTP $http_code): " . htmlspecialchars($response) . "</td><td>-</td><td>-</td><td>-</td></tr>";
                $json_query[] = "<p>Error: HTTP $http_code | Response: " . htmlspecialchars($response) . "</p>";
                continue;
            }

            $wlan_details[$index]['method'] = $method;
            $wlan_details[$index]['algorithm'] = 'AES';
            if ($method === 'WPA2') {
                $wlan_details[$index]['passphrase'] = $new_password;
                $wlan_details[$index]['saePassphrase'] = null;
            } else {
                $wlan_details[$index]['saePassphrase'] = $new_password;
                $wlan_details[$index]['passphrase'] = null;
            }
            $json_query[] = "<p>Updated WLAN Details | Zone: " . htmlspecialchars($current_zone_name) . " | SSID: " . htmlspecialchars($current_ssid) . " | Changed Encryption Method: " . htmlspecialchars($method) . " | Changed Passphrase: " . htmlspecialchars($method === 'WPA2' ? $new_password : '-') . " | Changed SAE Passphrase: " . htmlspecialchars($method === 'WPA3' ? $new_password : '-') . "</p>";

            $output .= "<tr>";
            $output .= "<td>" . htmlspecialchars($current_zone_name) . "</td>";
            $output .= "<td>" . htmlspecialchars($current_ssid) . "</td>";
            $output .= "<td>Password change successful</td>";
            $output .= "<td>" . htmlspecialchars($method) . "</td>";
            $output .= "<td>" . htmlspecialchars($method === 'WPA2' ? $new_password : '-') . "</td>";
            $output .= "<td>" . htmlspecialchars($method === 'WPA3' ? $new_password : '-') . "</td>";
            $output .= "</tr>";
        }

        if (!$any_change_attempted) {
            $output .= "<tr><td colspan=\"6\">No password to change.</td></tr>";
        }
    } else {
        $output .= "<tr><td colspan=\"6\">Invalid request.</td></tr>";
        $json_query[] = "<p>Error: Invalid request. No password update attempted.</p>";
    }

    $output .= "</table>";
    return array('success' => true, 'message' => $output, 'json_query' => $json_query);
}

// download_csv function added
function download_csv($wlan_details) {
    // Modification: Check array (Enhanced security)
    if (!is_array($wlan_details) || empty($wlan_details)) {
        echo "No data to download.";
        exit;
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="wlan_passwords.csv"');

    // Add UTF-8 BOM (Excel compatibility)
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    // Write header
    $header = array('Zone', 'SSID', 'Type', 'Security Standard', 'Encryption Method', 'Passphrase', 'SAE Passphrase');
    fputcsv($output, $header);

    // Write data (inner array loop)
    foreach ($wlan_details as $detail) {
        if (is_array($detail)) {
            $row = array(
                isset($detail['zone_name']) ? $detail['zone_name'] : '',
                isset($detail['ssid']) ? $detail['ssid'] : '',
                isset($detail['type']) ? $detail['type'] : '',
                isset($detail['method']) ? $detail['method'] : '',
                isset($detail['algorithm']) ? $detail['algorithm'] : '',
                isset($detail['passphrase']) ? $detail['passphrase'] : '',
                isset($detail['saePassphrase']) ? $detail['saePassphrase'] : ''
            );
            fputcsv($output, $row);
        }
    }

    fclose($output);
    exit;
}

// Form processing and API calls
$controller_version = isset($_POST['controller_version']) ? $_POST['controller_version'] : '';
$sz_ip = isset($_POST['sz_ip']) ? $_POST['sz_ip'] : '';
$api_ver = isset($_POST['api_ver']) ? $_POST['api_ver'] : '';
$id = isset($_POST['id']) ? $_POST['id'] : '';
$passwd = isset($_POST['passwd']) ? $_POST['passwd'] : '';
$zone_id = isset($_POST['zone_id']) ? $_POST['zone_id'] : '';
$wlan_id = isset($_POST['wlan_id']) ? $_POST['wlan_id'] : '';
$encryption_method = isset($_POST['encryption_method']) ? $_POST['encryption_method'] : '';
$new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';

$service_ticket = null;
$zone_list = array();
$wlan_list = array();
$wlan_details = array();
$zone_output = '';
$wlan_output = '';
$details_output = '';
$update_output = '';
$json_output = array();

$is_update_all_wlans = isset($_POST['update_all_wlans_trigger']);

// Handle CSV download request
if (isset($_POST['download_csv']) && isset($_POST['details_data'])) {
	$details_data = json_decode(base64_decode($_POST['details_data']), true);
	if (!empty($details_data)) {
		download_csv($details_data);
	}
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['download_csv'])) {
    if (!empty($sz_ip) && !empty($api_ver) && !empty($id) && !empty($passwd)) {
        $ticket_result = get_service_ticket($sz_ip, $api_ver, $id, $passwd);
        $json_output = array_merge($json_output, $ticket_result['json_query']);

        if ($ticket_result['success']) {
            $service_ticket = $ticket_result['serviceTicket'];
            if ($service_ticket) {
                $zone_result = get_zone_list($sz_ip, $api_ver, $service_ticket);
                $zone_output = $zone_result['message'];
                $zone_list = $zone_result['list'];
                $json_output = array_merge($json_output, $zone_result['json_query']);

                if (!empty($zone_id)) {
                    $wlan_result = get_wlan_list($sz_ip, $api_ver, $service_ticket, $zone_id, $zone_list);
                    $wlan_output = $wlan_result['message'];
                    $wlan_list = $wlan_result['list'];
                    $json_output = array_merge($json_output, $wlan_result['json_query']);
                }

                if (!empty($wlan_id) || $is_update_all_wlans) {
                    $fetch_wlan_id = ($wlan_id === 'all_wlans' || $is_update_all_wlans) ? 'all_wlans' : $wlan_id;
                    $details_result = get_wlan_details($sz_ip, $api_ver, $service_ticket, $zone_id, $fetch_wlan_id, $zone_list, $wlan_list);
                    $wlan_details = isset($details_result['details']) ? $details_result['details'] : array();
                    $details_output = $details_result['message'];
                    $json_output = array_merge($json_output, $details_result['json_query']);

                    if (isset($_POST['change_selected_wlans']) || isset($_POST['update_single_wlan']) || $is_update_all_wlans) {
                        $update_result = update_wlan_password($sz_ip, $api_ver, $service_ticket, $zone_id, $fetch_wlan_id, $encryption_method, $new_password, $zone_list, $wlan_list, $wlan_details);
                        $update_output = $update_result['message'];
                        $json_output = array_merge($json_output, $update_result['json_query']);

                        $details_result = get_wlan_details($sz_ip, $api_ver, $service_ticket, $zone_id, $fetch_wlan_id, $zone_list, $wlan_list);
                        $wlan_details = isset($details_result['details']) ? $details_result['details'] : array();
                        $details_output = $details_result['message'];
                        $json_output = array_merge($json_output, $details_result['json_query']);
                    }
                }
            } else {
                $zone_output = $ticket_result['message'];
            }
        } else {
            $zone_output = $ticket_result['message'];
        }
    } else {
        $zone_output = "<p>Please enter the IP address, API version, user ID, and password.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>SSID PSK Password Change</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; background-color: #f4f4f4; color: #333;
            background-color: #f4f7fa;
            color: #1a202c;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        .modern-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(90deg, #4c51bf, #7f9cf5);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-family: 'Poppins', sans-serif;
            font-size: 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 2rem;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
            animation: fadeIn 1s ease-in-out;
        }

		.menu-link {
            display: inline-block;
            margin: 5px 0;
            padding: 8px 15px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            text-decoration: none;
            color: #007bff;
            transition: background-color 0.3s;
        }

        .menu-link:hover {
            background-color: #e2e6ea;
        }

        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(-10px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .container {
			display: flex;
			gap: 2rem;
			max-width: 1500px;
			margin: auto;
			background: #fff;
			padding: 30px;
			border-radius: 8px;
			box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
		}

        .left-column {
            width: 35%;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .left-column-scroll {
            max-height: calc(300vh);
            overflow-y: auto;
            padding-right: 1rem;
        }

        .right-column {
            width: 65%;
        }
		h1 {
            color: #1a1a1a;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-top: 0;
        }

        h2, h3 {
            color: #2d3748;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        hr {
            border: 0;
            border-top: 1px solid #e2e8f0;
            margin: 1rem 0;
        }

        .result-section {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            max-height: 600px;
            overflow-y: auto;
        }

        .form-group {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
			flex-wrap: wrap;
        }
		
		.form-group button {
			display: block;
			text-align: left;
			margin-left: auto; /* Aligns button with input fields */
		}

        label {
            width: 150px;
            font-weight: 500;
            color: #4a5568;
            font-size: 14px;
        }

        input, select {
            width: 220px;
            padding: 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s ease;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #4c51bf;
            box-shadow: 0 0 0 2px rgba(76, 81, 191, 0.2);
        }

        button {
            background-color: #007BFF;
            color: #ffffff;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        button:hover {
            background-color: #434190;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        th, td {
            padding: 0.75rem 1rem;
            text-align: left;
            font-size: 14px;
            border: 1px solid #e2e8f0;
        }

        th {
            background-color: #edf2f7;
            font-weight: 600;
            color: #2d3748;
        }

        tr:last-child td {
            border-bottom: 1px solid #e2e8f0;
        }

        .table-with-download-btn {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .download-btn-container {
            display: flex;
            justify-content: flex-end;
        }

        .zone-list-table, .wlan-list-table, .wlan-details-table, .password-update-table {
            margin-top: 1rem;
        }

        .wlan-password-form {
            background-color: #f7fafc;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #e2e8f0;
        }

        .wlan-password-form h4 {
            margin: 0 0 0.75rem 0;
            font-size: 14px;
            font-weight: 600;
            color: #2d3748;
        }

        .not-changeable-wlan {
            background-color: #edf2f7;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #e2e8f0;
            color: #718096;
            font-size: 14px;
        }

        .password-field.hidden {
            display: none;
        }

        #json-query-section {
            background-color: #e6fffa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            max-height: 250px;
            overflow-y: auto;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        #toggle-json {
            background-color: #319795;
            margin-bottom: 1rem;
        }

        #toggle-json:hover {
            background-color: #2c7a7b;
        }

        #encryption_method_all, #new_password_all {
            font-size: 14px;
        }

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f7fafc;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a0aec0;
        }
    </style>
    <script>
        function toggleJsonDisplay() {
            var jsonSection = document.getElementById('json-query-section');
            var toggleButton = document.getElementById('toggle-json');
            if (jsonSection.style.display === 'none') {
                jsonSection.style.display = 'block';
				toggleButton.textContent = 'Hide API Query';
            } else {
                jsonSection.style.display = 'none';
                toggleButton.textContent = 'Show API Query';
            }
        }

        function togglePasswordField(index) {
            var methodSelect = document.getElementById('encryption_method_' + index);
            var passwordField = document.getElementById('password_field_' + index);
            var passwordInput = document.getElementById('new_password_' + index);
            if (methodSelect.value === 'no_change') {
                passwordField.classList.add('hidden');
                passwordInput.disabled = true;
            } else {
                passwordField.classList.remove('hidden');
                passwordInput.disabled = false;
            }
        }
    </script>
</head>
<body>
    <?php if ($show_api_query && !empty($json_output)) { ?>
        <div id="json-query-section">
            <h3>API Query</h3><hr>
            <?php echo implode('', $json_output); ?>
        </div>
        <button id="toggle-json" onclick="toggleJsonDisplay()">Hide API Query</button>
    <?php } ?>


    <div class="container">
        <div class="left-column">
        <a href="../portal/index.php" class="menu-link">☎ HOME</a><p>
                <h1>SSID PSK Password Change</h1>
            <div class="left-column-scroll">
                <form method="POST">
                    <div class="form-group">
                        <label for="controller_version">Controller Version:</label>
                        <select id="controller_version" name="controller_version" onchange="this.form.submit()" required>
                            <option value="">Select</option>
                            <?php foreach (array_keys($controller_api_map) as $version) { ?>
                                <option value="<?php echo htmlspecialchars($version); ?>" <?php echo $controller_version == $version ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($version); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="api_ver">API Version:</label>
                        <select id="api_ver" name="api_ver" required>
                            <option value="">Select</option>
                            <?php
                            if (!empty($controller_version) && isset($controller_api_map[$controller_version])) {
                                $api_versions = array_reverse($controller_api_map[$controller_version]);
                                foreach ($api_versions as $api) {
                                    echo '<option value="' . htmlspecialchars($api) . '" ' . ($api_ver == $api ? 'selected' : '') . '>' . htmlspecialchars($api) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="sz_ip">SZ IP Address:</label>
                        <input type="text" id="sz_ip" name="sz_ip" value="<?php echo htmlspecialchars($sz_ip); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="id">Username:</label>
                        <input type="text" id="id" name="id" value="<?php echo htmlspecialchars($id); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="passwd">Password:</label>
                        <input type="password" id="passwd" name="passwd" required>
                    </div>
                    <p><button type="submit" class="submit-btn">Get Zone List</button></p>
                </form>

                <?php if (!empty($zone_list)) { ?>
                    <form method="POST">
                        <input type="hidden" name="controller_version" value="<?php echo htmlspecialchars($controller_version); ?>">
                        <input type="hidden" name="sz_ip" value="<?php echo htmlspecialchars($sz_ip); ?>">
                        <input type="hidden" name="api_ver" value="<?php echo htmlspecialchars($api_ver); ?>">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
                        <input type="hidden" name="passwd" value="<?php echo htmlspecialchars($passwd); ?>">
                        <div class="form-group">
                            <label for="zone_id">Select Zone:</label>
                            <select id="zone_id" name="zone_id" required>
                                <option value="">Select</option>
                                <option value="all_zones" <?php echo $zone_id == 'all_zones' ? 'selected' : ''; ?>>All Zones</option>
                                <?php foreach ($zone_list as $zone) { ?>
                                    <option value="<?php echo htmlspecialchars($zone['id']); ?>" <?php echo $zone_id == $zone['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($zone['name']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <p><button type="submit" class="submit-btn">Get WLAN List for this Zone</button></p>
                    </form>
                <?php } ?>

                <?php if (!empty($wlan_list)) { ?>
                    <form method="POST">
                        <input type="hidden" name="controller_version" value="<?php echo htmlspecialchars($controller_version); ?>">
                        <input type="hidden" name="sz_ip" value="<?php echo htmlspecialchars($sz_ip); ?>">
                        <input type="hidden" name="api_ver" value="<?php echo htmlspecialchars($api_ver); ?>">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
                        <input type="hidden" name="passwd" value="<?php echo htmlspecialchars($passwd); ?>">
                        <input type="hidden" name="zone_id" value="<?php echo htmlspecialchars($zone_id); ?>">
                        <div class="form-group">
                            <label for="wlan_id">Select WLAN:</label>
                            <select id="wlan_id" name="wlan_id" required>
                                <option value="all_wlans" <?php echo $wlan_id == 'all_wlans' ? 'selected' : ''; ?>>All WLANs in this Zone</option>
                            </select>
                        </div>
                        <p><button type="submit" class="submit-btn">Get Details for All WLANs in this Zone</button></p>
                    </form>
                <?php } ?>

                <?php
                // Note: Added array check (for safety)
                if (!empty($wlan_details) && is_array($wlan_details)) {
                ?>
                    <p>
                    <hr>
                    <h2>Change Individual WLAN Password</h2>
                    <hr>
                    <form method="POST">
                        <input type="hidden" name="controller_version" value="<?php echo htmlspecialchars($controller_version); ?>">
                        <input type="hidden" name="sz_ip" value="<?php echo htmlspecialchars($sz_ip); ?>">
                        <input type="hidden" name="api_ver" value="<?php echo htmlspecialchars($api_ver); ?>">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
                        <input type="hidden" name="passwd" value="<?php echo htmlspecialchars($passwd); ?>">
                        <input type="hidden" name="zone_id" value="<?php echo htmlspecialchars($zone_id); ?>">
                        <input type="hidden" name="wlan_id" value="all_wlans">
                        <?php
                        $any_changeable_wlan = false;
                        foreach ($wlan_details as $index => $detail) {
                            if (is_array($detail) && $detail['changeable']) {
                                $any_changeable_wlan = true;
                        ?>
                                <div class="wlan-password-form">
                                    <h4><?php echo htmlspecialchars($detail['ssid'] . ' (Zone: ' . $detail['zone_name'] . ')'); ?></h4>
                                    <div class="form-group">
                                        <label for="encryption_method_<?php echo $index; ?>">Select Encryption Method:</label>
                                        <select id="encryption_method_<?php echo $index; ?>" name="encryption_methods[<?php echo $index; ?>]" onchange="togglePasswordField(<?php echo $index; ?>)">
                                            <option value="no_change">Do Not Change</option>
                                            <option value="WPA2">WPA2</option>
                                            <option value="WPA3">WPA3</option>
                                        </select>
                                    </div>
                                    <div class="form-group password-field hidden" id="password_field_<?php echo $index; ?>">
                                        <label for="new_password_<?php echo $index; ?>">New Password:</label>
                                        <input type="text" id="new_password_<?php echo $index; ?>" name="passwords[<?php echo $index; ?>]" disabled>
                                    </div>
                                </div>
                        <?php
                            } else {
                                $any_changeable_wlan = true;
                        ?>
                                <div class="not-changeable-wlan">
                                    <h4><?php echo htmlspecialchars((isset($detail['ssid']) ? $detail['ssid'] : 'Unknown') . ' (Zone: ' . (isset($detail['zone_name']) ? $detail['zone_name'] : 'Unknown') . ')'); ?></h4>
                                    <p>Not PSK</p>
                                </div>
                        <?php
                            }
                        }
                        if ($any_changeable_wlan) {
                        ?>
                            <button type="submit" name="change_selected_wlans">Apply Changes</button><p>
                        <?php } else {
                            echo "<p>No changeable WLANs found.</p>";
                        }
                        ?>
                    </form>

                    <?php if ($wlan_id === 'all_wlans') { ?>
                        <p>
                        <hr>
                        <h2>All WLANs (PSK, SAE Only)<br>Bulk Password Change (Same Password)</h2>
                        <hr>
                        <form method="POST">
                            <input type="hidden" name="controller_version" value="<?php echo htmlspecialchars($controller_version); ?>">
                            <input type="hidden" name="sz_ip" value="<?php echo htmlspecialchars($sz_ip); ?>">
                            <input type="hidden" name="api_ver" value="<?php echo htmlspecialchars($api_ver); ?>">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
                            <input type="hidden" name="passwd" value="<?php echo htmlspecialchars($passwd); ?>">
                            <input type="hidden" name="zone_id" value="<?php echo htmlspecialchars($zone_id); ?>">
                            <input type="hidden" name="wlan_id" value="all_wlans">
                            <input type="hidden" name="update_all_wlans_trigger" value="1">
                            <div class="form-group">
                                <label for="encryption_method_all">Select Encryption Method:</label>
                                <select id="encryption_method_all" name="encryption_method" required>
                                    <option value="">Select</option>
                                    <option value="WPA2">WPA2</option>
                                    <option value="WPA3">WPA3</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="new_password_all">New Password:</label>
                                <input type="text" id="new_password_all" name="new_password" required>
                            </div>
                            <button type="submit">Change All WLAN Passwords</button>
                        </form>
                    <?php } ?>
                <?php } ?>
            </div>
        <a href="../portal/index.php" class="menu-link">☎ HOME</a><p>
        </div>

        <div class="right-column">
            <?php if (!empty($zone_output)) { ?>
                <h3>Zone List</h3><hr>
                <div class="result-section">
                    <?php echo $zone_output; ?>
                </div>
            <?php } ?>
            <?php if (!empty($wlan_output)) { ?>
                <h3>WLAN List</h3><hr>
                <div class="result-section">
                    <?php echo $wlan_output; ?>
                </div>
            <?php } ?>
            <?php if (!empty($details_output)) { ?>
                <h3>WLAN Details</h3><hr>
                <div class="result-section table-with-download-btn">
                    <div class="download-btn-container">
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="download_csv" value="1">
                            <input type="hidden" name="details_data" value="<?php echo htmlspecialchars(base64_encode(json_encode($wlan_details))); ?>">
                            <button type="submit" class="btn"><i class="fas fa-download"></i> Download CSV</button>
                        </form>
                    </div>
                    <?php echo $details_output; ?>
                </div>
            <?php } ?>
            <?php if (!empty($update_output)) { ?>
                <h3>Password Change Result</h3><hr>
                <div class="result-section">
                    <?php echo $update_output; ?>
                </div>
            <?php } ?>
        </div>
    </div>
</body>
</html>