<?php

declare(strict_types=1);

/**
 * TRON 地址（Base58Check）格式校验，仅用于收款地址输入校验。
 * 依赖 bcmath 扩展进行大数运算。
 */
class TronAddress
{
    private const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    private const MAINNET_PREFIX = 0x41;

    public static function isValid(string $address): bool
    {
        $address = trim($address);
        if (!preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address)) {
            return false;
        }

        $bytes = self::base58Decode($address);
        if ($bytes === null || strlen($bytes) !== 25) {
            return false;
        }

        $payload = substr($bytes, 0, 21);
        $checksum = substr($bytes, 21, 4);

        if (ord($payload[0]) !== self::MAINNET_PREFIX) {
            return false;
        }

        $hash = hash('sha256', hash('sha256', $payload, true), true);

        return substr($hash, 0, 4) === $checksum;
    }

    /** 将 TronGrid 返回的 41 前缀十六进制地址转换为 Base58Check（T...）格式，供转账记录展示/存储使用 */
    public static function hexToBase58(string $hex): ?string
    {
        $hex = strtolower(trim($hex));
        if (!preg_match('/^41[0-9a-f]{40}$/', $hex)) {
            return null;
        }

        $bytes = hex2bin($hex);
        if ($bytes === false) {
            return null;
        }

        $checksum = substr(hash('sha256', hash('sha256', $bytes, true), true), 0, 4);
        $payload = $bytes . $checksum;

        return self::base58Encode($payload);
    }

    private static function base58Encode(string $bytes): string
    {
        $alphabet = self::ALPHABET;
        $num = '0';
        $len = strlen($bytes);

        for ($i = 0; $i < $len; $i++) {
            $num = bcadd(bcmul($num, '256'), (string) ord($bytes[$i]));
        }

        $result = '';
        while (bccomp($num, '0') > 0) {
            $rem = (int) bcmod($num, '58');
            $result = $alphabet[$rem] . $result;
            $num = bcdiv($num, '58', 0);
        }

        for ($i = 0; $i < $len && $bytes[$i] === "\x00"; $i++) {
            $result = '1' . $result;
        }

        return $result;
    }

    private static function base58Decode(string $input): ?string
    {
        $alphabet = self::ALPHABET;
        $base = strlen($alphabet);
        $num = '0';

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $charIndex = strpos($alphabet, $input[$i]);
            if ($charIndex === false) {
                return null;
            }
            $num = bcadd(bcmul($num, (string) $base), (string) $charIndex);
        }

        $bytes = '';
        while (bccomp($num, '0') > 0) {
            $mod = (int) bcmod($num, '256');
            $bytes = chr($mod) . $bytes;
            $num = bcdiv($num, '256', 0);
        }

        for ($i = 0, $len = strlen($input); $i < $len && $input[$i] === '1'; $i++) {
            $bytes = "\x00" . $bytes;
        }

        return $bytes;
    }
}
