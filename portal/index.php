<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HSITX Ruckus Technical Portal(for private server)</title>
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
        .menu-item {
            display: block;
            margin: 10px 0;
            padding: 10px 15px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            text-decoration: none;
            color: #007bff;
            transition: background-color 0.3s, transform 0.3s;
        }
        .menu-item:hover {
            background-color: #e2e6ea;
            transform: translateY(-2px);
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
        .note-list {
            list-style-type: none;
            padding: 0;
        }
        .note-list li {
            margin-bottom: 5px;
        }
        .faq {
            background-color: #e9ecef;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
        }
        .faq h3 {
            margin-top: 0;
            color: #495057;
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
</head>
<body>

<div class="container">
    <h1>HSITX Ruckus Technical Portal(for private server)</h1>
    <p>
        <a href="../api/sz-api-tool.php" class="menu-item">[ 1. View SZ Info ]</a>
    </p>
    <p>
        <a href="../api-u/unleashed-api-tool.php" class="menu-item">[ 2. View Unleashed Info ]</a>
    </p>
	 <p>
        <a href="../psk/change_psk_password.php" class="menu-item">[ 3. Batch Change PSK/SAE Password (Applies only to SZ) ]</a>
    </p>
    <p>
        <a href="../script/iplist_upload.php" class="menu-item">[ 4. Run AP script automatically ]</a>
    </p>
	<p>
        <a href="../upgrade/upgrade.php" class="menu-item">[ 5. Upgrade AP firmware ]</a>
    </p>
	<p>
        <a href="../fw/index.html" class="menu-item">[ 6. Upgrade AP firmware (paste script) ]</a>
    </p>
	<p>
        <a href="../upgrade2sz/upgrade2sz.php" class="menu-item">[ 7. Upgrade AP with SZ firmware and establish connection to SZ ]</a>
    </p>
    <p>
	    <a href="../snmp/index.php" class="menu-item">[ 8. View ICX ARP (using snmp) ]</a>
    </p>
    <p>
        <a href="../oui/oui.txt" class="menu-item">[ 9. View OUI (updated daily)]</a>
    </p>
    <p>
        <a href="../captiveportal/" class="menu-item">[ 10. Web Authentication Page]</a>
    </p>
	<p>
		<a href="../supported/" class="menu-item">[ 11. Check Supported Models for SmartZone, Unleashed, R1]</a>
    </p>

    <div class="note">
        <div class="note-title">☞ Note</div>
        <p>For [ 3. Batch Change PSK/SAE Password (Applies only to SZ) ], you can change all or some PSK/SAE SSIDs in a batch.</p>
        <ul class="note-list">
            <li>0) Only for PSK/SAE SSIDs.</li>
            <li>1) WPA2, WPA3 mixed SSIDs cannot be changed at this time.</li>
        </ul>
    </div>
	
	<div class="note">
        <div class="note-title">☞ Note</div>
        <p>For [ 4. Run AP script automatically ]</p>
        <ul class="note-list">
            <li>0) Download and modify sample.csv, then upload.</li><p>
            <li>1) Change multiple AP IPs (changeip.sh)</li><p>
			<li>┌▶ Change current_ip to new_IP, subnet, g/w</li><p>
			<li>└▶ Set SZ IP and apply AP hostname</li><p>
        </ul>
    </div>
	
	<div class="note">
        <div class="note-title">☞ Note</div>
			<p>For [ 5. Upgrade AP firmware ]</p>
        <ul class="note-list">
            <li>0) Uploading standalone firmware upgrades Unleashed -> Standalone.</li>
            <li>1) Uploading Unleashed firmware upgrades Standalone -> Unleashed.</li>
        </ul>
    </div>
	
	<div class="note">
        <div class="note-title">☞ Note</div>
			<p>For [ 6. Upgrade AP firmware (paste script) ]</p>
        <ul class="note-list">
            <li>0) Upload the firmware to the 'fw' directory and run the script from the bash shell.</li>
            <li>1) It automatically creates a script page for AP upgrade execution.</li>
        </ul>
    </div>

	<div class="note">
        <div class="note-title">☞ Note</div>
			<p>For [ 7. Upgrade AP with SZ firmware and establish connection to SZ ]</p>
        <ul class="note-list">
            <li>0) Check/Inquire available firmware versions by ZONE in the SZ</li>
            <li>1) After checking, select the version to upgrade the AP's firmware to the SZ's firmware, then establish connection to SZ, and change the hostname and IP address. The execution result can be downloaded as a CSV</li>
        </ul>
    </div>

    <div class="note">
        <div class="note-title">☞ Note</div>
        <ul class="note-list">
			<p>For [ 8. View ICX ARP ]</p>
            <li>0) You must set 'snmp-server community xxxx ro' on the ICX.</li>
            <li>1) After viewing, you can print the current ARP list and download it as a CSV.</li>
        </ul>
    </div>
	
    <div class="note">
        <div class="note-title">☞ Note</div>
			<p>For [ 9. View OUI ], it downloads and updates the IEEE OUI daily at 00:00.</p>
        <ul class="note-list">
        </ul>
    </div>

</div>
