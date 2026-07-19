<?php

namespace App\Services\Curriculum;

use App\Enums\CurriculumAssetKind;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

final class CurriculumUploadInspector
{
    /** @return array{original_name:string,sha256:string,bytes:int,mime:string,extension:string} */
    public function docx(UploadedFile $file): array
    {
        $this->assertReadableSize($file, (int) config('curriculum.import.docx_max_kib'));
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'docx') {
            throw new RuntimeException('The source must use the .docx extension.');
        }
        $path = $file->getRealPath();
        $mime = $this->mime($path);
        if (! in_array($mime, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ], true)) {
            throw new RuntimeException('The uploaded source content is not a DOCX/OOXML document.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The DOCX package cannot be opened.');
        }
        try {
            $maxEntries = (int) config('curriculum.import.docx_max_entries');
            if ($zip->numFiles < 3 || $zip->numFiles > $maxEntries) {
                throw new RuntimeException("The DOCX package must contain between 3 and {$maxEntries} entries.");
            }
            $total = 0;
            $required = ['[Content_Types].xml' => false, '_rels/.rels' => false, 'word/document.xml' => false];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if (! is_array($stat) || ! is_string($stat['name'] ?? null)) {
                    throw new RuntimeException('The DOCX package contains an unreadable entry.');
                }
                $name = str_replace('\\', '/', $stat['name']);
                $segments = explode('/', $name);
                if (str_contains($name, "\0") || str_starts_with($name, '/')
                    || preg_match('/^[A-Za-z]:\//', $name) === 1 || in_array('..', $segments, true)) {
                    throw new RuntimeException('The DOCX package contains an unsafe entry path.');
                }
                if ((int) ($stat['encryption_method'] ?? 0) !== 0) {
                    throw new RuntimeException('Encrypted DOCX package entries are not supported.');
                }
                $size = (int) ($stat['size'] ?? 0);
                if ($size > (int) config('curriculum.import.docx_max_entry_bytes')) {
                    throw new RuntimeException('A DOCX package entry exceeds the safe expansion limit.');
                }
                $total += $size;
                if ($total > (int) config('curriculum.import.docx_max_uncompressed_bytes')) {
                    throw new RuntimeException('The DOCX package exceeds the safe total expansion limit.');
                }
                if (array_key_exists($name, $required)) {
                    $required[$name] = true;
                }
                if (strcasecmp($name, 'word/vbaProject.bin') === 0) {
                    throw new RuntimeException('Macro-enabled Word content is not accepted.');
                }
            }
            if (in_array(false, $required, true)) {
                throw new RuntimeException('The DOCX package is missing required OOXML parts.');
            }
            $types = $zip->getFromName('[Content_Types].xml');
            if (! is_string($types)
                || ! str_contains($types, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml')
                || str_contains(strtolower($types), 'macroenabled')) {
                throw new RuntimeException('The OOXML content declaration is not a macro-free Word document.');
            }
        } finally {
            $zip->close();
        }

        return $this->metadata($file, $mime, 'docx');
    }

    /** @return array{original_name:string,sha256:string,bytes:int,mime:string,extension:string,kind:CurriculumAssetKind} */
    public function asset(UploadedFile $file): array
    {
        $this->assertReadableSize($file, (int) config('curriculum.import.asset_max_kib'));
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();
        $mime = $this->mime($path);
        $allowed = [
            'jpg' => [CurriculumAssetKind::Image, ['image/jpeg']],
            'jpeg' => [CurriculumAssetKind::Image, ['image/jpeg']],
            'png' => [CurriculumAssetKind::Image, ['image/png']],
            'webp' => [CurriculumAssetKind::Image, ['image/webp']],
            'mp3' => [CurriculumAssetKind::Audio, ['audio/mpeg', 'audio/mp3', 'audio/x-mpeg']],
            'wav' => [CurriculumAssetKind::Audio, ['audio/wav', 'audio/x-wav', 'audio/vnd.wave']],
        ];
        if (! isset($allowed[$extension]) || ! in_array($mime, $allowed[$extension][1], true)) {
            throw new RuntimeException('The asset extension and detected content type do not match an approved image or audio format.');
        }
        $kind = $allowed[$extension][0];
        if ($kind === CurriculumAssetKind::Image) {
            $image = @getimagesize($path);
            if (! is_array($image) || ($image['mime'] ?? null) !== $mime) {
                throw new RuntimeException('The uploaded image cannot be decoded as its declared format.');
            }
        } else {
            $header = file_get_contents($path, false, null, 0, 12);
            if ($extension === 'wav' && (! is_string($header) || substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WAVE')) {
                throw new RuntimeException('The uploaded WAV file has an invalid RIFF/WAVE signature.');
            }
            if ($extension === 'mp3' && (! is_string($header)
                || (substr($header, 0, 3) !== 'ID3' && ! (isset($header[1]) && ord($header[0]) === 0xFF && (ord($header[1]) & 0xE0) === 0xE0)))) {
                throw new RuntimeException('The uploaded MP3 file has an invalid ID3/frame signature.');
            }
        }

        $storageExtension = $extension === 'jpeg' ? 'jpg' : $extension;

        return $this->metadata($file, $mime, $storageExtension) + ['kind' => $kind];
    }

    private function assertReadableSize(UploadedFile $file, int $maxKib): void
    {
        if (! $file->isValid() || ! is_file($file->getRealPath())) {
            throw new RuntimeException('The upload did not complete successfully.');
        }
        $bytes = $file->getSize();
        if (! is_int($bytes) || $bytes < 1 || $bytes > $maxKib * 1024) {
            throw new RuntimeException("The upload must be non-empty and no larger than {$maxKib} KiB.");
        }
    }

    private function mime(string $path): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! is_string($mime) || $mime === '') {
            throw new RuntimeException('The upload content type cannot be determined.');
        }

        return strtolower($mime);
    }

    /** @return array{original_name:string,sha256:string,bytes:int,mime:string,extension:string} */
    private function metadata(UploadedFile $file, string $mime, string $extension): array
    {
        $path = $file->getRealPath();
        $hash = hash_file('sha256', $path);
        $bytes = $file->getSize();
        if (! is_string($hash) || ! is_int($bytes)) {
            throw new RuntimeException('The upload integrity metadata cannot be calculated.');
        }
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '');

        return [
            'original_name' => mb_substr($name !== '' ? $name : "upload.{$extension}", 0, 255),
            'sha256' => $hash,
            'bytes' => $bytes,
            'mime' => $mime,
            'extension' => $extension,
        ];
    }
}
