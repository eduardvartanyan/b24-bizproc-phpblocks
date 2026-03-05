<?php

// Запуск: https://ipvartanyan.ru/evraas/update-invoice.php?year=2025&month=08

$currentTime = date('Y-m-d H:i:s') . PHP_EOL;
file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/evraas-log.txt', $currentTime, FILE_APPEND);

function executeCurl($params) {
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://188.120.239.44/billmgr?' . http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => array(
            'Cookie: billmgrlang5=dragon:ru;'
        ),
    ]);

    $data = json_decode(curl_exec($curl), true);

    curl_close($curl);

    return $data;
}

function getMonthName($monthNumber) {
    $monthNames = [
        1 => 'январь',
        2 => 'февраль',
        3 => 'март',
        4 => 'апрель',
        5 => 'май',
        6 => 'июнь',
        7 => 'июль',
        8 => 'август',
        9 => 'сентябрь',
        10 => 'октябрь',
        11 => 'ноябрь',
        12 => 'декабрь',
    ];
    return $monthNames[(int) $monthNumber];
}
function getCompanyName($companyId) {
    $companyNames = [
        1 => 'ЕДЦ',
        5 => 'ЕО',
    ];

    return $companyNames[(int) $companyId];
}
function getCompanyFullName($companyId)
{
    $companyNames = [
        1 => 'ООО "ЕВРААС ЕДЦ"',
        5 => 'ООО "ЕВРААС-Охрана"',
    ];

    return $companyNames[(int) $companyId];
}
function formatDate($date) {
    return date('d.m.Y', strtotime($date));
}

function generateRowTitle($sessionId, $rows, $clientId, $companyId, $contractList) {

    $rowCount = count($rows);

    $profile = executeCurl([
        'auth' => $sessionId,
        'out' => 'json',
        'func' => 'profile.edit',
        'elid' => $clientId
    ]);

    $companyName = getCompanyName($companyId);

    $pattern = '/^' . preg_quote($companyName, '/') . ':\s*(.+)$/mu';

    $rowTitles = [];
    if (preg_match($pattern, $profile['doc']['note']['$'], $matches)) {
        $contractNumList = explode(',', $matches[1]);
        if (count($contractNumList) == $rowCount) {
            foreach ($contractNumList as $item) {
                $rowTitles[] = 'Услуги охраны по договору № ' . trim($item);
            }
        }
    }

    if (count($rowTitles) == 0) {
        $clientName = $profile['doc']['name']['$'];
        $companyFullName = getCompanyFullName($companyId);
        $clientContracts = $contractList[$clientName][$companyFullName] ?? [];

        if (count($clientContracts) == 1) {
            $contractNumList = explode(',', $clientContracts[0]['number']);
            if (count($contractNumList) == $rowCount) {
                foreach ($contractNumList as $item) {
                    $rowTitles[] = 'Услуги охраны по договору № ' . trim($item) . ' от ' . formatDate($clientContracts[0]['date']) . ' г.';
                }
            }
        }
    }

    if (count($rowTitles) == 0)
    {
        if ($paymentDesc = $profile['doc']['payment_description']['$'])
        {
            $char = mb_strtoupper(substr($paymentDesc,0,2), 'utf-8');
            $paymentDesc[0] = $char[0];
            $paymentDesc[1] = $char[1];

            for ($i = 0; $i < $rowCount; ++$i)
            {
                $rowTitles[] = $paymentDesc;
            }
        }
    }

    if (count($rowTitles) == 0)
    {
        for ($i = 0; $i < $rowCount; ++$i)
        {
            $rowTitles[] = 'Услуги охраны';
        }
    }

    $result = [];
    foreach ($rows as $i => $row) {
        $result[$row] = $rowTitles[$i];
    }

    return $result;
}



$year = $_GET['year'] ?? date('Y', strtotime('last month'));
$month = $_GET['month'] ?? date('m', strtotime('last month'));
$periodStr = ' за' . ' ' . getMonthName($month) . ' ' . $year . ' г.';
$createDate = $year . '-' . $month . '-' . cal_days_in_month(CAL_GREGORIAN, $month, $year);



