<?php
// PHP 5.4 compatible
// Set default timezone to avoid database corruption issues
date_default_timezone_set('Asia/Seoul');

// Initialize variables to store user input
$szIp = '';
$username = '';
$password = '';
$controllerVersion = '';
$apiVersion = '';
$serviceTicket = '';
$apData = [];
$bssidData = [];
$switchData = [];
$clusterName = '';
$apCount = 0;
$bssidCount = 0;
$switchCount = 0;
$errorMessage = '';
$szControllerVersion = ''; // Controller version obtained from API call result

// Controller version and API version mapping information (sorted from highest version)
$controller_api_map = array(
    '7.1.1' => array('v13_1', 'v13_0', 'v12_0', 'v11_1', 'v11_0'),
    '7.1.0' => array('v13_0', 'v12_0', 'v11_1', 'v11_0'),
    '7.0.0' => array('v12_0', 'v11_1', 'v11_0', 'v10_0'),
    '6.1.2' => array('v11_1', 'v11_0', 'v10_0', 'v9_1', 'v9_0'),
    '6.1.1' => array('v11_1', 'v11_0', 'v10_0', 'v9_1', 'v9_0'),
    '6.1.0' => array('v11_0', 'v10_0', 'v9_1', 'v9_0'),
    '6.0.0' => array('v10_0', 'v9_1', 'v9_0'),
    '5.2.0' => array('v9_0', 'v8_2', 'v8_1', 'v8_0', 'v7_0', 'v6_1', 'v6_0'),
    'Manual Selection' => array('v13_1', 'v13_0', 'v12_0', 'v11_1', 'v11_0', 'v10_0', 'v9_1', 'v9_0', 'v8_2', 'v8_1', 'v8_0', 'v7_0', 'v6_1', 'v6_0')
);

// API call function
function callAPI($url, $method = 'GET', $data = null, $headers = array()) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    
    if ($method == 'POST') {
        curl_setopt($curl, CURLOPT_POST, true);
        if ($data !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $defaultHeaders = array('Content-Type: application/json;charset=UTF-8');
    if (!empty($headers)) {
        $mergedHeaders = array_merge($defaultHeaders, $headers);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $mergedHeaders);
    } else {
        curl_setopt($curl, CURLOPT_HTTPHEADER, $defaultHeaders);
    }

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        return array('error' => 'cURL Error: ' . $error);
    }
    
    $result = json_decode($response, true);
    
    return array(
        'httpCode' => $httpCode,
        'data' => $result
    );
}

