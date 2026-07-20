<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class DataExportBuilder
{
    /**
     * @return array{bytes: string, sha256: string, size: int}
     */
    public function build(User $user): array
    {
        $profile = [
            'schema_version' => '1.0.0',
            'generated_at' => now()->toIso8601String(),
            'account' => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'disabled_at' => $user->disabled_at?->toIso8601String(),
            ],
            'exclusions' => [
                'password and remember hashes',
                'raw invitation and classroom-code tokens',
                'security logs about other people',
                'internal risk and anti-abuse signals',
                'other users and institution-private records',
            ],
        ];

        $memberships = DB::table('institution_memberships')
            ->join('institutions', 'institutions.id', '=', 'institution_memberships.institution_id')
            ->where('institution_memberships.user_id', $user->getKey())
            ->orderBy('institution_memberships.created_at')
            ->get([
                'institution_memberships.id', 'institutions.name_id as institution_name_id',
                'institutions.name_en as institution_name_en',
                'institution_memberships.status', 'institution_memberships.provenance',
                'institution_memberships.joined_at', 'institution_memberships.revoked_at',
            ])->map(fn ($row): array => (array) $row)->all();

        $progress = DB::table('curriculum_activity_progress')
            ->where('user_id', $user->getKey())
            ->orderBy('created_at')
            ->get([
                'learning_scope_key', 'package_name', 'content_version', 'activity_code', 'section_code',
                'viewed_at', 'started_at', 'attempted_at', 'self_checked_at', 'completed_at', 'baseline_skipped_at',
            ])->map(fn ($row): array => (array) $row)->all();

        $attempts = DB::table('curriculum_attempts')
            ->where('user_id', $user->getKey())
            ->orderBy('created_at')
            ->get([
                'id', 'learning_scope_key', 'package_name', 'content_version', 'activity_code',
                'intent', 'state', 'completion_reason', 'started_at', 'attempted_at', 'self_checked_at', 'completed_at',
            ])->map(fn ($row): array => (array) $row)->all();

        $acknowledgements = DB::table('policy_acknowledgements')
            ->where('user_id', $user->getKey())
            ->orderBy('acknowledged_at')
            ->get(['policy_type', 'locale', 'version', 'content_sha256', 'acknowledged_at', 'source'])
            ->map(fn ($row): array => (array) $row)->all();

        $temporary = tempnam(sys_get_temp_dir(), 'hospitrainity-export-');
        if ($temporary === false) {
            throw new RuntimeException('export_temp_unavailable');
        }

        try {
            $zip = new ZipArchive;
            if ($zip->open($temporary, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('export_archive_unavailable');
            }
            $zip->addFromString('profile.json', json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $zip->addFromString('summary.html', $this->html($profile, $memberships, $progress, $attempts, $acknowledgements));
            $zip->addFromString('memberships.csv', $this->csv($memberships));
            $zip->addFromString('progress.csv', $this->csv($progress));
            $zip->addFromString('attempts.csv', $this->csv($attempts));
            $zip->addFromString('policy-acknowledgements.csv', $this->csv($acknowledgements));
            $zip->close();

            $bytes = file_get_contents($temporary);
            if ($bytes === false) {
                throw new RuntimeException('export_read_failed');
            }

            return ['bytes' => $bytes, 'sha256' => hash('sha256', $bytes), 'size' => strlen($bytes)];
        } finally {
            @unlink($temporary);
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('export_csv_stream_failed');
        }

        $headers = $rows === [] ? [] : array_keys($rows[0]);
        if ($headers !== []) {
            fputcsv($stream, $headers, escape: '');
        }
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn (mixed $value): string => $this->safeCell($value), $row), escape: '');
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return $contents === false ? '' : $contents;
    }

    private function safeCell(mixed $value): string
    {
        $cell = is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR);
        if (preg_match('/^[=+\-@\t\r]/u', $cell) === 1) {
            return "'".$cell;
        }

        return $cell;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  list<array<string, mixed>>  ...$datasets
     */
    private function html(array $profile, array ...$datasets): string
    {
        [$memberships, $progress, $attempts, $acknowledgements] = $datasets;
        $account = $profile['account'];

        $body = '<h1>Hospitrainity personal data export</h1>';
        $body .= '<p>Generated at <time datetime="'.$this->escape($profile['generated_at']).'">'.$this->escape($profile['generated_at']).'</time>.</p>';
        $body .= $this->htmlTable('Account profile', [$account]);
        $body .= '<section aria-labelledby="export-exclusions"><h2 id="export-exclusions">Deliberately excluded</h2><ul>';
        foreach ($profile['exclusions'] as $exclusion) {
            $body .= '<li>'.$this->escape($exclusion).'</li>';
        }
        $body .= '</ul></section>';
        $body .= $this->htmlTable('Institution memberships', $memberships);
        $body .= $this->htmlTable('Learning progress', $progress);
        $body .= $this->htmlTable('Learning attempts', $attempts);
        $body .= $this->htmlTable('Policy acknowledgements', $acknowledgements);

        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Hospitrainity personal data export</title>'
            .'<style>body{font:1rem/1.5 system-ui,sans-serif;max-width:80rem;margin:auto;padding:2rem;color:#171717}table{border-collapse:collapse;width:100%;margin-block:1rem 2rem}caption{text-align:left;font-size:1.5rem;font-weight:700}th,td{border:1px solid #777;padding:.5rem;text-align:left;vertical-align:top}thead{background:#eee}.table-wrap{overflow-x:auto}</style>'
            .'</head><body>'.$body.'</body></html>';
    }

    /** @param list<array<string, mixed>> $rows */
    private function htmlTable(string $caption, array $rows): string
    {
        $id = 'export-'.strtolower(preg_replace('/[^a-z0-9]+/i', '-', $caption) ?? 'data');
        if ($rows === []) {
            return '<section aria-labelledby="'.$id.'"><h2 id="'.$id.'">'.$this->escape($caption).'</h2><p>No records.</p></section>';
        }

        $headers = array_keys($rows[0]);
        $html = '<div class="table-wrap" role="region" aria-labelledby="'.$id.'" tabindex="0"><table><caption id="'.$id.'">'.$this->escape($caption).'</caption><thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th scope="col">'.$this->escape(str_replace('_', ' ', $header)).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($headers as $header) {
                $value = $row[$header] ?? null;
                $cell = is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR);
                $html .= '<td>'.$this->escape($cell).'</td>';
            }
            $html .= '</tr>';
        }

        return $html.'</tbody></table></div>';
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
