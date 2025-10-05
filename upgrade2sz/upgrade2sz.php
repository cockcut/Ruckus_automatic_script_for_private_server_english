<?php
// Maintain PHP 5.4 compatibility (User request based)
session_start();

// Reset session variables if requested (for "New Script" link)
if (isset($_GET['reset'])) {
    unset($_SESSION['csv_path']);
    unset($_SESSION['csv_table']);
    unset($_SESSION['serviceTicket']);
    unset($_SESSION['zone_list']);
    unset($_SESSION['firmware_versions']);
    unset($_SESSION['selected_zone_id']);
    unset($_SESSION['selected_firmware_version']);
    unset($_SESSION['ready_to_run']);
    // SZ credentials are kept for convenience
    $reset_message = "🔄 Previous settings and uploaded content have been reset. Please upload a new CSV file.";
    $_SESSION['reset_message'] = $reset_message;
    header("Location: upgrade2sz.php");
    exit;
}

// Controller version and API version mapping (User request based)
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

$upload_dir = 'uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// --- SmartZone API Functions (Unchanged, kept for context) ---

// Disable SSL certificate verification
function disable_ssl_verification($ch) {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    return $ch;
}

// Get service ticket
function get_service_ticket($sz_ip, $api_ver, $id, $passwd) {
    $url = "https://$sz_ip:8443/wsg/api/public/$api_ver/serviceTicket";
    $payload = json_encode(array(
        "username" => $id,
        "password" => $passwd
    ));
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $ch = disable_ssl_verification($ch);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code != 200 || $response === false) {
        return array('success' => false, 'message' => "Service Ticket retrieval error (HTTP $http_code): " . ($response === false ? 'Curl Error' : $response));
    }
    $data = json_decode($response, true);
    return array('success' => true, 'serviceTicket' => isset($data['serviceTicket']) ? $data['serviceTicket'] : null);
}

// Get Zone list
function get_zone_list($sz_ip, $api_ver, $service_ticket) {
    $url = "https://$sz_ip:8443/wsg/api/public/$api_ver/rkszones?serviceTicket=$service_ticket";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $ch = disable_ssl_verification($ch);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code != 200 || $response === false) {
        return array('success' => false, 'message' => "Zone list retrieval error (HTTP $http_code): " . ($response === false ? 'Curl Error' : $response), 'list' => array());
    }
    $data = json_decode($response, true);
    $zone_list = array();
    if (isset($data['list'])) {
        foreach ($data['list'] as $item) {
            if (isset($item['name']) && isset($item['id'])) {
                $zone_list[] = array('name' => $item['name'], 'id' => $item['id']);
            }
        }
    }
    return array('success' => true, 'list' => $zone_list);
}

// AP Firmware list retrieval function
function get_ap_firmware_list($sz_ip, $api_ver, $service_ticket, $zone_id) {
    $url = "https://$sz_ip:8443/wsg/api/public/$api_ver/rkszones/$zone_id/apFirmware?serviceTicket=$service_ticket";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $ch = disable_ssl_verification($ch);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code != 200 || $response === false) {
        return array('success' => false, 'message' => "Firmware list request failed (HTTP $http_code): " . ($response === false ? 'Curl Error' : $response), 'list' => array());
    }
    $data = json_decode($response, true);
    $firmware_list = array();
    if (isset($data['list'])) {
        foreach ($data['list'] as $item) {
            if (isset($item['firmwareVersion']) && isset($item['supported']) && $item['supported'] === true) {
                $firmware_list[] = $item['firmwareVersion'];
            }
        }
    }
    return array('success' => true, 'list' => $firmware_list);
}

