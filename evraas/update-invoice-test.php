<?php

// Запуск: https://ipvartanyan.ru/evraas/update-invoice-test.php

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
function getCompanyName($company_id) {
    $companyNames = [
        1 => 'ЕДЦ',
        5 => 'ЕО',
    ];

    return $companyNames[(int) $company_id];
}
function getCompanyFullName($company_id)
{
    $companyNames = [
        1 => 'ООО "ЕВРААС ЕДЦ"',
        5 => 'ООО "ЕВРААС-Охрана"',
    ];

    return $companyNames[(int)$company_id];
}
function formatDate($date) {
    return date('d.m.Y', strtotime($date));
}

function generateRowTitle($curl, $sessionId, $invoice) {
    curl_setopt_array($curl, [
        CURLOPT_URL => API_URL . http_build_query([
                'auth' => $sessionId,
                'out' => 'json',
                'func' => 'profile.edit',
                'elid' => '2043'
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
        CURLOPT_HTTPHEADER => ['Cookie: billmgrlang5=dragon:ru;'],
    ]);

    $profile = json_decode(curl_exec($curl), true);

    $companyId = '1';
    $companyName = getCompanyName($companyId);

    $pattern = '/^' . preg_quote($companyName, '/') . ':\s*(.+)$/mu';

    $rowCount = count($invoice['doc']['elem']);

    $rowTitles = [];
    if (preg_match($pattern, $profile['doc']['note']['$'], $matches))
    {
        $contractNumList = explode(',', $matches[1]);

        if (count($contractNumList) == $rowCount)
        {
            foreach ($contractNumList as $item)
            {
                $rowTitles[] = 'Услуги охраны по договору № ' . trim($item);
            }
        }
    }

    if (count($rowTitles) == 0)
    {
        curl_setopt_array($curl, [
            CURLOPT_URL => API_URL . http_build_query([
                    'auth' => $sessionId,
                    'out' => 'json',
                    'func' => 'contract'
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
            CURLOPT_HTTPHEADER => ['Cookie: billmgrlang5=dragon:ru;'],
        ]);

        $contractData = json_decode(curl_exec($curl), true);

        $contractList = [];
        foreach ($contractData['doc']['elem'] as $item)
        {
            $contractList[$item['client_name']['$']][$item['company_name']['$']][] = [
                'date' => $item['signdate']['$'],
                'number' => $item['number']['$'],
            ];
        }

        $clientName = $profile['doc']['name']['$'];
        $companyFullName = getCompanyFullName($companyId);
        $clientContracts = $contractList[$clientName][$companyFullName] ?? [];

        if (count($clientContracts) == 1)
        {
            $contractNumList = explode(',', $clientContracts[0]['number']);

            if (count($contractNumList) == $rowCount)
            {
                foreach ($contractNumList as $item)
                {
                    $rowTitles[] = 'Услуги охраны по договору № ' . trim($item) . ' от ' . formatDate($clientContracts[0]['date']) . ' г.';
                }
            }
        }
    }

    print_r($rowTitles);

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

    return $rowTitles;
}



const API_URL = 'https://188.120.239.44/billmgr?';

$year = $_GET['year'] ?? date('Y', strtotime('last month'));
$month = $_GET['month'] ?? date('m', strtotime('last month'));
$periodStr = ' за' . ' ' . getMonthName($month) . ' ' . $year . ' г.';



$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => API_URL . http_build_query([
        'out' => 'json',
        'func' => 'auth',
        'username' => 'vartanyan',
        'password' => 'rX1iZ4rV8j',
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



curl_setopt_array($curl, [
    CURLOPT_URL => API_URL . http_build_query([
            'auth' => $sessionId,
            'out' => 'json',
            'func' => 'invoice.item',
            'elid' => '145243'
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
    CURLOPT_HTTPHEADER => ['Cookie: billmgrlang5=dragon:ru;'],
]);

$invoice = json_decode(curl_exec($curl), true);



$rows = $invoice['doc']['elem'];
$rowTitles = generateRowTitle($curl, $sessionId, $invoice);

for ($i = 0; $i < count($rows); ++$i)
{
    $elemSum = (float) $rows[$i]['amount']['$'];
    $elemRoundSum = round($elemSum);

    curl_setopt_array($curl, [
        CURLOPT_URL => API_URL . http_build_query([
                'auth' => $sessionId,
                'out' => 'json',
                'func' => 'invoice.item.edit',
                'elid' => $rows[$i]['id']['$'],
                'plid' => '145243',
                'item_amount' => $elemRoundSum,
                'name'=> $rowTitles[$i] . $periodStr,
                'sok' => 'ok'
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
        CURLOPT_HTTPHEADER => ['Cookie: billmgrlang5=dragon:ru;'],
    ]);

    curl_exec($curl);
}


curl_close($curl);
