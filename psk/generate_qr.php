<?php
// QR 코드 생성 라이브러리 포함
require_once 'phpqrcode/qrlib.php';

// URL 매개변수 받기
$ssid = isset($_GET['ssid']) ? $_GET['ssid'] : 'Unknown SSID';
$password = isset($_GET['password']) ? $_GET['password'] : '';
$method = isset($_GET['method']) ? $_GET['method'] : '';
$output_type = isset($_GET['output']) ? $_GET['output'] : 'html'; // 추가된 부분

// WLAN 스키마 생성
$wlan_schema = "";
if ($method === 'WPA2' || $method === 'WPA3') {
    $wlan_schema = "WIFI:S:" . $ssid . ";T:" . $method . ";P:" . $password . ";;";
} else {
    $wlan_schema = "WIFI:S:" . $ssid . ";T:nopass;;";
}

// 요청된 출력 유형에 따라 처리
if ($output_type === 'image') {
    // 이미지로 직접 출력
    header('Content-Type: image/png');
    QRcode::png($wlan_schema, false, QR_ECLEVEL_L, 10);
    exit();
} else {
    // HTML 페이지로 출력
    ob_start();
    QRcode::png($wlan_schema, null, QR_ECLEVEL_L, 10);
    $imageData = ob_get_clean();
    $base64Image = 'data:image/png;base64,' . base64_encode($imageData);

    ?>
    <!DOCTYPE html>
    <html lang="ko">
    <head>
        <meta charset="UTF-8">
        <title><?php echo htmlspecialchars($ssid); ?> QR 코드</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
                background-color: #f4f4f4;
            }
            h1 {
                color: #333;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <h1><?php echo htmlspecialchars($ssid); ?></h1>
        <img src="<?php echo $base64Image; ?>" alt="WLAN QR Code">
    </body>
    </html>
    <?php
}
?>
