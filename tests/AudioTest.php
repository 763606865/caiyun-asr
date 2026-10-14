<?php

declare(strict_types=1);

namespace Caiyun\Asr\Tests;

use Caiyun\Asr\Audio;
use Caiyun\Asr\Exceptions\AsrException;
use Caiyun\Asr\Support\Wav;
use PHPUnit\Framework\TestCase;

class AudioTest extends TestCase
{
    public function test_wav_pcm_and_sample_rate(): void
    {
        $pcm = random_bytes(32);
        $wav = self::wav($pcm, 8000);

        $this->assertTrue(Wav::isWav($wav));
        $this->assertSame($pcm, Wav::pcm($wav));
        $this->assertSame(8000, Wav::sampleRate($wav));

        $audio = Audio::contents($wav);
        $this->assertSame('wav', $audio->format());
        $this->assertSame(8000, $audio->sampleRate());
        $this->assertFalse($audio->isRemote());
    }

    public function test_file_uses_extension(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'asr');
        $this->assertNotFalse($path);
        $target = $path.'.mp3';
        rename($path, $target);
        file_put_contents($target, 'mp3-bytes');

        try {
            $audio = Audio::file($target, ['sample_rate' => 16000]);
            $this->assertSame('mp3', $audio->format());
            $this->assertSame(16000, $audio->sampleRate());
            $this->assertSame('mp3-bytes', $audio->bytes());
        } finally {
            unlink($target);
        }
    }

    public function test_rejects_empty_and_missing_file(): void
    {
        $this->expectException(AsrException::class);
        Audio::contents('');
    }

    public function test_missing_file(): void
    {
        $this->expectException(AsrException::class);
        Audio::file('/tmp/caiyun-asr-not-exists.wav');
    }

    public static function wav(string $pcm, int $sampleRate): string
    {
        $dataSize = strlen($pcm);

        return 'RIFF'.pack('V', 36 + $dataSize).'WAVE'
            .'fmt '.pack('V', 16)
            .pack('v', 1)
            .pack('v', 1)
            .pack('V', $sampleRate)
            .pack('V', $sampleRate * 2)
            .pack('v', 2)
            .pack('v', 16)
            .'data'.pack('V', $dataSize)
            .$pcm;
    }
}
