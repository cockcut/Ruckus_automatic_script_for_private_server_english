<?php
session_start();

$upload_dir = 'uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if (isset($_POST['upload_csv']) && isset($_FILES['csv_file'])) {
    $csv_file = $_FILES['csv_file'];
    if ($csv_file['error'] == UPLOAD_ERR_OK) {
        $csv_path = $upload_dir . basename($csv_file['name']);
        if (move_uploaded_file($csv_file['tmp_name'], $csv_path)) {
            $_SESSION['csv_path'] = $csv_path;
            $csv_upload_message = "✅ File upload successful: " . htmlspecialchars($csv_file['name']);

            $csv_data = array_map('str_getcsv', file($csv_path));
            $csv_table = "<div class='note'>";
            $csv_table .= "<div class='note-title'>Uploaded CSV Content</div>";
            $csv_table .= "<table class='styled-table'>";
            $csv_table .= "<thead><tr>";
            foreach ($csv_data[0] as $header) {
                $csv_table .= "<th>" . htmlspecialchars($header) . "</th>";
            }
            $csv_table .= "</tr></thead><tbody>";
            for ($i = 1; $i < count($csv_data); $i++) {
                $csv_table .= "<tr>";
                foreach ($csv_data[$i] as $cell) {
                    $csv_table .= "<td>" . htmlspecialchars($cell) . "</td>";
                }
                $csv_table .= "</tr>";
            }
            $csv_table .= "</tbody></table></div>";

            $_SESSION['csv_table'] = $csv_table;
        } else {
            $csv_upload_message = "CSV file upload failed";
        }
    } else {
        $csv_upload_message = "CSV file upload error: " . $csv_file['error'];
    }
}

if (isset($_POST['upload_firmware']) && isset($_FILES['firmware_file'])) {
    $firmware_file = $_FILES['firmware_file'];
    if ($firmware_file['error'] == UPLOAD_ERR_OK) {
        $firmware_path = $upload_dir . basename($firmware_file['name']);
        if (move_uploaded_file($firmware_file['tmp_name'], $firmware_path)) {
            $_SESSION['firmware_path'] = $firmware_path;
            $firmware_upload_message = "Firmware file upload successful: " . htmlspecialchars($firmware_file['name']);
        } else {
            $firmware_upload_message = "Firmware file upload failed";
        }
    } else {
        $firmware_upload_message = "Firmware file upload error: " . $firmware_file['error'];
    }
}