// CSV Example Table Function (Updated to 8 column format)
function get_example_csv_table() {
    $example_data = [
        ['current_ip', 'user', 'pass', 'new_IP', 'subnet', 'g/w', 'sz', 'hostname'],
        ['10.10.10.100', 'super', 'supe12345', '10.10.10.200', '255.255.255.0', '10.10.10.1', '100.100.100.100', 'ap1'],
        ['10.10.20.100', 'super', 'sp-admin', '10.10.20.234', '255.255.255.0', '10.10.20.254', '10.10.10.10', 'ap2'],
        ['10.10.20.200', 'super', 'abcdef', '10.10.20.235', '255.255.255.0', '10.10.20.254', '10.10.10.10', 'ap3']
    ];
    $table = "<div class='note' id='csv_example_table'>";
    $table .= "<div class='note-title'>※ The following shows an example of the contents of sz_sample.csv. Please download and edit the sample CSV above, then upload it.</div>";
    $table .= "<table class='styled-table'><thead><tr>";
    foreach ($example_data[0] as $header) {
        $table .= "<th>" . htmlspecialchars($header) . "</th>";
    }
    $table .= "</tr></thead><tbody>";
    for ($i = 1; $i < count($example_data); $i++) {
        $table .= "<tr>";
        for ($j = 0; $j < 8; $j++) {
            // Unmasking: Display as plain text
            $cell_display = $example_data[$i][$j];
            $table .= "<td>" . htmlspecialchars($cell_display) . "</td>";
        }
        $table .= "</tr>";
    }
    $table .= "</tbody></table></div>";
    return $table;
}


// --- File Upload and API Authentication Process ---

$error_message = '';
$success_message = '';
$api_message = '';

if (isset($_SESSION['reset_message'])) {
    $success_message = $_SESSION['reset_message'];
    unset($_SESSION['reset_message']);
}

$csv_preview_html = isset($_SESSION['csv_table']) ? $_SESSION['csv_table'] : get_example_csv_table();


