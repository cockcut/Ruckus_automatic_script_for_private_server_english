<?php
session_start();

// When a script execution request is received, only the log is saved to a file and the entire output is displayed on the web
if (isset($_GET['run_script']) && isset($_GET['script'])) {
    ini_set('max_execution_time', 0);
    
    $csv_path = $_SESSION['csv_path'] ?? '';
    $script_name = $_GET['script'];

    if (file_exists($csv_path)) {
        switch ($script_name) {
            case 'changeip': $script = './changeip.sh'; $log_file = './changeip.log'; $result_file = './changeip_result.csv'; break;
            case 'devicename': $script = './devicename.sh'; $log_file = './devicename.log'; $result_file = './devicename_result.csv'; break;
            case 'connect_sz': $script = './connect_sz.sh'; $log_file = './connect_sz.log'; $result_file = './connect_sz_result.csv'; break;
            case 'sz_devicename_changeip': $script = './sz_devicename_changeip.sh'; $log_file = './sz_devicename_changeip.log'; $result_file = './sz_devicename_changeip_result.csv'; break;
            case 'factory_reset': $script = './factory_reset.sh'; $log_file = './factory_reset.log'; $result_file = './factory_reset_result.csv'; break;
            case 'reboot': $script = './reboot.sh'; $log_file = './reboot.log'; $result_file = './reboot.csv'; break;
            default: echo "Error: Invalid script"; exit;
        }

        $command = "$script " . escapeshellarg($csv_path) . " 2>&1";
        $initial_output = "Execution command: $command\n\n";

        // Initialize existing log file
        file_put_contents($log_file, $initial_output);

        // Read output in real-time using popen and save it to a file
        $handle = popen($command, 'r');
        if ($handle) {
            while (!feof($handle)) {
                $line = fgets($handle);
                if ($line !== false) {
                    file_put_contents($log_file, $line, FILE_APPEND);
                }
            }
            pclose($handle);

            // Add final completion message to the log file
            $completion_message = "\n\nScript execution finished\n";
            $completion_message .= "Log file: $log_file\n";
            $completion_message .= "Result file: $result_file\n";
            file_put_contents($log_file, $completion_message, FILE_APPEND);
            
            chmod($log_file, 0644);
            chmod($result_file, 0644);

            // After all tasks are finished, read the entire log file and display it
            echo file_get_contents($log_file);
            echo "---CSV_TABLE_START---"; // CSV table output separator

            // Display the result CSV file as a table only when running the changeip.sh script
            if (file_exists($result_file) && $script_name == 'changeip') {
                $result_csv_data = array_map('str_getcsv', file($result_file));
				echo "<h3>Result CSV Content</h3>";	   												//When the file name is hidden
				//echo "<h3>Result CSV Content: " . htmlspecialchars($result_file) . "</h3>";	   //When the file name is visible
				echo "<div id='csv-table-container'>";  // Make a horizontal scroll for the CSV result table
                echo "<table class='styled-table'>";
                echo "<thead><tr>";
                foreach ($result_csv_data[0] as $header) {
                    echo "<th>" . htmlspecialchars($header) . "</th>";
                }
                echo "</tr></thead><tbody>";
                for ($i = 1; $i < count($result_csv_data); $i++) {
                    echo "<tr>";
                    foreach ($result_csv_data[$i] as $cell) {
                        echo "<td>" . htmlspecialchars($cell) . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</tbody></table>";
				echo "</div>"; // Close container div
            }
        } else {
            $error_msg = "Process execution failed\n";
            file_put_contents($log_file, $error_msg, FILE_APPEND);
            echo file_get_contents($log_file);
        }
    } else {
        echo "Error: CSV file not found";
    }
    exit; // Terminate immediately after script execution
}

// File upload processing
$upload_dir = 'uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $target_file = $upload_dir . basename($file['name']);

    if ($file['error'] !== 0) {
        echo "<p class='error-message'>File upload failed! Error code: " . $file['error'] . "</p>";
        exit;
    }

    if (file_exists($target_file)) {
        unlink($target_file);
        sleep(1);
    }

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        chmod($target_file, 0644);
        $_SESSION['csv_path'] = $target_file;
    } else {
        echo "<p class='error-message'>File move failed!<br>Error: " . error_get_last()['message'] . "<br>Target path: " . htmlspecialchars($target_file) . "</p>";
    }
}
?>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AP Script Automatic Execution</title>
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
        #csv-output {
            margin-top: 20px;
        }
        .script-button-group {
            gap: 10px;
            margin-top: 20px;
            align-items: center;
        }
        .script-btn {
            background-color: #5cb85c;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s;
            white-space: nowrap;
        }
        .script-btn:hover {
            background-color: #4cae4c;
        }
        .script-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
		
		#csv-table-container {
        overflow-x: auto; /* 가로 스크롤 활성화 */
        margin-top: 20px;
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
			white-space: nowrap;			
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
    <h1>AP Script Automatic Execution</h1>
    <p>
        <a href="../portal/index.php" class="menu-link">☎ HOME</a><p>
        <a href="./iplist_upload.php" class="menu-link">◀ Start a new script</a>
    </p>

    <?php if (isset($_SESSION['csv_path']) && $_SERVER['REQUEST_METHOD'] == 'POST'): ?>
        <p class='message'>✅ File upload successful: <?php echo htmlspecialchars(basename($_SESSION['csv_path'])); ?></p>
        <h3>Uploaded CSV Content</h3>
        <?php
            $csv_data = array_map('str_getcsv', file($_SESSION['csv_path']));
            echo "<div class='note'>";
            echo "<table class='styled-table'>";
            echo "<thead><tr>";
            foreach ($csv_data[0] as $header) {
                echo "<th>" . htmlspecialchars($header) . "</th>";
            }
            echo "</tr></thead><tbody>";
            for ($i = 1; $i < count($csv_data); $i++) {
                echo "<tr>";
                foreach ($csv_data[$i] as $cell) {
                    echo "<td>" . htmlspecialchars($cell) . "</td>";
                }
                echo "</tr>";
            }
            echo "</tbody></table></div>";
        ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['csv_path'])): ?>
        <h3>Click a script</h3>
        <div class="script-button-group">
            <button class="script-btn" data-script="changeip">Run changeip.sh</button> → AP will not reboot<p>
			* Change current_ip to new_IP, subnet, and g/w<p>
			* Set SZ IP and apply hostname to the AP<p>
            <?php // <button class="script-btn" data-script="devicename">devicename.sh 실행</button> -> Change hostname only -> AP will not reboot<p> //Apply after successful implementation?>
            <?php // <button class="script-btn" data-script="sz_devicename_changeip">sz_devicename_changeip 실행</button> -> Apply hostname change and SZ IP -> AP will not reboot<p> //Apply after successful implementation?>
            <?php // <button class="script-btn" data-script="factory_reset">factory_reset.sh 실행</button> -> Apply factory reset command and reboot -> AP will reboot<p> //Apply after successful implementation?>
            <?php // <button class="script-btn" data-script="reboot">reboot.sh 실행</button> -> Run AP reboot<p> //Apply after successful implementation?>
        </div>
        <div id="output"></div>
        <div id="csv-output"></div>
        <p>
            <a href="#" id="logDownload" class="menu-link" style="display:none;" target="_blank">📄 Download Log</a><p>
            <a href="#" id="resultDownload" class="menu-link" style="display:none;" target="_blank">📄 Download Result</a>
        </p>
    <?php endif; ?>

    <div class="note">
        <p>※ After uploading the CSV file, it will automatically attempt to connect using the entered password, followed by sp-admin, and then ruckus12#$.</p>
        <p><span class="note-text">※ For a factory-reset AP, the password will be set to ruckus12#$ after the script is applied.</span></p>
    </div>
    <p>
        <a href="./iplist_upload.php" class="menu-link">◀ Start a new script</a><p>
        <a href="../portal/index.php" class="menu-link">☎ HOME</a>
    </p>

    <script>
        document.querySelectorAll('.script-btn').forEach(button => {
            button.addEventListener('click', function() {
                const script = this.getAttribute('data-script');
                const outputDiv = document.getElementById('output');
                const csvOutputDiv = document.getElementById('csv-output');
                const logDownloadLink = document.getElementById('logDownload');
                const resultDownloadLink = document.getElementById('resultDownload');
                
                document.querySelectorAll('.script-btn').forEach(btn => btn.disabled = true);
                
                outputDiv.innerHTML = `Script execution starting... Please wait for the result.\n`;
                csvOutputDiv.innerHTML = '';
                logDownloadLink.style.display = 'none';
                resultDownloadLink.style.display = 'none';

                const xhr = new XMLHttpRequest();
                xhr.open('GET', `?run_script=1&script=${script}`, true);
                
                xhr.onload = function () {
                    if (xhr.status === 200) {
                        const response = xhr.responseText;
                        const parts = response.split('---CSV_TABLE_START---');

                        const logText = parts[0];
                        const csvTableHtml = parts.length > 1 ? parts[1] : '';

                        outputDiv.innerHTML = logText.replace(/\n/g, '<br>');
                        csvOutputDiv.innerHTML = csvTableHtml;
                        
                        const logFileMatch = logText.match(/Log file:\\s*(.*)/);
                        const resultFileMatch = logText.match(/Result file:\\s*(.*)/);

                        if (logFileMatch && logFileMatch[1]) {
                            logDownloadLink.href = logFileMatch[1].trim();
                            logDownloadLink.style.display = 'inline';
                        }
                        if (resultFileMatch && resultFileMatch[1]) {
                            resultDownloadLink.href = resultFileMatch[1].trim();
                            resultDownloadLink.style.display = 'inline';
                        }
                    } else {
                        outputDiv.innerHTML = 'An error occurred while running the script.';
                    }
                    outputDiv.scrollTop = outputDiv.scrollHeight;
                    document.querySelectorAll('.script-btn').forEach(btn => btn.disabled = false);
                };

                xhr.onerror = function () {
                    outputDiv.innerHTML = 'A network error occurred.';
                    document.querySelectorAll('.script-btn').forEach(btn => btn.disabled = false);
                };

                xhr.send();
            });
        });
    </script>
</div>
</body>
</html>