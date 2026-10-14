<?php

declare(strict_types=1);

namespace Caiyun\Asr\Http;

/**
 * RFC 6455 数据帧。缓冲区还不够一帧时，tryDecode() 返回 null。
 */
class WebSocketFrame
{
    /**
     * 尝试从缓冲区解出一帧。
     *
     * @return array{fin: bool, opcode: int, payload: string, consumed: int}|null
     */
    public static function tryDecode(string $buffer): ?array
    {
        if (strlen($buffer) < 2) {
            return null;
        }

        $first = ord($buffer[0]);
        $second = ord($buffer[1]);
        $masked = ($second & 0x80) !== 0;
        $length = $second & 0x7F;
        $offset = 2;

        if ($length === 126) {
            if (strlen($buffer) < $offset + 2) {
                return null;
            }
            $unpacked = unpack('n', substr($buffer, $offset, 2));
            $length = is_array($unpacked) ? (int) $unpacked[1] : 0;
            $offset += 2;
        } elseif ($length === 127) {
            if (strlen($buffer) < $offset + 8) {
                return null;
            }
            $unpacked = unpack('N2', substr($buffer, $offset, 8));
            $length = is_array($unpacked) ? ((int) $unpacked[1] * 4294967296) + (int) $unpacked[2] : 0;
            $offset += 8;
        }

        $mask = '';
        if ($masked) {
            if (strlen($buffer) < $offset + 4) {
                return null;
            }
            $mask = substr($buffer, $offset, 4);
            $offset += 4;
        }

        if (strlen($buffer) < $offset + $length) {
            return null;
        }

        $payload = substr($buffer, $offset, $length);
        if ($masked) {
            $payload = self::applyMask($payload, $mask);
        }

        return [
            'fin' => ($first & 0x80) !== 0,
            'opcode' => $first & 0x0F,
            'payload' => $payload,
            'consumed' => $offset + $length,
        ];
    }

    /**
     * 编码一帧。客户端发往服务端的帧需要掩码，传入 $mask 可固定掩码以便测试。
     */
    public static function encode(string $payload, int $opcode = 0x1, bool $fin = true, bool $masked = true, ?string $mask = null): string
    {
        $header = chr(($fin ? 0x80 : 0x00) | ($opcode & 0x0F));
        $length = strlen($payload);
        $maskBit = $masked ? 0x80 : 0x00;

        if ($length < 126) {
            $header .= chr($maskBit | $length);
        } elseif ($length < 65536) {
            $header .= chr($maskBit | 126).pack('n', $length);
        } else {
            $header .= chr($maskBit | 127).pack('NN', intdiv($length, 4294967296), $length & 0xFFFFFFFF);
        }

        if ($masked) {
            $mask = $mask ?? random_bytes(4);
            if (strlen($mask) !== 4) {
                $mask = random_bytes(4);
            }
            $header .= $mask;
            $payload = self::applyMask($payload, $mask);
        }

        return $header.$payload;
    }

    private static function applyMask(string $payload, string $mask): string
    {
        $length = strlen($payload);
        $unmasked = '';
        for ($i = 0; $i < $length; $i++) {
            $unmasked .= $payload[$i] ^ $mask[$i % 4];
        }

        return $unmasked;
    }
}
