<?php
require __DIR__ . '/../libs/PHPMailer/Exception.php';
require __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
require __DIR__ . '/../libs/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

$start = microtime(true);
$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->SMTPAuth = true;
$mail->Username = 'weathernet@sode-edu.in';
$mail->Password = 'sczo ysih koye emsx';
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port = 587;
$mail->Timeout = 5;
$mail->SMTPOptions = [
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ]
];

$mail->setFrom('weathernet@sode-edu.in', 'Test');
$mail->addAddress('weathernet@sode-edu.in');
$mail->Subject = 'Speed Test 465';
$mail->Body = 'Speed test';
$mail->send();

$time = (microtime(true) - $start) * 1000;
echo "Port 465 Time: " . round($time) . " ms\n";
