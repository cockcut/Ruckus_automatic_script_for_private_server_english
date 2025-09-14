<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AP IP List File (csv) Upload</title>
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
        h2 {
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
        .styled-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 12px;
        }
        .styled-table th, .styled-table td {
            border: 1px solid #ddd;
            padding: 5px;
            text-align: left;
        }
        .styled-table th {
            background-color: #f2f2f2;
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
    <h1>AP IP List File (csv) Upload</h1>
    <p>
        <a href="../portal/index.php" class="menu-link">☎ HOME</a><p>
        <a href="./iplist_upload.php" class="menu-link">◀ Start a New Script</a><p>
        <a href='./example/sample.csv' class="menu-link" download>📁 Download sample CSV file</a>
    </p>

    <h2>AP IP List File (csv) Upload</h2>
    
    <div class="note">
        <p>※ The following shows an example of the content of the sample CSV. Download and edit the sample CSV above to upload it.</p>
        <table class="styled-table">
            <thead>
                <tr>
                    <th>current_ip</th>
                    <th>ip</th>
                    <th>pass</th>
                    <th>new_IP</th>
                    <th>subnet</th>
                    <th>g/w</th>
                    <th>sz</th>
                    <th>hostname</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>10.10.10.100</td><td>super</td><td>ruckus12#$</td><td>10.10.10.200</td><td>255.255.255.0</td><td>10.10.10.1</td><td>100.100.100.100</td><td>ap1</td></tr>
                <tr><td>10.10.20.100</td><td>super</td><td>sp-admin</td><td>10.10.20.234</td><td>255.255.255.0</td><td>10.10.20.254</td><td>10.10.10.10</td><td>ap2</td></tr>
                <tr><td>10.10.20.200</td><td>super</td><td>sp-admin</td><td>10.10.20.235</td><td>255.255.255.0</td><td>10.10.20.254</td><td>10.10.20.254</td><td>ap3</td></tr>
            </tbody>
        </table>
    </div>

<?php
session_start();
$upload_dir = 'uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $target_file = $upload_dir . basename($file['name']);

    if ($file['error'] !== 0) {
        echo "<p class='error-message'>File upload failed! Error code: " . $file['error'] . "</p>";
    } else {
        if (file_exists($target_file)) {
            unlink($target_file);
            sleep(1);
        }
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            chmod($target_file, 0644);
            $_SESSION['csv_path'] = $target_file;
            echo "<p class='message'>✅ File upload successful: " . htmlspecialchars($file['name']) . "</p>";

            echo "<h3>Uploaded CSV Content</h3>";
            $csv_data = array_map('str_getcsv', file($target_file));
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
            echo "</tbody></table>";
        } else {
            echo "<p class='error-message'>File move failed: " . error_get_last()['message'] . "</p>";
        }
    }
}
?>

<form action="script_run_sz.php" method="post" enctype="multipart/form-data">
    <h3>Upload AP IP List File</h3>
    <input type="file" name="file" required>
    <button type="submit">Upload AP List</button>
</form>
<div class="note">
    <p>※ After uploading the CSV file, it will automatically attempt to connect using the password entered, followed by sp-admin, and then ruckus12#$.</p>
    <p><span class="note-text">※ After applying the script to a factory reset AP, the password will be set to ruckus12#$.</span></p>
</div>
<p>
    <a href="./iplist_upload.php" class="menu-link">◀ Start a New Script</a><p>
    <a href="../portal/index.php" class="menu-link">☎ HOME</a>
</p>
</div>
</body>
</html>