<?php
function sendLineNotify($message) {
    $access_token = "lIKMvrGp5nqKd2HesyV7e9uR39+QzPArXjDqs8l4OrpV7XgyEWe6kQj06WW6PhmRWsAzPaxFhwrhS+ww/ZKe+QXQdYt1yIvum0Mhexypmop7zsf9d0Ti3uPdE1nRomdb8n5yPWoQBoD2OCcceD1FCAdB04t89/1O/w1cDnyilFU=";
    $userId = "Cf7022e3078599b16a20bba34597e1459"; // group
    // $userId = "Udb349f6c975b3413a673f641b6e2e387"; // admin

    $url = "https://api.line.me/v2/bot/message/push";

    $data = [
        "to" => $userId,
        "messages" => [
            ["type" => "text", "text" => $message]
        ]
    ];

    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer {$access_token}"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // echo "HTTP Code: $httpCode<br>";
    // echo "Response: $result<br>";

}
?>