// If a script execution request comes in, save the log to a file and output the entire thing to the web.
if (isset($_GET['run_upgrade'])) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    ini_set('output_buffering', '0');
    ini_set('zlib.output_compression', 'Off');
    ini_set('max_execution_time', 0);
    while (ob_get_level() > 0) ob_end_clean();

    $csv_path = $_SESSION['csv_path'] ?? '';
    $firmware_path = $_SESSION['firmware_path'] ?? '';
    $user_password = isset($_SESSION['upgrade_password']) ? escapeshellarg($_SESSION['upgrade_password']) : '';
    $log_file = './upgrade.log';

    echo "data: Upgrade starting...\n\n";
    ob_flush(); flush();

    if (file_exists($csv_path) && file_exists($firmware_path)) {
        echo "data: Running upgrade script...\n\n";
        ob_flush(); flush();
        
        // Ensure the path and arguments are properly escaped and safe.
        $firmware_file_name = basename($firmware_path);
        $command = "expect -f ./upgrade.sh " . escapeshellarg($csv_path) . " " . escapeshellarg($firmware_file_name) . " " . $user_password . " > " . escapeshellarg($log_file) . " 2>&1 &";
        
        pclose(popen($command, 'r'));

        echo "data: Upgrade complete\n\n";
        echo "data: Log file: " . htmlspecialchars($log_file) . "\n\n";
        echo "data: Upgrade process complete.\n\n";
    } else {
        echo "data: CSV or firmware file is missing. Please upload both files.\n\n";
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AP Firmware Upgrade</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; background-color: #f4f4f4; color: #333; }
        .container { max-width: 1200px; margin: auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
        .form-section { margin-bottom: 20px; }
        .form-section h3 { border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        .form-section input[type="file"] { margin-right: 10px; }
        .form-section button { padding: 8px 15px; background-color: #007BFF; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .form-section button:hover { background-color: #0056b3; }
        .note { background-color: #f9f9f9; border-left: 4px solid #007BFF; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .note-title { font-weight: bold; margin-bottom: 10px; }
        .styled-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 0.9em; min-width: 400px; border-radius: 5px 5px 0 0; overflow: hidden; box-shadow: 0 0 20px rgba(0, 0, 0, 0.15); }
        .styled-table thead tr { background-color: #007BFF; color: #ffffff; text-align: left; }
        .styled-table th, .styled-table td { padding: 12px 15px; border: 1px solid #ddd; }
        .styled-table tbody tr { border-bottom: 1px solid #dddddd; }
        .styled-table tbody tr:nth-of-type(even) { background-color: #f3f3f3; }
        .styled-table tbody tr:last-of-type { border-bottom: 2px solid #009879; }
        .output-section { margin-top: 30px; }
        .output-section pre { background-color: #2b2b2b; color: #f8f8f2; padding: 15px; border-radius: 8px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; }
        .result-section { border: 1px solid #ddd; padding: 15px; border-radius: 8px; margin-top: 20px; }
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
    </style>
</head>
<body>

<div class="container">
    <a href="../portal/index.php" class="menu-link">☎ HOME</a>
    <a href="./index.html" class="menu-link">Previous Screen</a>
    <h1>AP Firmware Upgrade</h1>
    
    <div class="form-section">
        <h3>1. Upload CSV file</h3>
        <form action="" method="post" enctype="multipart/form-data">
            <input type="file" name="csv_file" required>
            <button type="submit" name="upload_csv">Upload CSV file</button>
        </form>
        <?php if (isset($csv_upload_message)) echo "<p>" . htmlspecialchars($csv_upload_message) . "</p>"; ?>
    </div>

    <div class="form-section">
        <h3>2. Upload Firmware file</h3>
        <form action="" method="post" enctype="multipart/form-data">
            <input type="file" name="firmware_file" required>
            <button type="submit" name="upload_firmware">Upload Firmware file</button>
        </form>
        <?php if (isset($firmware_upload_message)) echo "<p>" . htmlspecialchars($firmware_upload_message) . "</p>"; ?>
    </div>

    <div class="form-section">
        <h3>3. Start AP Firmware Upgrade</h3>
        <button id="upgradeBtn" class="script-btn">Start AP Firmware Upgrade</button>
        <a id="logDownload" class="menu-link" style="display:none;" download="upgrade.log">Download Log</a>
    </div>

    <?php
    if (isset($_SESSION['csv_table'])) {
        echo $_SESSION['csv_table'];
    }
    if (isset($_SESSION['firmware_path'])) {
        echo "<div class='note'>";
        echo "<div class='note-title'>Uploaded Firmware Content</div>";
        echo "<div>" . htmlspecialchars(basename($_SESSION['firmware_path'])) . "</div>";
        echo "</div>";
    }
    ?>

    <div class="output-section">
        <h3>Log</h3>
        <pre id="output"></pre>
    </div>

    <div class="note">
        <p><span class="note-text">※ After uploading the CSV and firmware files, it will automatically try the passwords in this order: the entered password, sp-admin, and ruckus12#$.</span></p>
        <p><span class="note-text">※ If the firmware upgrade is successful, the AP will be reset and rebooted.</span></p>
    </div>

    <p>
        <a href="./upgrade.php" class="menu-link">◀ New Script</a><p>
        <a href="../portal/index.php" class="menu-link">☎ HOME</a>
    </p>

    <script>
        document.getElementById('upgradeBtn')?.addEventListener('click', function() {
            const outputDiv = document.getElementById('output');
            const logDownloadLink = document.getElementById('logDownload');
            outputDiv.innerHTML = 'Upgrade starting...\n';
            this.disabled = true;

            const eventSource = new EventSource('?run_upgrade=1');
            eventSource.onmessage = function(event) {
                outputDiv.innerHTML += event.data + '\n';
                outputDiv.scrollTop = outputDiv.scrollHeight;
                if (event.data.includes('Log file')) {
                    logDownloadLink.style.display = 'inline';
                }
            };

            eventSource.onerror = function() {
                outputDiv.innerHTML += 'An error occurred during the upgrade process. Please refresh manually.\n';
                document.getElementById('upgradeBtn').disabled = false;
            };

            eventSource.onclose = function() {
                outputDiv.innerHTML += 'Upgrade process complete.\n';
                document.getElementById('upgradeBtn').disabled = false;
            };
        });
    </script>
</div>
</body>
</html>