$sessionData = executeCurl([
    'out' => 'json',
    'func' => 'auth',
    'username' => 'vartanyan',
    'password' => 'rX1iZ4rV8j'
]);
$sessionId = $sessionData['doc']['auth']['$'];

if (!$sessionId) die('Не удалось авторизоваться');
echo 'Авторизовался!<br>';


executeCurl([
    'auth' => $sessionId,
    'out' => 'json',
    'func' => 'invoice.filter',
    'invoice_status' => '1',
    'fromdate' => $createDate,
    'todate' => $createDate,
//    'id' => '164230',
    'sok' => 'ok'
]);

$invoiceList = executeCurl([
    'auth' => $sessionId,
    'out' => 'json',
    'func' => 'invoice'
]);



$prodExceptions = ['СМС информирование'];

$contractData = executeCurl([
    'auth' => $sessionId,
    'out' => 'json',
    'func' => 'contract',
    'p_cnt' => '5000'
]);

$contractList = [];
foreach ($contractData['doc']['elem'] as $item) {
    $contractList[$item['client_name']['$']][$item['company_name']['$']][] = [
        'date' => $item['signdate']['$'],
        'number' => $item['number']['$'],
    ];
}

foreach ($invoiceList['doc']['elem'] as $item)
{
    $invoiceRows = executeCurl([
        'auth' => $sessionId,
        'out' => 'json',
        'func' => 'invoice.item',
        'elid' => $item['id']['$']
    ]);

    $rows = $invoiceRows['doc']['elem'];
    $rowCount = is_array($rows) ? count($rows) : 0;

    $changingTitleRowIds = $rowTitles = [];
    foreach ($rows as &$row) {
        $isException = false;
        foreach ($prodExceptions as $prodException) {
            if (strpos($row['name']['$'], $prodException) !== false) {
                $row['name']['$'] = $prodException;
                $isException = true;
            }
        }
        if (!$isException) {
            $changingTitleRowIds[] = $row['id']['$'];
        }
    }

    if ($changingTitleRowIds) {
        $rowTitles = generateRowTitle(
            $sessionId,
            $changingTitleRowIds,
            $item['customer_id']['$'],
            $item['company_id']['$'],
            $contractList
        );
    }

    for ($i = 0; $i < $rowCount; ++$i)
    {
        $elemSum = (float) $rows[$i]['amount']['$'];
        $elemRoundSum = round($elemSum);

        executeCurl([
            'auth' => $sessionId,
            'out' => 'json',
            'func' => 'invoice.item.edit',
            'elid' => $rows[$i]['id']['$'],
            'plid' => $item['id']['$'],
            'amount' => $elemRoundSum,
            'name'=> (in_array($rows[$i]['id']['$'], $changingTitleRowIds) ? $rowTitles[$rows[$i]['id']['$']] : $rows[$i]['name']['$']) . $periodStr,
            'sok' => 'ok'
        ]);
    }

    echo 'Обработал акт # ' . $item['id']['$'] . '.<br>';

//    curl_setopt_array($curl, [
//        CURLOPT_URL => API_URL . http_build_query([
//                'auth' => $sessionId,
//                'out' => 'json',
//                'func' => 'invoice.send',
//                'method' => 'email',
//                'elid' => $item['id']['$'],
//                'sok' => 'ok',
//            ]),
//        CURLOPT_RETURNTRANSFER => true,
//        CURLOPT_ENCODING => '',
//        CURLOPT_MAXREDIRS => 10,
//        CURLOPT_TIMEOUT => 0,
//        CURLOPT_FOLLOWLOCATION => true,
//        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//        CURLOPT_CUSTOMREQUEST => 'GET',
//        CURLOPT_SSL_VERIFYHOST => false,
//        CURLOPT_SSL_VERIFYPEER => false,
//    ]);
//
//    curl_exec($curl);
}

file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/evraas-log.txt', PHP_EOL . PHP_EOL, FILE_APPEND);

//echo 'Акты отправлены на почту!<br>';
