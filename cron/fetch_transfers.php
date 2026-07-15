<?php

declare(strict_types=1);

/**
 * 只读拉取当前收款地址的链上转入记录（TronGrid 公开 API），写入 transfer_records。
 * 仅做 GET 查询，不涉及任何私钥、签名或转账/委托操作。
 * 用法：php cron/fetch_transfers.php（建议由 crontab 每 2-5 分钟调度一次）
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require __DIR__ . '/../includes/Database.php';
require __DIR__ . '/../includes/TronAddress.php';
require __DIR__ . '/../includes/Logger.php';

const MIN_AMOUNT_SUN = 1_000_000; // 1 TRX
const FETCH_LIMIT = 50;
const HTTP_TIMEOUT = 10;
const MAX_RETRIES = 2;

function tron_get(string $url, ?string $apiKey): ?array
{
    for ($attempt = 1; $attempt <= MAX_RETRIES; $attempt++) {
        $ch = curl_init($url);
        $headers = ['Accept: application/json'];
        if ($apiKey) {
            $headers[] = 'TRON-PRO-API-KEY: ' . $apiKey;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_TIMEOUT => HTTP_TIMEOUT,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response !== false && $httpCode === 200) {
            $data = json_decode($response, true);
            if (is_array($data)) {
                return $data;
            }
        }

        Logger::write('cron', "TronGrid 请求失败（第 {$attempt} 次）：HTTP {$httpCode} {$curlError}");
        if ($attempt < MAX_RETRIES) {
            sleep(2);
        }
    }

    return null;
}

try {
    $db = Database::getInstance();
} catch (\RuntimeException $e) {
    Logger::write('cron', '系统尚未安装，跳过本次抓取');
    exit(0);
}

$pdo = $db->pdo();
$configStmt = $pdo->query("SELECT receive_address, trongrid_api_key FROM `{$db->table('config')}` WHERE id = 1 LIMIT 1");
$config = $configStmt->fetch() ?: [];
$address = trim((string) ($config['receive_address'] ?? ''));
$apiKey = trim((string) ($config['trongrid_api_key'] ?? '')) ?: null;

if ($address === '' || !TronAddress::isValid($address)) {
    Logger::write('cron', '收款地址未配置或格式无效，跳过本次抓取');
    exit(0);
}

$url = sprintf(
    'https://api.trongrid.io/v1/accounts/%s/transactions?limit=%d&only_confirmed=true&only_to=true&order_by=block_timestamp,desc',
    rawurlencode($address),
    FETCH_LIMIT
);

$result = tron_get($url, $apiKey);
if ($result === null) {
    Logger::write('cron', 'TronGrid 请求多次失败，本次抓取放弃');
    exit(1);
}

$transactions = $result['data'] ?? [];
$table = $db->table('transfer_records');
$insert = $pdo->prepare(
    "INSERT INTO `{$table}` (address, tx_hash, from_address, amount, tx_timestamp, fetched_at) VALUES (?, ?, ?, ?, ?, NOW())"
);

$inserted = 0;
$skipped = 0;

foreach ($transactions as $tx) {
    $txHash = (string) ($tx['txID'] ?? '');
    $contracts = $tx['raw_data']['contract'] ?? [];
    $blockTimestamp = (int) ($tx['block_timestamp'] ?? 0);

    if ($txHash === '' || empty($contracts) || $blockTimestamp <= 0) {
        continue;
    }

    foreach ($contracts as $contract) {
        if (($contract['type'] ?? '') !== 'TransferContract') {
            continue;
        }

        $value = $contract['parameter']['value'] ?? [];
        $amount = (int) ($value['amount'] ?? 0);
        $toHex = (string) ($value['to_address'] ?? '');
        $fromHex = (string) ($value['owner_address'] ?? '');

        if ($amount < MIN_AMOUNT_SUN) {
            continue;
        }

        $toAddress = TronAddress::hexToBase58($toHex);
        if ($toAddress === null || $toAddress !== $address) {
            continue;
        }

        $fromAddress = TronAddress::hexToBase58($fromHex) ?? $fromHex;
        $amountTrx = $amount / 1_000_000;
        $txTime = date('Y-m-d H:i:s', intdiv($blockTimestamp, 1000));

        try {
            $insert->execute([$address, $txHash, $fromAddress, $amountTrx, $txTime]);
            $inserted++;
        } catch (\PDOException $e) {
            // tx_hash 唯一索引冲突 = 已抓取过，直接跳过，无需提前查重
            if ((int) $e->getCode() === 23000) {
                $skipped++;
            } else {
                Logger::write('cron', "写入 transfer_records 失败：{$e->getMessage()}");
            }
        }
    }
}

Logger::write('cron', "抓取完成：新增 {$inserted} 条，跳过（已存在） {$skipped} 条，本次共 " . count($transactions) . ' 条交易');
exit(0);
