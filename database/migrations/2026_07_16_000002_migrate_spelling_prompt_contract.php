<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('exercises')
            ->where('type', 'spelling_quiz')
            ->orderBy('id')
            ->chunkById(100, function ($exercises): void {
                foreach ($exercises as $exercise) {
                    $content = json_decode($exercise->content, true);
                    if (! is_array($content)) {
                        continue;
                    }

                    $legacyValue = is_string($content['audio_url'] ?? null)
                        ? trim($content['audio_url'])
                        : '';
                    $fallback = is_string($content['correct_answer'] ?? null)
                        ? trim($content['correct_answer'])
                        : '';

                    if (! is_string($content['prompt_text'] ?? null) || trim($content['prompt_text']) === '') {
                        $content['prompt_text'] = $legacyValue !== '' && ! $this->looksLikeAnyAudioUrl($legacyValue)
                            ? $legacyValue
                            : $fallback;
                    }

                    if ($legacyValue === '' || ! $this->isApprovedLegacyAudioUrl($legacyValue)) {
                        unset($content['audio_url']);
                    }

                    DB::table('exercises')->where('id', $exercise->id)->update([
                        'content' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('exercises')
            ->where('type', 'spelling_quiz')
            ->orderBy('id')
            ->chunkById(100, function ($exercises): void {
                foreach ($exercises as $exercise) {
                    $content = json_decode($exercise->content, true);
                    if (! is_array($content)) {
                        continue;
                    }

                    if (! isset($content['audio_url']) && is_string($content['prompt_text'] ?? null)) {
                        $content['audio_url'] = $content['prompt_text'];
                    }
                    unset($content['prompt_text']);

                    DB::table('exercises')->where('id', $exercise->id)->update([
                        'content' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
    }

    private function looksLikeAnyAudioUrl(string $value): bool
    {
        return preg_match('~^(?:https://|/).+\.(?:mp3|wav)(?:[?#].*)?$~iD', $value) === 1;
    }

    private function isApprovedLegacyAudioUrl(string $value): bool
    {
        $decoded = str_replace('\\', '/', rawurldecode($value));

        return preg_match('~^/(?:audio|storage)/.+\.(?:mp3|wav)$~iD', $decoded) === 1
            && ! preg_match('~(^|/)\.\.(/|$)~', $decoded);
    }
};
