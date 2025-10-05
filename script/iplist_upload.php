<?php
session_start();

// Reset session if requested
if (isset($_GET['reset'])) {
    unset($_SESSION['csv_path']);
    unset($_SESSION['csv_table']);
    $csv_upload_message = "🔄 Previous upload content has been reset. Please upload a new CSV file.";
}

$upload_dir = 'uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle CSV file upload
if (isset($_POST['upload_csv']) && isset($_FILES['csv_file'])) {
    $csv_file = $_FILES['csv_file'];
    if ($csv_file['error'] == UPLOAD_ERR_OK) {
        $csv_path = $upload_dir . basename($csv_file['name']);
        if (move_uploaded_file($csv_file['tmp_name'], $csv_path)) {
            $_SESSION['csv_path'] = $csv_path;
            $csv_upload_message = "✅ File upload successful: " . htmlspecialchars($csv_file['name']);

            // Process CSV for display
			$raw_lines = file($csv_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $csv_data = array_map('str_getcsv', array_filter($raw_lines, function($line) {
                $trimmed_line = trim($line);
                // Exclude empty lines or lines with only commas (e.g., ,,,,,,)
                return $trimmed_line !== '' && !preg_match('/^,+$/', $trimmed_line);
            }));
            if ($csv_data && count($csv_data) > 0) {
                $csv_table = "<div class='note'>";
                //$csv_table .= "<div class='note-title'>Uploaded CSV Content (First 10 Rows Displayed)</div>";
				$csv_table .= "<div class='note-title'>Uploaded CSV Content</div>";
                $csv_table .= "<table class='styled-table'>";
                $csv_table .= "<thead><tr>";
				// display a Header
                foreach ($csv_data[0] as $header) {
                    $csv_table .= "<th>" . htmlspecialchars($header) . "</th>";
                }
                $csv_table .= "</tr></thead><tbody>";
				// display a Data
                //$max_rows = min(count($csv_data), 11);
				$max_rows = count($csv_data);
                for ($i = 1; $i < $max_rows; $i++) {
                    $csv_table .= "<tr>";
                    foreach ($csv_data[$i] as $cell) {
                        $csv_table .= "<td>" . htmlspecialchars($cell) . "</td>";
                    }
                    $csv_table .= "</tr>";
                }
                $csv_table .= "</tbody></table></div>";
                $_SESSION['csv_table'] = $csv_table;
            } else {
                $csv_upload_message = "CSV file uploaded successfully, but error reading or parsing file content";
                unset($_SESSION['csv_path']);
            }
        } else {
            $csv_upload_message = "CSV file upload failed: move_uploaded_file error";
        }
    } else {
        $csv_upload_message = "CSV file upload error: " . $csv_file['error'];
    }
    if (isset($_SESSION['firmware_path'])) {
        unset($_SESSION['firmware_path']);
    }
}

// Handle script execution (Server-Sent Events)
if (isset($_GET['run_script'])) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    ini_set('output_buffering', '0');
    ini_set('zlib.output_compression', 'Off');
    ini_set('max_execution_time', 0);
    while (@ob_get_level() > 0) @ob_end_clean();

    $csv_path = isset($_SESSION['csv_path']) ? $_SESSION['csv_path'] : '';
    $operation = $_GET['run_script'];
    $script = './script.sh';
    $log_file = "./script_${operation}.log";
    $result_file = "./script_${operation}_result.csv";

    if (file_exists($csv_path)) {
        $command = "$script " . escapeshellarg($operation) . " " . escapeshellarg($csv_path) . " 2>&1";
        echo "data: Command executed: $command\n\n";
        @flush();

        @file_put_contents($log_file, "Command executed: $command\n");
        if (file_exists($result_file)) {
            @unlink($result_file);
        }

        $handle = @popen($command, 'r');
        if ($handle) {
            $start_time = time();
            $max_duration = 600; // 10 minutes
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
                    $timeout_msg = "Process terminated due to exceeding maximum execution time (10 minutes)\n";
                    echo "data: $timeout_msg\n\n";
                    @file_put_contents($log_file, $timeout_msg, FILE_APPEND);
                    @flush();
                    break;
                }
                @usleep(100000);
            }
            @pclose($handle);
            echo "data: Script execution completed\n\n";
            echo "data: Log file: $log_file\n\n";
            echo "data: Result file: $result_file\n\n";
            @flush();
            @chmod($log_file, 0666);
            @chmod($result_file, 0666);
        } else {
            $error_msg = "Process execution failed\n";
            echo "data: $error_msg\n\n";
            @file_put_contents($log_file, $error_msg, FILE_APPEND);
            @flush();
        }
    } else {
        $error_msg = "Error: AP IP list file (CSV) not found. Please upload a file first.\n";
        echo "data: $error_msg\n\n";
        @flush();
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AP IP Batch Change</title>
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
			font-size: 12px;
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
        .download-links {
            margin-top: 10px;
        }
        .download-links a {
            margin-right: 15px;
        }
    </style>
</head>
<body>

<div class="container">
	<a href="../portal/index.php" class="menu-link">☎ HOME</a>
    <h1>AP IP Batch Change Script Execution</h1>
        <a href="./iplist_upload.php?reset=1" class="menu-link">◀ Reset Script</a><br>
        <a href='./example/sample.csv' class="menu-link" download>📁 Download Sample CSV File</a>

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
    <?php else: 
        $sample_csv_data = array(
            array("current_ip", "user", "pass", "new_IP", "subnet", "g/w", "sz", "hostname"),
            array("10.10.10.100", "super", "supe12345", "10.10.10.200", "255.255.255.0", "10.10.10.1", "100.100.100.100", "ap1"),
            array("10.10.20.100", "super", "sp-admin", "10.10.20.234", "255.255.255.0", "10.10.20.254", "10.10.10.10", "ap2"),
            array("10.10.20.200", "super", "abcdef", "10.10.20.235", "255.255.255.0", "10.10.20.254", "10.10.10.10", "ap3")
        );
        $sample_table = "<div class='note'>";
        $sample_table .= "<div class='note-title'>※ The following shows an example of the sample CSV content. Download the sample CSV above, edit it, and upload it.</div>";
        $sample_table .= "<table class='styled-table'>";
        $sample_table .= "<thead><tr>";
        foreach ($sample_csv_data[0] as $header) {
            $sample_table .= "<th>" . htmlspecialchars($header) . "</th>";
        }
        $sample_table .= "</tr></thead><tbody>";
        for ($i = 1; $i < count($sample_csv_data); $i++) {
            $sample_table .= "<tr>";
            foreach ($sample_csv_data[$i] as $cell) {
                $sample_table .= "<td>" . htmlspecialchars($cell) . "</td>";
            }
            $sample_table .= "</tr>";
        }
        $sample_table .= "</tbody></table></div>";
        echo $sample_table;
    ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['csv_path'])): ?>
        <h3>Script Execution</h3>
        <?php
        $log_file = './script_${operation}.log';
        $result_file = './script_${operation}_result.csv';
        ?>
		<button class="script-btn" data-operation="changeip">Run changeip</button> → Change current_ip to new_IP, subnet, g/w, no AP reboot<p>
        <button class="script-btn" data-operation="connect_sz">Run connect_sz</button> → Set SZ IP, no reboot<p>
		<button class="script-btn" data-operation="devicename">Run devicename</button> → Set AP hostname, no AP reboot<p>
        <button class="script-btn" data-operation="reboot">Run reboot</button> → Reboot AP<p>
        <button class="script-btn" data-operation="factory_reset">Run factory_reset</button> → Factory reset and reboot AP<p>
        <button class="script-btn" data-operation="sz_devicename_changeip">Run sz_devicename_changeip</button> → Set SZ IP, hostname, and IP simultaneously, no AP reboot<p>
        <div id="output"></div>
        
        <div class="download-links">
            <a href="#" download id="logDownload" style="display:none;">📄 Download Log</a><br>
            <a href="#" download id="resultDownload" style="display:none;">📄 Download Result</a>
        </div>
    <?php endif; ?>

    <div class="note">
        <p><span class="note-text">※ Clicking the scripts above will connect to the AP listed in the 'current_ip' column of the CSV file and execute the corresponding script.</span></p>
        <p><span class="note-text">※ When connecting to the AP, the values in the 'user' and 'pass' columns of the CSV are used. If the connection fails, the passwords 'sp-admin' and 'ruckus12#$' are automatically tried in that order.</span></p>
    </div>

    <p>
        <a href="./iplist_upload.php?reset=1" class="menu-link">◀ Reset Script</a><p>
        <a href="../portal/index.php" class="menu-link">☎ HOME</a>
    </p>

    <script>
        document.querySelectorAll('.script-btn').forEach(button => {
            button.addEventListener('click', function() {
                const operation = this.getAttribute('data-operation');
                const outputDiv = document.getElementById('output');
                const logDownloadLink = document.getElementById('logDownload');
                const resultDownloadLink = document.getElementById('resultDownload');
                
                logDownloadLink.href = `./script_${operation}.log`;
                resultDownloadLink.href = `./script_${operation}_result.csv`;
                
                outputDiv.innerHTML = `${operation} script started...waiting for log results...\n`;
                document.querySelectorAll('.script-btn').forEach(btn => btn.disabled = true);

                const eventSource = new EventSource(`?run_script=${operation}`);
                
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
                    outputDiv.innerHTML += 'Script process completed\n';
                    eventSource.close();
                    document.querySelectorAll('.script-btn').forEach(btn => btn.disabled = false);
                    logDownloadLink.style.display = 'inline';
                    resultDownloadLink.style.display = 'inline';
                };
            });
        });
    </script>
</div>

</body>
</html>


