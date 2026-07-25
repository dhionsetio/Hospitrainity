<?php

namespace Tests\Unit;

use App\Services\Reflections\ReflectionMediaInspector;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\TestCase;

class ReflectionMediaInspectorTest extends TestCase
{
    private ReflectionMediaInspector $inspector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inspector = new ReflectionMediaInspector;
    }

    public function test_inspects_valid_wav_file(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_wav');
        file_put_contents($tmp, "RIFF\x24\x00\x00\x00WAVEfmt \x10\x00\x00\x00\x01\x00\x01\x00\x44\xAC\x00\x00");

        $file = new UploadedFile($tmp, 'sample.wav', 'audio/wav', null, true);

        try {
            $metadata = $this->inspector->inspect($file, 'voice_recording');

            $this->assertSame('sample.wav', $metadata['original_name']);
            $this->assertSame('wav', $metadata['extension']);
            $this->assertSame('voice_recording', $metadata['kind']);
            $this->assertNotEmpty($metadata['sha256']);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_inspects_valid_webm_file(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_webm');
        file_put_contents($tmp, "\x1A\x45\xDF\xA3\x01\x00\x00\x00\x00\x00\x00\x1F\x42\x86\x81\x01\x42\xF7\x81\x01\x42\xF2\x81\x04\x42\xF3\x81\x08\x42\x82\x84webm");

        $file = new UploadedFile($tmp, 'clip.webm', 'audio/webm', null, true);

        try {
            $metadata = $this->inspector->inspect($file, 'audio_file');

            $this->assertSame('clip.webm', $metadata['original_name']);
            $this->assertSame('webm', $metadata['extension']);
            $this->assertSame('audio_file', $metadata['kind']);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_inspects_valid_mp3_file(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_mp3');
        file_put_contents($tmp, "ID3\x04\x00\x00\x00\x00\x00\x00\xFF\xFB\x90\x44");

        $file = new UploadedFile($tmp, 'track.mp3', 'audio/mpeg', null, true);

        try {
            $metadata = $this->inspector->inspect($file, 'audio_file');

            $this->assertSame('track.mp3', $metadata['original_name']);
            $this->assertSame('mp3', $metadata['extension']);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_inspects_valid_ogg_file(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_ogg');
        file_put_contents($tmp, "OggS\x00\x02\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x01\x13\x00\x00\x00\x00OpusHead\x01\x01\x00\x00\x80\xbb\x00\x00\x00\x00\x00");

        $file = new UploadedFile($tmp, 'sound.ogg', 'audio/ogg', null, true);

        try {
            $metadata = $this->inspector->inspect($file, 'audio_file');

            $this->assertSame('sound.ogg', $metadata['original_name']);
            $this->assertSame('ogg', $metadata['extension']);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_inspects_valid_mp4_file(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_mp4');
        file_put_contents($tmp, "\x00\x00\x00\x1Cftypisom\x00\x00\x02\x00isomiso2mp41");

        $file = new UploadedFile($tmp, 'video.mp4', 'video/mp4', null, true);

        try {
            $metadata = $this->inspector->inspect($file, 'media_attachment');

            $this->assertSame('video.mp4', $metadata['original_name']);
            $this->assertSame('mp4', $metadata['extension']);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_rejects_invalid_magic_bytes(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_bad');
        file_put_contents($tmp, "RIFF\x24\x00\x00\x00XXXXfmt \x10\x00\x00\x00\x01\x00\x01\x00\x44\xAC\x00\x00");

        $file = new UploadedFile($tmp, 'fake.wav', 'audio/wav', null, true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The reflection media extension and detected content type do not match');

        try {
            $this->inspector->inspect($file);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_rejects_spoofed_plain_text_as_wav(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_txt');
        file_put_contents($tmp, "This is plain text pretending to be audio");

        $file = new UploadedFile($tmp, 'malicious.wav', 'audio/wav', null, true);

        $this->expectException(RuntimeException::class);

        try {
            $this->inspector->inspect($file);
        } finally {
            @unlink($tmp);
        }
    }
}
