<?php
session_start();

$upload_dir = 'Uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if (isset($_POST['upload_csv']) && isset($_FILES['csv_file'])) {
    // Handle CSV file upload
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
    // Handle firmware file upload
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

    if (file_exists($csv_path) && file_exists($firmware_path)) {
        $firmware_filename = basename($firmware_path);
        $is_unleashed = (strpos($firmware_filename, '200') !== false);
        $script = $is_unleashed ? './ufwupdate.sh' : './sfwupdate.sh';
        $log_file = $is_unleashed ? './ufwupdate.log' : './sfwupdate.log';
        $result_file = $is_unleashed ? './ufw_result.csv' : './sfw_result.csv'; // Define result file name

        // Delete previous execution result and log files for new execution
        if (file_exists($log_file)) unlink($log_file);
        if (file_exists($result_file)) unlink($result_file);

        // Include result file path in execution command (third argument)
        $command = "$script " . escapeshellarg($csv_path) . " " . escapeshellarg($firmware_path) . " " . escapeshellarg($result_file) . " 2>&1";

        echo "data: Command executed: $command\n\n";
        flush();

        file_put_contents($log_file, "Command executed: $command\n");

        $handle = popen($command, 'r');
        if ($handle) {
            $start_time = time();
            $max_duration = 600;

            while (!feof($handle)) {
                $line = fgets($handle);
                if ($line !== false) {
                    $encoded_line = htmlspecialchars($line);
                    echo "data: $encoded_line\n\n";
                    file_put_contents($log_file, $line, FILE_APPEND);
                    flush();
                }

                if ((time() - $start_time) > $max_duration) {
                    pclose($handle);
                    $timeout_msg = "Process terminated due to exceeding maximum execution time (10 minutes)\n";
                    echo "data: $timeout_msg\n\n";
                    file_put_contents($log_file, $timeout_msg, FILE_APPEND);
                    flush();
                    break;
                }

                usleep(100000);
            }

            pclose($handle);
            
            // Set permissions for result file and output information
            if (file_exists($result_file)) {
                chmod($result_file, 0666);
                echo "data: Result file: $result_file\n\n";
            }
            
            echo "data: Script execution completed\n\n";
            echo "data: Log file: $log_file\n\n";
            flush();
            chmod($log_file, 0666);
        } else {
            $error_msg = "Process execution failed\n";
            echo "data: $error_msg\n\n";
            file_put_contents($log_file, $error_msg, FILE_APPEND);
            flush();
        }
    } else {
        $error_msg = "Error: CSV or firmware file not found\n";
        echo "data: $error_msg\n\n";
        flush();
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AP Firmware Upgrade</title>
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
    </style>
</head>
<body>

<div class="container">
    <h1>AP Firmware Upgrade</h1>
	<center>★ Downloads use HTTP port 8080 on the PC. Please allow TCP 8080 on the PC. ★</center>
    <p>
        <a href="../portal/index.php" class="menu-link">☎ HOME</a><p>
        <a href="./upgrade.php" class="menu-link">◀ Reset Script</a><p>
        <a href='./example/fw_sample.csv' class="menu-link" download>📁 Download Sample CSV File</a>
    </p>

    <form method="post" enctype="multipart/form-data">
        <h3>Upload AP IP List File (CSV)</h3>
        <input type="file" name="csv_file" accept=".csv" required>
        <input type="submit" name="upload_csv" value="Upload AP List">
    </form>

    <?php if (isset($csv_upload_message)): ?>
        <p class="message"><?php echo $csv_upload_message; ?></p>
    <?php endif; ?>

    <?php if (isset($_SESSION['csv_table'])): ?>
        <?php echo $_SESSION['csv_table']; ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['csv_path'])): ?>
        <form method="post" enctype="multipart/form-data">
            <h3>Upload Firmware File</h3>
            <input type="file" name="firmware_file" required>
            <input type="submit" name="upload_firmware" value="Upload Firmware File">
        </form>
    <?php endif; ?>
	<p>
    <?php if (isset($firmware_upload_message)): ?>
        <p class="message"><?php echo $firmware_upload_message; ?></p>
    <?php endif; ?>

    <?php if (isset($_SESSION['firmware_path'])): ?>
        <?php
        $firmware_filename = basename($_SESSION['firmware_path']);
        $button_text = (strpos($firmware_filename, '200') !== false) ? "Unleashed Upgrade" : "Standalone Upgrade";
        $log_file = (strpos($firmware_filename, '200') !== false) ? './ufwupdate.log' : './sfwupdate.log';
        $result_file = (strpos($firmware_filename, '200') !== false) ? './ufw_result.csv' : './sfw_result.csv'; // Define result file name
        ?>
        <button id="upgradeBtn"><?php echo $button_text; ?></button>
        <div id="output"></div>
        <p>
            <a href="<?php echo $log_file; ?>" download id="logDownload" style="display:none;">📄 Download Full Log</a><br>
            <a href="<?php echo $result_file; ?>" download id="resultDownload" style="display:none; margin-left: 10px;">✅ Download Full Result</a>
        </p>
    <?php endif; ?>

    <div class="note">
        <p><span class="note-text">※ After uploading the CSV and firmware files, the system will attempt authentication using the provided password, followed by 'sp-admin', and then 'ruckus12#$'. </span></p>
        <p><span class="note-text">※ Upon successful firmware upgrade, the AP will be reset and rebooted.</span></p>
    </div>

    <p>
        <a href="./upgrade.php" class="menu-link">◀ Reset Script</a><p>
        <a href="../portal/index.php" class="menu-link">☎ HOME</a>
    </p>

    <script>
        document.getElementById('upgradeBtn')?.addEventListener('click', function() {
            const outputDiv = document.getElementById('output');
            const logDownloadLink = document.getElementById('logDownload');
            const resultDownloadLink = document.getElementById('resultDownload'); // New link variable
            outputDiv.innerHTML = 'Upgrade started...waiting for log results...\n';
            this.disabled = true;

            // Hide existing links during execution
            logDownloadLink.style.display = 'none';
            resultDownloadLink.style.display = 'none';

            const eventSource = new EventSource('?run_upgrade=1');
            eventSource.onmessage = function(event) {
                outputDiv.innerHTML += event.data + '\n';
                outputDiv.scrollTop = outputDiv.scrollHeight;
                if (event.data.includes('Log file')) {
                    logDownloadLink.style.display = 'inline';
                }
                // Show download link when result file message is detected
                if (event.data.includes('Result file')) { 
                    resultDownloadLink.style.display = 'inline';
                }
            };

            eventSource.onerror = function() {
                outputDiv.innerHTML += 'Upgrade process completed\n';
                eventSource.close();
                document.getElementById('upgradeBtn').disabled = false;
                logDownloadLink.style.display = 'inline';
                resultDownloadLink.style.display = 'inline'; // Show download link even on error termination
            };
        });
    </script>
</div>

</body>
</html>