// Request processing logic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    $szIp = $_POST['sz_ip'];
    $username = $_POST['user_id'];
    $password = $_POST['password'];
    $controllerVersion = $_POST['controller_version'];
    $apiVersion = $_POST['api_version'];

    $apiBaseUrl = "https://$szIp:8443/wsg/api/public/$apiVersion";
    $apiSwitchBaseUrl = "https://$szIp:8443/switchm/api/$apiVersion";

    // 3. Service Ticket API call
    $stUrl = "$apiBaseUrl/serviceTicket";
    $stData = array('username' => $username, 'password' => $password);
    $stResponse = callAPI($stUrl, 'POST', $stData);

    if (isset($stResponse['data']['serviceTicket'])) {
        $serviceTicket = $stResponse['data']['serviceTicket'];
        // Extract controllerVersion value from serviceTicket API result
        if (isset($stResponse['data']['controllerVersion'])) {
            $szControllerVersion = $stResponse['data']['controllerVersion'];
        }

        // 4. Cluster info API call
        $clusterUrl = "$apiBaseUrl/cluster/state?serviceTicket=$serviceTicket";
        $clusterResponse = callAPI($clusterUrl);
        if (isset($clusterResponse['data']['clusterName'])) {
            $clusterName = $clusterResponse['data']['clusterName'];
        }

        // 5. AP list API call
        $apUrl = "$apiBaseUrl/query/ap?serviceTicket=$serviceTicket";
        $apPostData = array(
            'filters' => array(array('type' => 'DOMAIN', 'value' => '8b2081d5-9662-40d9-a3db-2a3cf4dde3f7')),
            'fullTextSearch' => array('type' => 'AND', 'value' => ''),
            'attributes' => array('*'),
            'limit' => 10000
        );
        $apResponse = callAPI($apUrl, 'POST', $apPostData);
        if (isset($apResponse['data']['list'])) {
            $apData = $apResponse['data']['list'];
            $apCount = count($apData);
        }

        // 7. BSSID list API call
        $bssidUrl = "$apiBaseUrl/query/ap/wlan?serviceTicket=$serviceTicket";
        $bssidPostData = array(
            'filters' => array(array('type' => 'DOMAIN', 'value' => '8b2081d5-9662-40d9-a3db-2a3cf4dde3f7')),
            'fullTextSearch' => array('type' => 'AND', 'value' => ''),
            'limit' => 10000
        );
        $bssidResponse = callAPI($bssidUrl, 'POST', $bssidPostData);
        if (isset($bssidResponse['data']['list'])) {
            $bssidData = $bssidResponse['data']['list'];
        }
        
        // Add logic to calculate BSSID row count
        $bssidRowCount = 0;
        foreach ($bssidData as $bssid) {
            if (isset($bssid['wlanBssids']) && is_array($bssid['wlanBssids'])) {
                $bssidRowCount += count($bssid['wlanBssids']);
            }
        }
        $bssidCount = $bssidRowCount;

        // 8. Switch list API call
        $switchUrl = "$apiSwitchBaseUrl/switch?serviceTicket=$serviceTicket";
        $switchPostData = array(
            'filters' => array(array('type' => 'DOMAIN', 'value' => '8b2081d5-9662-40d9-a3db-2a3cf4dde3f7')),
            'fullTextSearch' => array('type' => 'AND', 'value' => ''),
            'sortInfo' => array('sortColumn' => 'serialNumber', 'dir' => 'ASC'),
            'page' => 1,
            'limit' => 10000
        );
        $switchResponse = callAPI($switchUrl, 'POST', $switchPostData);
        if (isset($switchResponse['data']['list'])) {
            $switchData = $switchResponse['data']['list'];
            $switchCount = count($switchData);
        }
    } else {
        $errorMessage = "API call failed: Could not get service ticket. Please check IP, ID, and PW.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ruckus SmartZone API Tool</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; background-color: #f4f4f4; color: #333; }
        .container { max-width: 1200px; margin: auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
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
		h1 {
            color: #d9534f;
            text-align: center;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-top: 0;
        }        
		h2, h3 { color: #1a1a1a; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 0; }
        form { background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        label { font-weight: bold; display: block; margin-bottom: 5px; }
        input[type="text"], input[type="password"], select { width: 100%; padding: 8px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        input[type="submit"] { background-color: #007BFF; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        input[type="submit"]:hover { background-color: #0056b3; }
        .error { color: #d9534f; font-weight: bold; }
        .download-link { background-color: #007BFF; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 14px; margin-left: 10px; }
        .download-link:hover { background-color: #0056b3; }
        .scrollable-table-container { overflow-x: auto; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; white-space: nowrap; }
        td { white-space: nowrap; }
    </style>
    <script>
        var controllerApiMap = <?php echo json_encode($controller_api_map); ?>;
        
        function updateApiVersions() {
            var controllerSelect = document.getElementById('controller_version');
            var apiSelect = document.getElementById('api_version');
            var selectedController = controllerSelect.value;
            
            apiSelect.innerHTML = '';
            
            var apiVersions = controllerApiMap[selectedController] || [];
            
            apiVersions.forEach(function(version) {
                var option = document.createElement('option');
                option.value = version;
                option.textContent = version;
                apiSelect.appendChild(option);
            });

            if (selectedController === '') {
                 apiSelect.innerHTML = '<option value="">Select Controller Version first</option>';
            }
        }
        
        window.onload = function() {
            updateApiVersions();
            var controllerSelect = document.getElementById('controller_version');
            var apiSelect = document.getElementById('api_version');
            
            controllerSelect.value = "<?php echo htmlspecialchars($controllerVersion); ?>";
            updateApiVersions();
            apiSelect.value = "<?php echo htmlspecialchars($apiVersion); ?>";
        };
    </script>
</head>
<body>
<div class="container">
<a href="../portal/index.php" class="menu-link">☎ HOME</a>
    <h1>Ruckus SmartZone API Tool</h1>
    <p>Enter the information below and run the API to query the AP and switch lists.</p>
    <form method="POST">
        <label for="controller_version">Controller Version:</label>
        <select name="controller_version" id="controller_version" onchange="updateApiVersions()">
            <option value="">Select</option>
            <?php
            foreach (array_keys($controller_api_map) as $cv) {
                echo "<option value=\"$cv\">$cv</option>";
            }
            ?>
        </select>
        
        <label for="api_version">API Version:</label>
        <select name="api_version" id="api_version">
             <option value="">Select Controller Version first</option>
        </select>
        
        <label for="sz_ip">SZ IP Address:</label>
        <input type="text" id="sz_ip" name="sz_ip" value="<?php echo htmlspecialchars($szIp); ?>" required>

        <label for="user_id">Username:</label>
        <input type="text" id="user_id" name="user_id" value="<?php echo htmlspecialchars($username); ?>" required>

        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>
        
        <input type="submit" name="submit" value="Run API and View Results">
    </form>

    <?php if (isset($errorMessage) && $errorMessage): ?>
        <p class="error"><?php echo htmlspecialchars($errorMessage); ?></p>
    <?php elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && $serviceTicket): ?>
        
        <hr>
        <h2>Results</h2>
        
        <div class="card">
            <h3>1. Cluster Information</h3>
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Controller IP</td>
                        <td><?php echo htmlspecialchars($szIp); ?></td>
                    </tr>
                    <tr>
                        <td>Cluster Name</td>
                        <td><?php echo htmlspecialchars($clusterName); ?></td>
                    </tr>
                    <tr>
                        <td>Controller Version</td>
                        <td><?php echo htmlspecialchars($szControllerVersion); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p><p>
        
        <div class="card">
            <h3>2. AP List (Total: <?php echo $apCount; ?>)
            <a href="data:text/csv;charset=utf-8,%EF%BB%BF<?php
                $csvData = "AP_Name,IP,AP_MAC,serial,Model,channel2G,channel5G,channel6G,status,config_status,firmwareVer,airtime2G,airtime5G,airtime6G,noise2G,noise5G,noise6G,eirp2G,eirp5G,eirp6G,Clients,poePort,ZoneDomain\n";
                foreach ($apData as $ap) {
                    $row = array(
                        isset($ap['deviceName']) ? $ap['deviceName'] : 'N/A',
                        isset($ap['ip']) ? $ap['ip'] : 'N/A',
                        isset($ap['apMac']) ? $ap['apMac'] : 'N/A',
                        isset($ap['serial']) ? $ap['serial'] : 'N/A',
                        isset($ap['model']) ? $ap['model'] : 'N/A',
                        (isset($ap['channel24G']) && $ap['channel24G']) ? $ap['channel24G'] : 'N/A',
                        (isset($ap['channel5G']) && $ap['channel5G']) ? $ap['channel5G'] : 'N/A',
                        (isset($ap['channel6G']) && $ap['channel6G']) ? $ap['channel6G'] : 'N/A',
                        isset($ap['connectionStatus']) ? $ap['connectionStatus'] : 'N/A',
                        isset($ap['configurationStatus']) ? $ap['configurationStatus'] : 'N/A',
                        isset($ap['firmwareVersion']) ? $ap['firmwareVersion'] : 'N/A',
                        isset($ap['airtime24G']) ? $ap['airtime24G'] : 'N/A',
                        isset($ap['airtime5G']) ? $ap['airtime5G'] : 'N/A',
                        isset($ap['airtime6G']) ? $ap['airtime6G'] : 'N/A',
                        isset($ap['noise24G']) ? $ap['noise24G'] : 'N/A',
                        isset($ap['noise5G']) ? $ap['noise5G'] : 'N/A',
                        isset($ap['noise6G']) ? $ap['noise6G'] : 'N/A',
                        isset($ap['eirp24G']) ? $ap['eirp24G'] : 'N/A',
                        isset($ap['eirp50G']) ? $ap['eirp50G'] : 'N/A',
                        isset($ap['eirp6G']) ? $ap['eirp6G'] : 'N/A',
                        isset($ap['numClients']) ? $ap['numClients'] : 'N/A',
                        isset($ap['poePortStatus']) ? $ap['poePortStatus'] : 'N/A',
                        (isset($ap['zoneName']) ? $ap['zoneName'] : 'N/A') . ' (' . (isset($ap['domainName']) ? $ap['domainName'] : 'N/A') . ')'
                    );
                    $csvData .= implode(',', array_map(function($val) {
                        return '"' . str_replace('"', '""', $val) . '"';
                    }, $row)) . "\n";
                }
                echo rawurlencode($csvData);
            ?>" download="<?php echo htmlspecialchars($szIp); ?>_AP_list_<?php echo date('Ymd_His'); ?>.csv" class="download-link">Download CSV</a>
            </h3>
            
            <div class="scrollable-table-container">
                <table>
                    <thead>
                        <tr>
                            <th>AP_Name</th><th>IP</th><th>AP_MAC</th><th>serial</th><th>Model</th><th>channel2G</th><th>channel5G</th><th>channel6G</th><th>status</th><th>config_status</th><th>firmwareVer</th><th>airtime2G</th><th>airtime5G</th><th>airtime6G</th><th>noise2G</th><th>noise5G</th><th>noise6G</th><th>eirp2G</th><th>eirp5G</th><th>eirp6G</th><th>Clients</th><th>poePort</th><th>ZoneDomain</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($apData as $ap): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(isset($ap['deviceName']) ? $ap['deviceName'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($ap['ip']) ? $ap['ip'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($ap['apMac']) ? $ap['apMac'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($ap['serial']) ? $ap['serial'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($ap['model']) ? $ap['model'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars((isset($ap['channel24G']) && $ap['channel24G']) ? $ap['channel24G'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars((isset($ap['channel5G']) && $ap['channel5G']) ? $ap['channel5G'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars((isset($ap['channel6G']) && $ap['channel6G']) ? $ap['channel6G'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($ap['connectionStatus']) ? $ap['connectionStatus'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($ap['configurationStatus']) ? $ap['configurationStatus'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($ap['firmwareVersion']) ? $ap['firmwareVersion'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($ap['airtime24G']) ? $ap['airtime24G'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($ap['airtime5G']) ? $ap['airtime5G'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($ap['airtime6G']) ? $ap['airtime6G'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($ap['noise24G']) ? $ap['noise24G'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($ap['noise5G']) ? $ap['noise5G'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($ap['noise6G']) ? $ap['noise6G'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($ap['eirp24G']) ? $ap['eirp24G'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($ap['eirp50G']) ? $ap['eirp50G'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($ap['eirp6G']) ? $ap['eirp6G'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($ap['numClients']) ? $ap['numClients'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($ap['poePortStatus']) ? $ap['poePortStatus'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars((isset($ap['zoneName']) ? $ap['zoneName'] : 'N/A') . ' (' . (isset($ap['domainName']) ? $ap['domainName'] : 'N/A') . ')'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p><p>
        <div class="card">
            <h3>3. AP MAC/Serial List (Total: <?php echo $apCount; ?>)
            <a href="data:text/csv;charset=utf-8,%EF%BB%BF<?php
                $csvData = "AP_MAC,serial\n";
                foreach ($apData as $ap) {
                    $row = array(isset($ap['apMac']) ? $ap['apMac'] : 'N/A', isset($ap['serial']) ? $ap['serial'] : 'N/A');
                    $csvData .= implode(',', array_map(function($val) {
                        return '"' . str_replace('"', '""', $val) . '"';
                    }, $row)) . "\n";
                }
                echo rawurlencode($csvData);
            ?>" download="<?php echo htmlspecialchars($szIp); ?>_AP_mac_serial_list_<?php echo date('Ymd_His'); ?>.csv" class="download-link">Download CSV</a>
            </h3>
            
            <div class="scrollable-table-container">
                <table>
                    <thead>
                        <tr>
                            <th>AP_MAC</th><th>serial</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($apData as $ap): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(isset($ap['apMac']) ? $ap['apMac'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($ap['serial']) ? $ap['serial'] : 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p><p>
        <div class="card">
            <h3>4. BSSID List (Total: <?php echo $bssidCount; ?>)
            <a href="data:text/csv;charset=utf-8,%EF%BB%BF<?php
                $csvData = "deviceName,apMac,wlanName,bssid,radioid,ip,eirp2G,eirp5G,eirp6G\n";
                $bssidRows = array();
                foreach ($bssidData as $bssid) {
                    $apMac = isset($bssid['apMac']) ? $bssid['apMac'] : '';
                    $deviceName = isset($bssid['deviceName']) ? $bssid['deviceName'] : '';
                    $apInfo = null;
                    foreach ($apData as $ap) {
                        if (isset($ap['apMac']) && $ap['apMac'] == $apMac) {
                            $apInfo = $ap;
                            break;
                        }
                    }
                    $ip = ($apInfo && isset($apInfo['ip'])) ? $apInfo['ip'] : 'N/A';
                    $eirp2g = ($apInfo && isset($apInfo['eirp24G'])) ? $apInfo['eirp24G'] : 'N/A';
                    $eirp5g = ($apInfo && isset($apInfo['eirp50G'])) ? $apInfo['eirp50G'] : 'N/A';
                    $eirp6g = ($apInfo && isset($apInfo['eirp6G'])) ? $apInfo['eirp6G'] : 'N/A';
                    
                    if (isset($bssid['wlanBssids']) && is_array($bssid['wlanBssids'])) {
                        foreach ($bssid['wlanBssids'] as $wlan) {
                            $radioId = isset($wlan['radioId']) ? $wlan['radioId'] : null;
                            $radioDesc = 'N/A';
                            if ($radioId === 0) {
                                $radioDesc = '2.4_Ghz';
                            } elseif ($radioId === 1) {
                                $radioDesc = '5.0_Ghz';
                            } elseif ($radioId === 2) {
                                $radioDesc = '6.0_Ghz';
                            }
                            $bssidRows[] = array(
                                $deviceName,
                                $apMac,
                                isset($wlan['wlanName']) ? $wlan['wlanName'] : 'N/A',
                                isset($wlan['bssid']) ? $wlan['bssid'] : 'N/A',
                                $radioDesc,
                                $ip,
                                $eirp2g,
                                $eirp5g,
                                $eirp6g
                            );
                        }
                    }
                }
                foreach ($bssidRows as $row) {
                    $csvData .= implode(',', array_map(function($val) {
                        return '"' . str_replace('"', '""', $val) . '"';
                    }, $row)) . "\n";
                }
                echo rawurlencode($csvData);
            ?>" download="<?php echo htmlspecialchars($szIp); ?>_BSSID_list_<?php echo date('Ymd_His'); ?>.csv" class="download-link">Download CSV</a>
            </h3>
            
            <div class="scrollable-table-container">
                <table>
                    <thead>
                        <tr>
                            <th>deviceName</th><th>apMac</th><th>wlanName</th><th>bssid</th><th>radioid</th><th>ip</th><th>eirp2G</th><th>eirp5G</th><th>eirp6G</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                        foreach ($bssidData as $bssid_info): 
                            $apMac = isset($bssid_info['apMac']) ? $bssid_info['apMac'] : '';
                            $deviceName = isset($bssid_info['deviceName']) ? $bssid_info['deviceName'] : '';
                            $apInfo = null;
                            foreach ($apData as $ap) {
                                if (isset($ap['apMac']) && $ap['apMac'] == $apMac) {
                                    $apInfo = $ap;
                                    break;
                                }
                            }
                            $ip = ($apInfo && isset($apInfo['ip'])) ? $apInfo['ip'] : 'N/A';
                            $eirp2g = ($apInfo && isset($apInfo['eirp24G'])) ? $apInfo['eirp24G'] : 'N/A';
                            $eirp5g = ($apInfo && isset($apInfo['eirp50G'])) ? $apInfo['eirp50G'] : 'N/A';
                            $eirp6g = ($apInfo && isset($apInfo['eirp6G'])) ? $apInfo['eirp6G'] : 'N/A';
                            if (isset($bssid_info['wlanBssids']) && is_array($bssid_info['wlanBssids'])):
                                foreach ($bssid_info['wlanBssids'] as $wlan): 
                                    $radioId = isset($wlan['radioId']) ? $wlan['radioId'] : null;
                                    $radioDesc = 'N/A';
                                    if ($radioId === 0) {
                                        $radioDesc = '2.4_Ghz';
                                    } elseif ($radioId === 1) {
                                        $radioDesc = '5.0_Ghz';
                                    } elseif ($radioId === 2) {
                                        $radioDesc = '6.0_Ghz';
                                    }
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($deviceName); ?></td>
                            <td><?php echo htmlspecialchars($apMac); ?></td>
                            <td><?php echo htmlspecialchars(isset($wlan['wlanName']) ? $wlan['wlanName'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($wlan['bssid']) ? $wlan['bssid'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($radioDesc); ?></td>
                            <td><?php echo htmlspecialchars($ip); ?></td>
                            <td><?php echo htmlspecialchars($eirp2g); ?></td>
                            <td><?php echo htmlspecialchars($eirp5g); ?></td>
                            <td><?php echo htmlspecialchars($eirp6g); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p><p>
        <div class="card">
            <h3>5. Switch List (Total: <?php echo $switchCount; ?>)
            <a href="data:text/csv;charset=utf-8,%EF%BB%BF<?php
                $csvData = "switchName,ipAddress,macAddress,serialNumber,model,status,upTime,firmwareVersion\n";
                foreach ($switchData as $switch) {
                    $status = (isset($switch['status']) && $switch['status']) ? $switch['status'] : 'N/A';
                    $upTime = ($status == 'OFFLINE') ? 'OFFLINE' : ((isset($switch['upTime']) && $switch['upTime']) ? $switch['upTime'] : 'N/A');
                    
                    $row = array(
                        isset($switch['switchName']) ? $switch['switchName'] : 'N/A',
                        isset($switch['ipAddress']) ? $switch['ipAddress'] : 'N/A',
                        isset($switch['macAddress']) ? $switch['macAddress'] : 'N/A',
                        isset($switch['serialNumber']) ? $switch['serialNumber'] : 'N/A',
                        isset($switch['model']) ? $switch['model'] : 'N/A',
                        $status,
                        $upTime,
                        isset($switch['firmwareVersion']) ? $switch['firmwareVersion'] : 'N/A'
                    );
                    $csvData .= implode(',', array_map(function($val) {
                        return '"' . str_replace('"', '""', $val) . '"';
                    }, $row)) . "\n";
                }
                echo rawurlencode($csvData);
            ?>" download="<?php echo htmlspecialchars($szIp); ?>_Switch_list_<?php echo date('Ymd_His'); ?>.csv" class="download-link">Download CSV</a>
            </h3>

            <div class="scrollable-table-container">
                <table>
                    <thead>
                        <tr>
                            <th>switchName</th><th>ipAddress</th><th>macAddress</th><th>serialNumber</th><th>model</th><th>status</th><th>upTime</th><th>firmwareVersion</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($switchData as $switch): ?>
                        <?php
                            $status = (isset($switch['status']) && $switch['status']) ? $switch['status'] : 'N/A';
                            $upTime = ($status == 'OFFLINE') ? 'OFFLINE' : ((isset($switch['upTime']) && $switch['upTime']) ? $switch['upTime'] : 'N/A');
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars(isset($switch['switchName']) ? $switch['switchName'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($switch['ipAddress']) ? $switch['ipAddress'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($switch['macAddress']) ? $switch['macAddress'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($switch['serialNumber']) ? $switch['serialNumber'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($switch['model']) ? $switch['model'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($status); ?></td>
                            <td><?php echo htmlspecialchars($upTime); ?></td>
                            <td><?php echo htmlspecialchars(isset($switch['firmwareVersion']) ? $switch['firmwareVersion'] : 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <p><p>
        <hr>
        <h3>
            Download All Lists Combined CSV
            <a href="data:text/csv;charset=utf-8,%EF%BB%BF<?php
                // Function to escape CSV values
                function escapeCsv($value) {
                    return '"' . str_replace('"', '""', $value) . '"';
                }

                $csvData = '';

                // Add Cluster Info section
                $csvData .= "Cluster Information\n";
                $csvData .= "Item,Value\n";
                $csvData .= escapeCsv('Controller IP') . ',' . escapeCsv($szIp) . "\n";
                $csvData .= escapeCsv('Cluster Name') . ',' . escapeCsv($clusterName) . "\n";
                $csvData .= escapeCsv('Controller Version') . ',' . escapeCsv($szControllerVersion) . "\n";
                $csvData .= "\n"; // Add a blank line for separation

                // Add AP List section
                $csvData .= "AP List\n";
                $csvData .= "AP_Name,IP,AP_MAC,serial,Model,channel2G,channel5G,channel6G,status,config_status,firmwareVer,airtime2G,airtime5G,airtime6G,noise2G,noise5G,noise6G,eirp2G,eirp5G,eirp6G,Clients,poePort,ZoneDomain\n";
                foreach ($apData as $ap) {
                    $row = array(
                        isset($ap['deviceName']) ? $ap['deviceName'] : 'N/A',
                        isset($ap['ip']) ? $ap['ip'] : 'N/A',
                        isset($ap['apMac']) ? $ap['apMac'] : 'N/A',
                        isset($ap['serial']) ? $ap['serial'] : 'N/A',
                        isset($ap['model']) ? $ap['model'] : 'N/A',
                        (isset($ap['channel24G']) && $ap['channel24G']) ? $ap['channel24G'] : 'N/A',
                        (isset($ap['channel5G']) && $ap['channel5G']) ? $ap['channel5G'] : 'N/A',
                        (isset($ap['channel6G']) && $ap['channel6G']) ? $ap['channel6G'] : 'N/A',
                        isset($ap['connectionStatus']) ? $ap['connectionStatus'] : 'N/A',
                        isset($ap['configurationStatus']) ? $ap['configurationStatus'] : 'N/A',
                        isset($ap['firmwareVersion']) ? $ap['firmwareVersion'] : 'N/A',
                        isset($ap['airtime24G']) ? $ap['airtime24G'] : 'N/A',
                        isset($ap['airtime5G']) ? $ap['airtime5G'] : 'N/A',
                        isset($ap['airtime6G']) ? $ap['airtime6G'] : 'N/A',
                        isset($ap['noise24G']) ? $ap['noise24G'] : 'N/A',
                        isset($ap['noise5G']) ? $ap['noise5G'] : 'N/A',
                        isset($ap['noise6G']) ? $ap['noise6G'] : 'N/A',
                        isset($ap['eirp24G']) ? $ap['eirp24G'] : 'N/A',
                        isset($ap['eirp50G']) ? $ap['eirp50G'] : 'N/A',
                        isset($ap['eirp6G']) ? $ap['eirp6G'] : 'N/A',
                        isset($ap['numClients']) ? $ap['numClients'] : 'N/A',
                        isset($ap['poePortStatus']) ? $ap['poePortStatus'] : 'N/A',
                        (isset($ap['zoneName']) ? $ap['zoneName'] : 'N/A') . ' (' . (isset($ap['domainName']) ? $ap['domainName'] : 'N/A') . ')'
                    );
                    $csvData .= implode(',', array_map('escapeCsv', $row)) . "\n";
                }
                $csvData .= "\n\n";

                // Add BSSID List section
                $csvData .= "BSSID List\n";
                $csvData .= "deviceName,apMac,wlanName,bssid,radioid,ip,eirp2G,eirp5G,eirp6G\n";
                foreach ($bssidData as $bssid) {
                    $apMac = isset($bssid['apMac']) ? $bssid['apMac'] : '';
                    $deviceName = isset($bssid['deviceName']) ? $bssid['deviceName'] : '';
                    $apInfo = null;
                    foreach ($apData as $ap) {
                        if (isset($ap['apMac']) && $ap['apMac'] == $apMac) {
                            $apInfo = $ap;
                            break;
                        }
                    }
                    $ip = ($apInfo && isset($apInfo['ip'])) ? $apInfo['ip'] : 'N/A';
                    $eirp2g = ($apInfo && isset($apInfo['eirp24G'])) ? $apInfo['eirp24G'] : 'N/A';
                    $eirp5g = ($apInfo && isset($apInfo['eirp50G'])) ? $apInfo['eirp50G'] : 'N/A';
                    $eirp6g = ($apInfo && isset($apInfo['eirp6G'])) ? $apInfo['eirp6G'] : 'N/A';
                    
                    if (isset($bssid['wlanBssids']) && is_array($bssid['wlanBssids'])) {
                        foreach ($bssid['wlanBssids'] as $wlan) {
                            $radioId = isset($wlan['radioId']) ? $wlan['radioId'] : null;
                            $radioDesc = 'N/A';
                            if ($radioId === 0) {
                                $radioDesc = '2.4_Ghz';
                            } elseif ($radioId === 1) {
                                $radioDesc = '5.0_Ghz';
                            } elseif ($radioId === 2) {
                                $radioDesc = '6.0_Ghz';
                            }
                            $row = array(
                                $deviceName,
                                $apMac,
                                isset($wlan['wlanName']) ? $wlan['wlanName'] : 'N/A',
                                isset($wlan['bssid']) ? $wlan['bssid'] : 'N/A',
                                $radioDesc,
                                $ip,
                                $eirp2g,
                                $eirp5g,
                                $eirp6g
                            );
                            $csvData .= implode(',', array_map('escapeCsv', $row)) . "\n";
                        }
                    }
                }
                $csvData .= "\n\n";

                // Add Switch List section
                $csvData .= "Switch List\n";
                $csvData .= "switchName,ipAddress,macAddress,serialNumber,model,status,upTime,firmwareVersion\n";
                foreach ($switchData as $switch) {
                    $status = (isset($switch['status']) && $switch['status']) ? $switch['status'] : 'N/A';
                    $upTime = ($status == 'OFFLINE') ? 'OFFLINE' : ((isset($switch['upTime']) && $switch['upTime']) ? $switch['upTime'] : 'N/A');
                    
                    $row = array(
                        isset($switch['switchName']) ? $switch['switchName'] : 'N/A',
                        isset($switch['ipAddress']) ? $switch['ipAddress'] : 'N/A',
                        isset($switch['macAddress']) ? $switch['macAddress'] : 'N/A',
                        isset($switch['serialNumber']) ? $switch['serialNumber'] : 'N/A',
                        isset($switch['model']) ? $switch['model'] : 'N/A',
                        $status,
                        $upTime,
                        isset($switch['firmwareVersion']) ? $switch['firmwareVersion'] : 'N/A'
                    );
                    $csvData .= implode(',', array_map('escapeCsv', $row)) . "\n";
                }
                
                echo rawurlencode($csvData);
            ?>" download="<?php echo htmlspecialchars($szIp); ?>_All_list_<?php echo date('Ymd_His'); ?>.csv" class="download-link">Download Combined CSV of All Lists</a>
        </h3>

    <?php endif; ?>
	<a href="../portal/index.php" class="menu-link">☎ HOME</a><p>
</div>
</body>
</html>
