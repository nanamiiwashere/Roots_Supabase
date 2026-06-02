<?php
function uploadToSupabase($fileTmp, $fileName, $bucket){
    $url = SUPABASE_URL . '/storage/v1/object/' . $bucket . "/" . $fileName;

    $header = [
        "apikey: " . SUPABASE_KEY,
        "Authorization: " . SUPABASE_KEY,
        "Content-Type:  application/octet-stream"
    ];

    $ch - curl_init($curl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($fileTmp));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_exec($ch);
    curl_close($ch);

    return SUPABASE_URL . "/storage/v1/object/public" . $bucket . "/" . $fileName;
}
?>