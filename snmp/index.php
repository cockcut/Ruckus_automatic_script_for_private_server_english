<?php
/**
 * A script that uses SNMP on a Ruckus ICX switch to query device information, interface names, and ARP lists, and then displays the results on the screen or downloads them as a CSV file. All functions are integrated into a single file.
 */

// For PHP 5.4 compatibility, array() was used instead of short array syntax.

$output1 = '';
$output2 = '';
$community_raw = '';
$switch_ip_raw = '';
$model = 'N/A';
$version = 'N/A';
$hostname = 'N/A';

// Check and secure data sent via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['community']) && isset($_POST['switch_ip'])) {
    $community_raw = $_POST['community'];
    $switch_ip_raw = $_POST['switch_ip'];

    $community = escapeshellarg($community_raw);
    $switch_ip = escapeshellarg($switch_ip_raw);
    $snmp_version = '2c'; // SNMP version 2c is fixed

    // SNMP Command 1: Query sysDescr (device information)
    $command_sysdescr = "snmpget -c {$community} -v {$snmp_version} {$switch_ip} .1.3.6.1.2.1.1.1.0";
    $output_sysdescr = shell_exec($command_sysdescr);

    // Extract device model and firmware version
    if ($output_sysdescr) {
        if (preg_match('/STRING:\s+Ruckus\s+Wireless,\s+Inc\.\s+(ICX\S+),\s+IronWare\s+Version\s+(\S+)/i', $output_sysdescr, $matches)) {
            $full_version_string = $matches[2];
            
            // Extract model and version information
            $model = $matches[1];
            $version = preg_replace('/T\d+$/', '', $full_version_string); // Remove TXXX pattern
        }
    }

    // SNMP Command 2: Query sysName (hostname)
    $command_sysname = "snmpget -c {$community} -v {$snmp_version} {$switch_ip} .1.3.6.1.2.1.1.5.0";
    $output_sysname = shell_exec($command_sysname);

    // Extract hostname
    if ($output_sysname) {
        if (preg_match('/STRING:\s+(.*)/', $output_sysname, $matches)) {
            $hostname = $matches[1];
        }
    }

    // SNMP Command 3: Query ifDescr (interface description)
    $command1 = "snmpbulkwalk -c {$community} -v {$snmp_version} {$switch_ip} .1.3.6.1.2.1.2.2.1.2";

    // SNMP Command 4: Query ipNetToPhysicalPhysAddress (ARP table)
    $command2 = "snmpbulkwalk -c {$community} -v {$snmp_version} {$switch_ip} .1.3.6.1.2.1.4.35.1.4";

    // Execute command with shell_exec()
    $output1 = shell_exec($command1);
    $output2 = shell_exec($command2);

    // Parse the result of the first command to map index and description
    $descriptions = array();
    if ($output1) {
        $lines1 = explode("\n", trim($output1));
        foreach ($lines1 as $line) {
            if (preg_match('/ifDescr\.(\d+)\s+=\s+STRING:\s+(.*)/', $line, $matches)) {
                $index = $matches[1];
                $description = $matches[2];
                $descriptions[$index] = $description;
            }
        }
    }

    // Check if it is a CSV download request
    if (isset($_POST['download_csv'])) {
        // Set CSV file headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="snmp_results.csv"');

        // Output CSV file
        $output_csv = fopen('php://output', 'w');
        fputcsv($output_csv, array('IP', 'MAC', 'VLAN', 'MAC (lowercase)')); // Modify CSV header

        if ($output2) {
            $lines2 = explode("\n", trim($output2));
            foreach ($lines2 as $line) {
                // Handle both the existing STRING format and the new OctetString format
                if (preg_match('/ipNetToPhysicalPhysAddress\.(\d+)\.ipv4\."([^"]+)"\s+=\s+STRING:\s+(.*)/', $line, $matches)) {
                    $index = $matches[1];
                    $ip = $matches[2];
                    $mac = $matches[3];
                    
                    // Reformat MAC address (colon format)
                    $mac_parts = explode(':', $mac);
                    $formatted_mac_parts = array();
                    foreach ($mac_parts as $part) {
                        $hex_value = hexdec($part);
                        $formatted_mac_parts[] = sprintf("%02X", $hex_value);
                    }
                    $formatted_mac = implode(':', $formatted_mac_parts);
                    $formatted_mac_lower = strtolower($formatted_mac); // Lowercase MAC address
                    
                    $description = isset($descriptions[$index]) ? $descriptions[$index] : "Unknown";
                    fputcsv($output_csv, array($ip, $formatted_mac, $description, $formatted_mac_lower)); // Modify CSV data
                } elseif (preg_match('/ipNetToPhysicalPhysAddress\.(\d+)\.ipv4\."([^"]+)"\s+=\s+Value\s+\(OctetString\):\s+([\S\s]+)/', $line, $matches)) {
                    $index = $matches[1];
                    $ip = $matches[2];
                    $raw_mac_str = trim($matches[3]);
                    
                    // Reformat MAC address (hyphen format)
                    $mac_parts = explode('-', $raw_mac_str);
                    $formatted_mac_parts = array();
                    foreach ($mac_parts as $part) {
                        $hex_value = hexdec($part);
                        $formatted_mac_parts[] = sprintf("%02X", $hex_value);
                    }
                    $formatted_mac = implode(':', $formatted_mac_parts);
                    $formatted_mac_lower = strtolower($formatted_mac); // Lowercase MAC address
                    
                    $description = isset($descriptions[$index]) ? $descriptions[$index] : "Unknown";
                    fputcsv($output_csv, array($ip, $formatted_mac, $description, $formatted_mac_lower)); // Modify CSV data
                }
            }
        }
        fclose($output_csv);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Query SNMP Data</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
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
<body class="bg-gray-100 p-8 flex items-center justify-center min-h-screen">

    <?php if (empty($output1) && empty($output2) && !isset($_POST['community'])) { ?>
        <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
			<a href="../portal/index.php" class="menu-link">☎ HOME</a>
            <h1 class="text-2xl font-bold text-center mb-6">ICX ARP Query</h1>
            <br>
            <form action="index.php" method="post" class="space-y-4">
                <div>
                    <label for="community" class="block text-sm font-medium text-gray-700">SNMP Community</label>
                    <input type="text" id="community" name="community" placeholder="public" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label for="switch_ip" class="block text-sm font-medium text-gray-700">ICX Switch IP</label>
                    <input type="text" id="switch_ip" name="switch_ip" placeholder="192.168.0.1" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <button type="submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Query
                </button>
            </form>
        </div>
    <?php } else { ?>
        <div class="bg-white p-6 rounded-lg shadow-md max-w-4xl mx-auto">
            <h1 class="text-2xl font-bold mb-4">SNMP Data Combined Result</h1>
            <class="text-gray-600 mb-4">
				▶ Switch IP: <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($switch_ip_raw); ?></span><p>
                ▶ Hostname: <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($hostname); ?></span> <p>
                ▶ Model: <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($model); ?></span><p>
                ▶ Firmware Version: <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($version); ?></span> <p>

			</class>
			<br>
            <pre class="bg-gray-800 text-green-400 p-4 rounded-md overflow-x-auto">
IP               | MAC                 | MAC (lower)         | VLAN
-----------------|---------------------|---------------------|----------------
<?php
// Parse the result of the second command and combine with the first result for output
if ($output2) {
    $lines2 = explode("\n", trim($output2));
    foreach ($lines2 as $line) {
        // Handle both the existing STRING format and the new OctetString format
        if (preg_match('/ipNetToPhysicalPhysAddress\.(\d+)\.ipv4\."([^"]+)"\s+=\s+STRING:\s+(.*)/', $line, $matches)) {
            $index = $matches[1];
            $ip = $matches[2];
            $mac = $matches[3];
            
            // Reformat MAC address (colon format)
            $mac_parts = explode(':', $mac);
            $formatted_mac_parts = array();
            foreach ($mac_parts as $part) {
                $hex_value = hexdec($part);
                $formatted_mac_parts[] = sprintf("%02X", $hex_value);
            }
            $formatted_mac = implode(':', $formatted_mac_parts);
            $formatted_mac_lower = strtolower($formatted_mac); // Lowercase MAC address
            
            $description = isset($descriptions[$index]) ? $descriptions[$index] : "Unknown";
            echo str_pad($ip, 16) . " | " . str_pad($formatted_mac, 19) . " | " . str_pad($formatted_mac_lower, 19) . " | " . $description . "\n";
        } elseif (preg_match('/ipNetToPhysicalPhysAddress\.(\d+)\.ipv4\."([^"]+)"\s+=\s+Value\s+\(OctetString\):\s+([\S\s]+)/', $line, $matches)) {
            $index = $matches[1];
            $ip = $matches[2];
            $raw_mac_str = trim($matches[3]);
            
            // Reformat MAC address (hyphen format)
            $mac_parts = explode('-', $raw_mac_str);
            $formatted_mac_parts = array();
            foreach ($mac_parts as $part) {
                $hex_value = hexdec($part);
                $formatted_mac_parts[] = sprintf("%02X", $hex_value);
            }
            $formatted_mac = implode(':', $formatted_mac_parts);
            $formatted_mac_lower = strtolower($formatted_mac); // Lowercase MAC address
            
            $description = isset($descriptions[$index]) ? $descriptions[$index] : "Unknown";
            echo str_pad($ip, 16) . " | " . str_pad($formatted_mac, 19) . " | " . str_pad($formatted_mac_lower, 19) . " | " . $description . "\n";
        }
    }
} else {
    echo "No command execution result.\n";
    echo "Please check the SNMP Community, Switch IP, and network status.";
}
?>
            </pre>
            <div class="mt-6 text-center space-x-4">
                <form action="index.php" method="post" class="inline-block">
                    <input type="hidden" name="community" value="<?php echo htmlspecialchars($community_raw); ?>">
                    <input type="hidden" name="switch_ip" value="<?php echo htmlspecialchars($switch_ip_raw); ?>">
                    <button type="submit" class="inline-block bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                        Refresh
                    </button>
                </form>
                <a href="index.php" class="inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                    Query from the beginning
                </a>
                <form action="index.php" method="post" class="inline-block">
                    <input type="hidden" name="community" value="<?php echo htmlspecialchars($community_raw); ?>">
                    <input type="hidden" name="switch_ip" value="<?php echo htmlspecialchars($switch_ip_raw); ?>">
                    <input type="hidden" name="download_csv" value="1">
                    <button type="submit" class="bg-gray-700 hover:bg-gray-800 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                        Get as CSV
                    </button>
                </form>
            </div>
        </div>
    <?php } ?>
</body>
</html>