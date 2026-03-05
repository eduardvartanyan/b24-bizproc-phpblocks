<?php

// Запуск: https://ipvartanyan.ru/evraas/edc-generate-invoice.php?year=2025&month=07

const API_URL = 'https://188.120.239.44/billmgr?';



$year = $_GET['year'] ?? date('Y', strtotime('last month'));
$month = $_GET['month'] ?? date('m', strtotime('last month'));

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => API_URL . http_build_query([
        'out' => 'json',
        'func' => 'auth',
        'username' => 'vartanyan',
        'password' => 'rX1iZ4rV8j'
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$data = json_decode(curl_exec($curl), true);
$sessionId = $data['doc']['auth']['$'];

if (!$sessionId) die('Не удалось авторизоваться');

echo 'Авторизовался!<br>';

$from = $year . '-' . $month . '-01';
$to = $year . '-' . $month . '-' . cal_days_in_month(CAL_GREGORIAN, $month, $year);

// 1 - ЕДЦ, 5 - ЕО
$companyIds = [
    1,
    5
];

foreach ($companyIds as $company)
{
    curl_setopt_array($curl, [
        CURLOPT_URL => API_URL . http_build_query([
                'auth' => $sessionId,
                'out' => 'json',
                'func' => 'invoice.generate',
                'company' => $company,
                'gentype' => 'all',
                'profiletype' => '1,2,3',
                'invoice_status' => '1',
                'fromdate' => $from,
                'todate' => $to,
                'cdate' => $to,
                'sok' => 'ok',
            ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    curl_exec($curl);
}

echo 'Сгенерировал акты!';

curl_close($curl);
