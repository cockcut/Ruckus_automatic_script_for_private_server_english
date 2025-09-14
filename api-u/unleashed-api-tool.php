<?php
// PHP 5.4 compatible
date_default_timezone_set('Asia/Seoul'); // Prevent timezone errors

// Function to display input form
function show_form($error = '') {
?>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ruckus Unleashed API Tool</title>
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
        h1, h2, h3 { color: #1a1a1a; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 0; }
        form { background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        label { font-weight: bold; display: block; margin-bottom: 5px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 8px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        input[type="submit"] { background-color: #007BFF; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        input[type="submit"]:hover { background-color: #0056b3; }
        .error { color: #d9534f; font-weight: bold; margin-bottom: 15px; }
        .download-link { background-color: #007BFF; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 14px; margin-left: 10px; }
        .download-link:hover { background-color: #0056b3; }
        .scrollable-table-container { overflow-x: auto; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; white-space: nowrap; }
        td { white-space: nowrap; }
    </style>
</head>
<body>
<div class="container">
    <a href="../portal/index.php" class="menu-link">☎ HOME</a>
    <h1>Ruckus Unleashed API Tool</h1>
    <p>Enter the information below to query the AP and WLAN information of the Unleashed controller.</p>
    <?php if ($error): ?>
        <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
        <label for="uip">Unleashed Master or Management-interface IP:</label>
        <input name="uip" id="uip" type="text" value="<?php echo isset($_POST['uip']) ? htmlspecialchars($_POST['uip'], ENT_QUOTES, 'UTF-8') : ''; ?>" required />
        <label for="username">Username:</label>
        <input name="username" id="username" type="text" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8') : ''; ?>" required />
        <label for="password">Password:</label>
        <input name="password" id="password" type="password" required />
        <input type="submit" name="submit" value="Execute API and View Results">
    </form>
    <a href="../portal/index.php" class="menu-link">☎ HOME</a>
</div>
</body>
</html>
<?php
}

// Function to display results
function show_results($uip, $username, $password) {
    // Sanitize inputs
    $uip = filter_var($uip, FILTER_SANITIZE_STRING);
    $username = filter_var($username, FILTER_SANITIZE_STRING);
    $password = filter_var($password, FILTER_SANITIZE_STRING);

    // Execute the shell script and capture stderr
    $script_path = './script_ap-wlan-stats.sh';
    $command = escapeshellcmd($script_path) . ' ' . escapeshellarg($uip) . ' ' . escapeshellarg($username) . ' ' . escapeshellarg($password) . ' 2>&1';
    $output = shell_exec($command);

    // File paths
    $ap_result_file = "./$uip/ap-result.xml";
    $ap_list_file = "./$uip/1.ap.list";
    $wlan_list_file = "./$uip/2.wlan.list";
    $ap_csv_file = "./$uip/download/$uip.ap.csv";
    $wlan_csv_file = "./$uip/download/$uip.wlan.csv";

    // Initialize arrays for data
    $ap_data = array();
    $wlan_data = array();
    $error = '';

    // Check ap-result.xml for authentication failure
    if (file_exists($ap_result_file)) {
        $ap_result_content = file_get_contents($ap_result_file);
        if (strpos($ap_result_content, 'The document has moved') !== false) {
            $error = "Invalid ID or password. API call failed. Please re-enter.\n";
        }
    } else {
        $error .= "AP result file ($ap_result_file) was not created.\n";
    }

    // Read AP list
    if (!$error && file_exists($ap_list_file)) {
        $ap_lines = file($ap_list_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (count($ap_lines) > 2) {
            $ap_data = array_slice($ap_lines, 2); // Skip title and separator
            if (count($ap_data) == 0 || trim($ap_data[0]) == '') {
                $error .= "AP data is empty. Please check the ID or password.\n";
            }
        } else {
            $error .= "AP list file ($ap_list_file) is empty or contains only the header.\n";
        }
    } elseif (!$error) {
        $error .= "AP list file ($ap_list_file) was not created.\n";
    }

    // Read WLAN list
    if (!$error && file_exists($wlan_list_file)) {
        $wlan_lines = file($wlan_list_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (count($wlan_lines) > 2) {
            $wlan_data = array_slice($wlan_lines, 2); // Skip title and separator
            if (count($wlan_data) == 0 || trim($wlan_data[0]) == '') {
                $error .= "WLAN data is empty. Please check the ID or password.\n";
            }
        } else {
            $error .= "WLAN list file ($wlan_list_file) is empty or contains only the header.\n";
        }
    } elseif (!$error) {
        $error .= "WLAN list file ($wlan_list_file) was not created.\n";
    }

?>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ruckus Unleashed API Tool - Results</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; background-color: #f4f4f4; color: #333; }
        .container { max-width: 1200px; margin: auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
        h1, h2, h3 { color: #1a1a1a; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 0; }
        .error { color: #d9534f; font-weight: bold; margin-bottom: 15px; }
        .download-link { background-color: #007BFF; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 14px; margin-left: 10px; }
        .download-link:hover { background-color: #0056b3; }
        .scrollable-table-container { overflow-x: auto; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; white-space: nowrap; }
        td { white-space: nowrap; }
    </style>
</head>
<body>
<div class="container">
	<p><a href="../portal/index.php">☎ HOME</a>
	<p><a href="./unleashed-api-tool.php">◀ New API Entry</a></p>
    <h1>Ruckus Unleashed API Tool - Results</h1>
    <h2>1. Controller Information</h2>
    <table>
        <tr><th>Item</th><th>Value</th></tr>
        <tr><td>IP</td><td><?php echo htmlspecialchars($uip, ENT_QUOTES, 'UTF-8'); ?></td></tr>
        <tr><td>Username</td><td><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></td></tr>
    </table>
    <p>
    <?php if ($error): ?>
        <p class="error"><?php echo nl2br(htmlspecialchars($error, ENT_QUOTES, 'UTF-8')); ?></p>
    <?php else: ?>
        <h2>2. AP Information List
            <?php if (file_exists($ap_csv_file)): ?>
                <a href="./<?php echo htmlspecialchars($uip, ENT_QUOTES, 'UTF-8'); ?>/download/<?php echo htmlspecialchars($uip, ENT_QUOTES, 'UTF-8'); ?>.ap.csv" class="download-link">Download AP List</a>
            <?php else: ?>
                <span class="error">AP download file was not created.</span>
            <?php endif; ?>
        </h2>
        <div class="scrollable-table-container">
            <table>
                <tr>
                    <th>mac</th><th>ap-name</th><th>model</th><th>ip</th><th>netmask</th><th>gateway</th><th>serial-number</th><th>firmware-version</th><th>num-sta</th><th>eth0</th><th>eth1</th><th>2G_ch</th><th>5G_ch</th><th>6G_ch</th>
                </tr>
                <?php
                foreach ($ap_data as $line) {
                    $fields = explode('|', trim($line));
                    if (count($fields) >= 14) {
                        echo '<tr>';
                        foreach ($fields as $field) {
                            echo '<td>' . htmlspecialchars(trim($field), ENT_QUOTES, 'UTF-8') . '</td>';
                        }
                        echo '</tr>';
                    }
                }
                ?>
            </table>
        </div>
        <p>
        <h2>3. WLAN Information List
            <?php if (file_exists($wlan_csv_file)): ?>
                <a href="./<?php echo htmlspecialchars($uip, ENT_QUOTES, 'UTF-8'); ?>/download/<?php echo htmlspecialchars($uip, ENT_QUOTES, 'UTF-8'); ?>.wlan.csv" class="download-link">Download WLAN, BSSID List</a>
            <?php else: ?>
                <span class="error">WLAN download file was not created.</span>
            <?php endif; ?>
        </h2>
        <div class="scrollable-table-container">
            <table>
                <tr>
                    <th>BSSID</th><th>SSID</th><th>Radio</th><th>AP_mac</th><th>802.11</th>
                </tr>
                <?php
                foreach ($wlan_data as $line) {
                    $fields = explode('|', trim($line));
                    if (count($fields) >= 5) {
                        echo '<tr>';
                        foreach ($fields as $field) {
                            echo '<td>' . htmlspecialchars(trim($field), ENT_QUOTES, 'UTF-8') . '</td>';
                        }
                        echo '</tr>';
                    }
                }
                ?>
            </table>
        </div>
    <?php endif; ?>
    <p><a href="./unleashed-api-tool.php">◀ New API Entry</a></p>
    <p><a href="../portal/index.php">☎ HOME</a></p>
</div>
</body>
</html>
<?php
}

// Main logic
if (isset($_POST['uip']) && isset($_POST['username']) && isset($_POST['password']) && isset($_POST['submit'])) {
    $uip = trim($_POST['uip']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if (empty($uip) || empty($username) || empty($password)) {
        show_form('All fields must be filled out.');
    } else {
        show_results($uip, $username, $password);
    }
} else {
    show_form();
}
?>