<?php

namespace App\Services\Reflections;

use Illuminate\Http\UploadedFile;
use RuntimeException;

final class ReflectionMediaInspector
{
    /**
     * Inspect an uploaded reflection audio/video file and verify its magic-byte signature.
     *
     * @return array{original_name: string, sha256: string, bytes: int, mime: string, extension: string, kind: string}
     */
    public function inspect(UploadedFile $file, ?string $declaredKind = null): array
    {
        $maxKib = (int) config('learning_reflection.max_attachment_kib', 51200);
        $this->assertReadableSize($file, $maxKib);

        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();
        $mime = $this->mime($path);

        /** @var array<string, list<string>> $allowedMap */
        $allowedMap = config('learning_reflection.allowed', []);

        if (! isset($allowedMap[$extension]) || ! in_array($mime, $allowedMap[$extension], true)) {
            throw new RuntimeException('The reflection media extension and detected content type do not match an approved audio or video format.');
        }

        $header = file_get_contents($path, false, null, 0, 16);
        if (! is_string($header) || strlen($header) < 4) {
            throw new RuntimeException('The uploaded file content cannot be read for signature verification.');
        }

        $this->verifyMagicBytes($extension, $header);

        $kind = $declaredKind;
        if (! in_array($kind, ['voice_recording', 'audio_file', 'video_file'], true)) {
            $kind = str_starts_with($mime, 'video/') ? 'video_file' : 'audio_file';
        }

        return $this->metadata($file, $mime, $extension) + ['kind' => $kind];
    }

    private function verifyMagicBytes(string $extension, string $header): void
    {
        switch ($extension) {
            case 'wav':
                if (strlen($header) < 12 || substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WAVE') {
                    throw new RuntimeException('The uploaded WAV file has an invalid RIFF/WAVE signature.');
                }
                break;

            case 'mp3':
                if (substr($header, 0, 3) !== 'ID3' && ! (isset($header[1]) && ord($header[0]) === 0xFF && (ord($header[1]) & 0xE0) === 0xE0)) {
                    throw new RuntimeException('The uploaded MP3 file has an invalid ID3/frame signature.');
                }
                break;

            case 'webm':
                if (substr($header, 0, 4) !== "\x1A\x45\xDF\xA3") {
                    throw new RuntimeException('The uploaded WebM file has an invalid EBML header signature.');
                }
                break;

            case 'ogg':
            case 'oga':
                if (substr($header, 0, 4) !== 'OggS') {
                    throw new RuntimeException('The uploaded Ogg file has an invalid OggS signature.');
                }
                break;

            case 'mp4':
            case 'm4a':
            case 'mp4a':
                if (strlen($header) < 8 || substr($header, 4, 4) !== 'ftyp') {
                    throw new RuntimeException('The uploaded MP4 file has an invalid ISO Base Media ftyp signature.');
                }
                break;

            default:
                throw new RuntimeException('Unsupported file format extension.');
        }
    }

    private function assertReadableSize(UploadedFile $file, int $maxKib): void
    {
        if (! $file->isValid() || ! is_file($file->getRealPath())) {
            throw new RuntimeException('The media upload did not complete successfully.');
        }
        $bytes = $file->getSize();
        if (! is_int($bytes) || $bytes < 1 || $bytes > $maxKib * 1024) {
            throw new RuntimeException("The uploaded media must be non-empty and no larger than {$maxKib} KiB.");
        }
    }

    private function mime(string $path): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! is_string($mime) || $mime === '') {
            throw new RuntimeException('The media content type cannot be determined.');
        }

        return strtolower($mime);
    }

    /** @return array{original_name: string, sha256: string, bytes: int, mime: string, extension: string} */
    private function metadata(UploadedFile $file, string $mime, string $extension): array
    {
        $path = $file->getRealPath();
        $hash = hash_file('sha256', $path);
        $bytes = $file->getSize();
        if (! is_string($hash) || ! is_int($bytes)) {
            throw new RuntimeException('The media integrity hash cannot be calculated.');
        }
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '');

        return [
            'original_name' => mb_substr($name !== '' ? $name : "recording.{$extension}", 0, 255),
            'sha256' => $hash,
            'bytes' => $bytes,
            'mime' => $mime,
            'extension' => $extension,
        ];
    }
}
