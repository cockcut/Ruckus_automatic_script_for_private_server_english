<?php

// ==========================================================
// Administrator-friendly variable settings for modifying and adding URLs
// ==========================================================
$urls = [
    '6.1.2' => 'https://docs-be.commscope.com/api/bundle/sz-612-upgradeguide-sz/page/GUID-4F87EF93-EA50-4F46-B5EA-5A21EDD5D69A.html',
    '7.1.0' => 'https://docs-be.commscope.com/api/bundle/sz-710-upgradeguide-sz/page/GUID-BDA54B18-3CE0-4BBE-B315-9BE0D0AC5C17.html',
    '7.1.1' => 'https://docs-be.commscope.com/api/bundle/sz-711-upgradeguide-sz/page/GUID-BDA54B18-3CE0-4BBE-B315-9BE0D0AC5C17.html',
];

$data_file = 'sz_ap_models.json';
$all_ap_models = [];

// If the refresh button is pressed or the data file does not exist
if ($_SERVER['REQUEST_METHOD'] == 'POST' || !file_exists($data_file)) {
    echo "<p>Updating the supported AP list...</p>";
    
    // Check if cURL is enabled
    if (!function_exists('curl_init')) {
        die('cURL is not enabled. Please enable cURL in the php.ini file.');
    }

    foreach ($urls as $version => $url) {
        // Fetch HTML content with cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $html = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $version_ap_models = [];
        if ($html === false || $http_code !== 200) {
            $version_ap_models = ["Error: Failed to download data. (HTTP status code: " . $http_code . ")"];
        } else {
            // Extract AP models using DOM
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML($html);
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);
            // Use the same XPath query for all URLs
            $query = '//h2[contains(text(), "Supported AP Models")]/following-sibling::table[1]//td';
            $nodes = $xpath->query($query);
            
            if ($nodes->length > 0) {
                foreach ($nodes as $node) {
                    $raw_text = trim($node->nodeValue);
                    // Extract model names using a more robust regular expression
                    if (preg_match_all('/(?:[RTH]\d{2,4}(?:[A-Z]{0,2}e?)?|C110|M510|E510)/i', $raw_text, $matches)) {
                        $version_ap_models = array_merge($version_ap_models, $matches[0]);
                    }
                }
            }
        }
        
        // Remove duplicates and sort
        $version_ap_models = array_unique($version_ap_models);
        sort($version_ap_models);
        $all_ap_models[$version] = $version_ap_models;
    }
    
    // Save processed data to a JSON file
    file_put_contents($data_file, json_encode($all_ap_models));
    
    // After the update is complete, refresh the page to make the message disappear
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// If the existing data file exists, load data from the file
$all_ap_models = [];
if (file_exists($data_file)) {
    $json_data = file_get_contents($data_file);
    if ($json_data !== false) {
        $all_ap_models = json_decode($json_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $all_ap_models = [];
        }
    }
} else {
    echo "<p>No data yet. Please press the 'Update Supported APs' button to fetch the data.</p>";
}

?>

<style>
/* Table style */
.ap-table {
    border-collapse: collapse;
    margin-top: 20px;
    font-family: Arial, sans-serif;
    table-layout: fixed;
}

/* Basic style for all cells */
.ap-table td, .ap-table th {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: center;
	width: 110px;
}

/* Header row style */
.ap-table thead {
    background-color: #f7f7f7;
}

/* Header cell style */
.ap-table th {
    font-weight: bold;
    color: #333;
    text-align: center;
}

/* Body cell style */
.ap-table tbody tr:nth-child(even) {
    background-color: #f9f9f9;
}

/* Hover effect */
.ap-table tbody tr:hover {
    background-color: #e6e6e6;
}

/* Refresh button style */
.refresh-button {
    padding: 10px 15px;
    font-size: 14px;
    cursor: pointer;
    background-color: #007BFF;
    color: white;
    border: none;
    border-radius: 5px;
}

.refresh-button:hover {
    background-color: #0056b3;
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
		
</style>

<div>
    <a href="../portal/index.php" class="menu-link">☎ HOME</a>
	<br>
    <a href="./index.html" class="menu-link">Previous Screen</a>
</div>

<h1>RUCKUS SmartZone Version-specific AP Support Status</h1>

<form method="post" action="">
    <button type="submit" class="refresh-button">Update Supported APs</button>
</form>

<table class="ap-table" cellpadding="0" cellspacing="0">
    <thead>
        <tr>
            <?php foreach ($all_ap_models as $version => $models): ?>
                <th><?php echo htmlspecialchars($version); ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php
        $max_models = 0;
        foreach ($all_ap_models as $models) {
            if (count($models) > $max_models) {
                $max_models = count($models);
            }
        }

        for ($i = 0; $i < $max_models; $i++):
        ?>
            <tr>
                <?php
                foreach ($all_ap_models as $models):
                    $model = isset($models[$i]) ? $models[$i] : '';
                ?>
                    <td><?php echo htmlspecialchars($model); ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endfor; ?>
    </tbody>
</table>