// Step 0: Process CSV file upload only
if (isset($_POST['upload_csv'])) {
    $csv_file = $_FILES['csv_file'];
    
    // CSV file processing (Preview of uploaded file)
    if ($csv_file['error'] == UPLOAD_ERR_OK) {
        // Delete existing file and save new file
        if (isset($_SESSION['csv_path']) && file_exists($_SESSION['csv_path'])) {
            @unlink($_SESSION['csv_path']);
        }
        $csv_path = $upload_dir . basename($csv_file['name']);
        if (move_uploaded_file($csv_file['tmp_name'], $csv_path)) {
            $_SESSION['csv_path'] = $csv_path;
            $csv_upload_message = "✅ CSV file upload successful: " . htmlspecialchars($csv_file['name']);

            $raw_lines = file($csv_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $csv_data = array_map('str_getcsv', array_filter($raw_lines, function($line) {
                $trimmed_line = trim($line);
				// Exclude empty lines or lines with only commas (e.g., ,,,,,, )
                return $trimmed_line !== '' && !preg_match('/^,+$/', $trimmed_line);
            }));

            if ($csv_data && count($csv_data) > 0) {
                $csv_table = "<div class='note'>";
                //$csv_table .= "<div class='note-title'>Uploaded CSV Content (First 10 rows only shown) - Column check required</div>";
                $csv_table .= "<div class='note-title'>Uploaded CSV Content</div>";
				$csv_table .= "<table class='styled-table'><thead><tr>";
                // Display header
                $header_row = array_slice($csv_data[0], 0, 8); // Use up to 8 columns
                foreach ($header_row as $header) {
                    $csv_table .= "<th>" . htmlspecialchars($header) . "</th>";
                }
                $csv_table .= "</tr></thead><tbody>";
                
                // Display data
                //$max_rows = min(count($csv_data), 11);
				$max_rows = count($csv_data);
                for ($i = 1; $i < $max_rows; $i++) {
                    $csv_table .= "<tr>";
                    $data_row = array_slice($csv_data[$i], 0, 8); // Use up to 8 columns
                    for ($j = 0; $j < 8; $j++) {
                        $cell_display = isset($data_row[$j]) ? $data_row[$j] : '';
                        $csv_table .= "<td>" . htmlspecialchars($cell_display) . "</td>";
                    }
                    $csv_table .= "</tr>";
                }
                $csv_table .= "</tbody></table></div>";
                $_SESSION['csv_table'] = $csv_table;
                $csv_preview_html = $csv_table;
            } else {
                $csv_upload_message = "❌ CSV file upload successful, but file content read or parsing error";
                unset($_SESSION['csv_path']);
            }
            $success_message .= $csv_upload_message . "<br>";
        } else {
            $error_message .= "❌ CSV file upload failed: move_uploaded_file error<br>";
        }
    } else {
        $error_message .= "❌ CSV file upload error: " . $csv_file['error'] . "<br>";
    }
}


// Step 1: API Authentication and Zone List Fetch
if (isset($_POST['step1_submit'])) {
    $sz_ip = isset($_POST['sz_ip']) ? trim($_POST['sz_ip']) : '';
    $controller_ver = isset($_POST['controller_ver']) ? $_POST['controller_ver'] : '';
    $api_ver = isset($_POST['api_ver']) ? $_POST['api_ver'] : '';
    $id = isset($_POST['sz_user']) ? $_POST['sz_user'] : '';
    $passwd = isset($_POST['sz_pass']) ? $_POST['sz_pass'] : '';

    $_SESSION['sz_ip'] = $sz_ip;
    $_SESSION['controller_ver'] = $controller_ver;
    $_SESSION['api_ver'] = $api_ver;
    $_SESSION['sz_user'] = $id;
    $_SESSION['sz_pass'] = $passwd;
    
    // API Authentication and Zone List Retrieval
    if (isset($_SESSION['csv_path'])) {
        if (!empty($sz_ip) && !empty($api_ver) && !empty($id) && !empty($passwd)) {
            $auth_result = get_service_ticket($sz_ip, $api_ver, $id, $passwd);
            if ($auth_result['success']) {
                $_SESSION['serviceTicket'] = $auth_result['serviceTicket'];
                $api_message .= "✅ Service ticket retrieval successful! (Controller: *" . htmlspecialchars($controller_ver) . "*, API: *" . htmlspecialchars($api_ver) . "*)<br>";

                $zone_result = get_zone_list($sz_ip, $api_ver, $auth_result['serviceTicket']);
                if ($zone_result['success']) {
                    $_SESSION['zone_list'] = $zone_result['list'];
                    $api_message .= "✅ Zone list retrieval successful! " . count($zone_result['list']) . " Zones confirmed.<br>";
                } else {
                    $error_message .= $zone_result['message'] . "<br>";
                    unset($_SESSION['serviceTicket']);
                }
            } else {
                $error_message .= "❌ API authentication failed: " . $auth_result['message'] . "<br>";
            }
        } else {
            $error_message .= "SmartZone information and Controller/API version are required fields.<br>";
        }
    } else {
        $error_message .= "❌ The AP list CSV file must be uploaded first.<br>";
    }
}

// Step 2: Attempting Firmware list retrieval (unchanged)
if (isset($_POST['step2_submit']) && isset($_SESSION['serviceTicket']) && isset($_SESSION['sz_ip'])) {
    $zone_id = isset($_POST['zone_id']) ? $_POST['zone_id'] : '';
    $_SESSION['selected_zone_id'] = $zone_id;
    
    if (!empty($zone_id)) {
        $fw_result = get_ap_firmware_list($_SESSION['sz_ip'], $_SESSION['api_ver'], $_SESSION['serviceTicket'], $zone_id);
        
        if ($fw_result['success']) {
            $_SESSION['firmware_versions'] = $fw_result['list'];
            $success_message .= "✅ Firmware list for Zone ID *" . htmlspecialchars($zone_id) . "* successfully retrieved.<br>";
        } else {
            $error_message .= "❌ Firmware list retrieval failed: " . $fw_result['message'] . "<br>";
            unset($_SESSION['firmware_versions']);
        }
    } else {
        $error_message .= "A Zone must be selected.<br>";
    }
}

// Step 3: Final firmware version selection and upgrade execution preparation (unchanged)
if (isset($_POST['step3_submit']) && isset($_SESSION['firmware_versions'])) {
    $selected_firmware_version = isset($_POST['firmware_version']) ? $_POST['firmware_version'] : '';
    $_SESSION['selected_firmware_version'] = $selected_firmware_version;
    
    if (!empty($selected_firmware_version)) {
        $success_message .= "✅ Firmware version *" . htmlspecialchars($selected_firmware_version) . "* has been selected. The upgrade can be executed.<br>";
        $_SESSION['ready_to_run'] = true;
    } else {
        $error_message .= "A firmware version must be selected.<br>";
        unset($_SESSION['ready_to_run']);
    }
}


// --- Upgrade Execution (Server-Sent Events) ---
if (isset($_GET['run_upgrade'])) {
    // ... (logic for SSE execution, almost same as previous version)
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    ini_set('output_buffering', '0');
    ini_set('zlib.output_compression', 'Off');
    ini_set('max_execution_time', 0);
    while (@ob_get_level() > 0) @ob_end_clean();

    $csv_path = isset($_SESSION['csv_path']) ? $_SESSION['csv_path'] : '';
    $firmware_version = isset($_SESSION['selected_firmware_version']) ? $_SESSION['selected_firmware_version'] : '';
    $sz_ip = isset($_SESSION['sz_ip']) ? $_SESSION['sz_ip'] : '';
    $script = './szfwupdate.sh';
    $log_file = './szfwupdate.log';
    $result_file = './szfw_result.csv';

    if (file_exists($csv_path) && !empty($firmware_version) && !empty($sz_ip)) {
        
        if (file_exists($log_file)) @unlink($log_file);
        if (file_exists($result_file)) @unlink($result_file);

        // Pass 4 required arguments
        $command = "$script " . escapeshellarg($csv_path) . " " . escapeshellarg($firmware_version) . " " . escapeshellarg($sz_ip) . " " . escapeshellarg($result_file) . " 2>&1";

        echo "data: Execution command: $command\n\n";
        @flush();

        @file_put_contents($log_file, "Execution command: $command\n");
        $handle = @popen($command, 'r');
        if ($handle) {
            $start_time = time();
            $max_duration = 600; 
            while (!@feof($handle)) {
                $line = @fgets($handle);
                if ($line !== false) {
                    $encoded_line = htmlspecialchars($line);
                    echo "data: $encoded_line\n\n";
                    @file_put_contents($log_file, $line, FILE_APPEND);
                    @flush();
                }
                if ((time() - $start_time) > $max_duration) {
                    @pclose($handle);
                    $timeout_msg = "Forced termination due to process maximum execution time (10 minutes) exceeded\n";
                    echo "data: $timeout_msg\n\n";
                    @file_put_contents($log_file, $timeout_msg, FILE_APPEND);
                    @flush();
                    break;
                }
                @usleep(100000);
            }
            @pclose($handle);

            if (@file_exists($log_file)) {
                @chmod($log_file, 0666);
                echo "data: Log file: $log_file\n\n";
            }
            if (@file_exists($result_file)) {
                @chmod($result_file, 0666);
                echo "data: Result file: $result_file\n\n";
            }
        } else {
            echo "data: ❌ Script execution failed. (popen error)\n\n";
        }
    } else {
        $missing = [];
        if (empty($csv_path)) $missing[] = 'CSV file';
        if (empty($firmware_version)) $missing[] = 'Firmware Version';
        if (empty($sz_ip)) $missing[] = 'SmartZone IP';
        echo "data: ❌ Information required for upgrade (" . implode(', ', $missing) . ") is insufficient.\n\n";
    }

    //echo "data: Upgrade process complete\n\n";
    @flush();
    exit;
}

// --- HTML Output Section ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SmartZone AP Firmware Upgrade</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: auto;
            background: #fff;
            padding: 20px 40px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #d9534f;
            text-align: center;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        h3 {
            color: #0275d8;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-top: 25px;
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
        .note {
            background-color: #ffeeba;
            border-left: 5px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .note-title {
            color: #856404;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .note-text {
            color: #d9534f;
            font-weight: bold;
        }
        #output {
            white-space: pre-wrap;
            border: 1px solid #ccc;
            padding: 15px;
            height: 500px;
            overflow-y: auto;
            background-color: #f8f8f8;
            border-radius: 5px;
        }
        button {
            background-color: #5cb85c;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #4cae4c;
        }
        button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        .styled-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .styled-table th, .styled-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
			font-size: 12px;
        }
        .styled-table th {
            background-color: #f2f2f2;
        }
        .message {
            margin: 10px 0;
            padding: 10px;
            border-radius: 5px;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
		form { 
			background: #f9f9f9;
			padding: 20px;
			border-radius: 8px;
			margin-bottom: 20px; 
		}
		label { font-weight: bold; display: block; margin-bottom: 5px; }
		input[type="text"], input[type="password"], select { width: 100%; padding: 8px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        /*input[type="submit"] { background-color: #007BFF; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }*/
        /*input[type="submit"]:hover { background-color: #0056b3; }*/
        /* upgrade2sz.php dedicated style integration/maintenance */
        .firmware-version-item { display: inline-block; padding: 8px 15px; margin: 5px; background-color: #e9ecef; border-radius: 4px; cursor: pointer; border: 1px solid #ced4da; }
        .firmware-version-item:hover { background-color: #007bff; color: white; border-color: #007bff; }
    </style>
</head>
<body>

<div class="container">
    <a href="../portal/index.php" class="menu-link">☎ HOME</a>
    <h1>SmartZone AP Firmware Upgrade</h1>
        <a href="./upgrade2sz.php?reset=1" class="menu-link">◀ Start New Script)</a><br>
        <a href='./example/sz_sample.csv' class="menu-link" download>📁 Download sz_sample CSV file</a>
    
    <?php if (!empty($error_message)) echo "<p class='error-message message'>$error_message</p>"; ?>
    <?php if (!empty($success_message)) echo "<p class='message'>$success_message</p>"; ?>
    <?php if (!empty($api_message)) echo "<div class='note'>$api_message</div>"; ?>
    
    
    <h3>Step 0: Upload AP Information CSV File</h3>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="csv_file" accept=".csv" required>
        <input type="submit" name="upload_csv" value="Upload AP List">
    </form>
        
    <div id="csv_preview_area">
        <?php echo $csv_preview_html; ?>
    </div>
    
    
    <h3>Step 1: SmartZone Authentication and Zone List Retrieval</h3>
    <form method="POST">
            <label for="controller_version">Controller Version:</label>
            <select name="controller_ver" id="controller_ver_select" required>
                <option value="">-- Select Controller Version --</option>
                <?php
                $current_controller = isset($_SESSION['controller_ver']) ? $_SESSION['controller_ver'] : '';
                foreach (array_keys($controller_api_map) as $ver) {
                    $selected = ($ver === $current_controller) ? ' selected' : '';
                    echo "<option value=\"$ver\"" . $selected . ">$ver</option>";
                }
                ?>
            </select>
        </p>
        <p>
            <label for="api_ver_select">API Version:</label>
            <select name="api_ver" id="api_ver_select" required disabled>
                <option value="">-- Select API Version --</option>
            </select>
        </p>

        <p>
            <label for="sz_ip">SZ IP Address (ex. 192.168.0.100):</label>
            <input type="text" name="sz_ip" placeholder="SmartZone IP" value="<?php echo isset($_SESSION['sz_ip']) ? htmlspecialchars($_SESSION['sz_ip']) : '192.168.0.100'; ?>" required>
        </p>
        <p>
            <label for="sz_user">SZ User ID:</label>
            <input type="text" name="sz_user" value="<?php echo isset($_SESSION['sz_user']) ? htmlspecialchars($_SESSION['sz_user']) : ''; ?>" required>
            SZ Password:
            <input type="password" name="sz_pass" value="<?php echo isset($_SESSION['sz_pass']) ? htmlspecialchars($_SESSION['sz_pass']) : ''; ?>" required>
        </p>
        <button type="submit" name="step1_submit" <?php echo isset($_SESSION['csv_path']) ? '' : 'disabled'; ?>>Get Zone List</button>
        <?php if (!isset($_SESSION['csv_path'])) echo "<p style='color:red; margin-top: 10px;'>⚠️ Must upload CSV file first to activate.</p>"; ?>
    </form>
    
    
    <?php if (isset($_SESSION['zone_list']) && is_array($_SESSION['zone_list'])) { ?>
    <h3>Step 2: Select Zone and Verify Firmware Version</h3>
    <form method="POST">
        <p>
            Select Zone:
            <select name="zone_id" required>
                <option value="">-- Select a Zone --</option>
                <?php
                $selected_zone_id = isset($_SESSION['selected_zone_id']) ? $_SESSION['selected_zone_id'] : '';
                foreach ($_SESSION['zone_list'] as $zone) {
                    $selected = ($zone['id'] === $selected_zone_id) ? ' selected' : '';
                    echo "<option value=\"" . htmlspecialchars($zone['id']) . "\"" . $selected . ">" . htmlspecialchars($zone['name']) . "</option>";
                }
                ?>
            </select>
        </p>
        <button type="submit" name="step2_submit">Get Firmware List for Selected Zone</button>
    </form>
    <?php } ?>

    <?php if (isset($_SESSION['firmware_versions']) && is_array($_SESSION['firmware_versions'])) { ?>
    <h3>Step 3: Select Firmware Version and Execute Upgrade</h3>
    <p>
        Available Firmware Versions for Zone ID: <?php echo htmlspecialchars(isset($_SESSION['selected_zone_id']) ? $_SESSION['selected_zone_id'] : ''); ?>:
    </p>
    <div class="firmware-list-container">
        <form id="firmware_select_form" method="POST">
            <input type="hidden" name="firmware_version" id="firmware_version_input">
            <input type="hidden" name="step3_submit" value="1">
            <?php foreach ($_SESSION['firmware_versions'] as $fw_ver) { ?>
                <span class="firmware-version-item" data-version="<?php echo htmlspecialchars($fw_ver); ?>">
                    <?php echo htmlspecialchars($fw_ver); ?>
                </span>
            <?php } ?>
        </form>
        
        <p style="margin-top: 15px;">
            Selected Version: <strong id="selected_fw_display"><?php echo isset($_SESSION['selected_firmware_version']) ? htmlspecialchars($_SESSION['selected_firmware_version']) : 'None'; ?></strong>
            <button type="button" id="upgradeBtn" <?php echo isset($_SESSION['ready_to_run']) ? '' : 'disabled'; ?> style="margin-left: 20px;">
                Execute Upgrade and Batch AP Configuration Change
            </button>
        </p>
    </div>
    </p>
    <?php } ?>
    
    
    <h3>Upgrade Results</h3>
    <p>
        <a href="./szfwupdate.log" target="_blank" id="logDownload" style="display: none;">📄 Download Full Log</a>
        <a href="./szfw_result.csv" target="_blank" id="resultDownload" style="display: none; margin-left: 10px;">✅ Download Full Results</a>
    </p>
    <div id="output">
        Upgrade log will be displayed here.
    </div>
	<p>
	    <a href="./upgrade2sz.php?reset=1" class="menu-link">◀ Start New Script</a><br>
        <a href="../portal/index.php" class="menu-link">☎ HOME</a>
    </p>
    
    <script>
        // Convert PHP Controller-API Map to JavaScript Object
        const controllerApiMap = <?php echo json_encode($controller_api_map); ?>;
        const controllerSelect = document.getElementById('controller_ver_select');
        const apiSelect = document.getElementById('api_ver_select');
        const currentApiVer = "<?php echo isset($_SESSION['api_ver']) ? htmlspecialchars($_SESSION['api_ver']) : ''; ?>";

        function sortApiVersions(versions) {
            // Sort by descending order (e.g., v13_1 > v13_0 > v12_0)
            return versions.sort((a, b) => {
                const partsA = a.substring(1).split('_').map(Number);
                const partsB = b.substring(1).split('_').map(Number);
                if (partsA[0] !== partsB[0]) return partsB[0] - partsA[0]; // Major version (13 vs 12)
                return partsB[1] - partsA[1]; // Minor version (1 vs 0)
            });
        }

        function updateApiVersionSelect() {
            const selectedController = controllerSelect.value;
            
            // Initialize API selection list
            apiSelect.innerHTML = '<option value="">-- Select API Version --</option>';
            apiSelect.disabled = true;

            if (selectedController && controllerApiMap[selectedController]) {
                let versions = controllerApiMap[selectedController];
                
                // Sort versions (highest number first)
                versions = sortApiVersions(versions);

                versions.forEach((version, index) => {
                    const option = document.createElement('option');
                    option.value = version;
                    option.textContent = version;
                    
                    // 1. Automatically select if there is a previous session value
                    // 2. If there is no session value, select the highest version (first after sorting) as the default
                    if (version === currentApiVer || (!currentApiVer && index === 0)) {
                        option.selected = true;
                    }
                    apiSelect.appendChild(option);
                });
                apiSelect.disabled = false;
            }
        }

        // Populate initial API list upon page load (Maintain session and auto-select highest version)
        updateApiVersionSelect();

        // Update API list when controller selection changes
        controllerSelect.addEventListener('change', updateApiVersionSelect);

        // Process firmware version selection
        document.querySelectorAll('.firmware-version-item').forEach(item => {
            item.addEventListener('click', function() {
                const selectedVersion = this.getAttribute('data-version');
                document.getElementById('firmware_version_input').value = selectedVersion;
                document.getElementById('firmware_select_form').submit(); // Auto submit
            });
        });
        
        // Execute Upgrade (SSE)
        document.getElementById('upgradeBtn')?.addEventListener('click', function() {
            const outputDiv = document.getElementById('output');
            const logDownloadLink = document.getElementById('logDownload');
            const resultDownloadLink = document.getElementById('resultDownload');
            /*
            // Hide CSV preview area (Maintain existing logic of upgrade2sz.php)
            const csvPreviewArea = document.getElementById('csv_preview_area');
            if (csvPreviewArea) {
                csvPreviewArea.style.display = 'none';
            }
			*/
            outputDiv.innerHTML = 'Starting upgrade... waiting for log results...\n';
            this.disabled = true;

            logDownloadLink.style.display = 'none';
            resultDownloadLink.style.display = 'none';

            const eventSource = new EventSource('?run_upgrade=1');
            eventSource.onmessage = function(event) {
                outputDiv.innerHTML += event.data + '\n';
                outputDiv.scrollTop = outputDiv.scrollHeight;
                if (event.data.includes('Log file')) {
                    logDownloadLink.style.display = 'inline';
                }
                if (event.data.includes('Result file')) {
                    resultDownloadLink.style.display = 'inline';
                }
            };

            eventSource.onerror = function() {
                outputDiv.innerHTML += 'Upgrade process complete\n';
                eventSource.close();
                document.getElementById('upgradeBtn').disabled = false;
            };
        });
    </script>
</div>

</body>
</html>