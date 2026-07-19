@php
    $richTextHtml = '';

    foreach ($runs ?? [] as $run) {
        // Escape source text before adding the fixed, allow-listed formatting tags.
        // Building one continuous string prevents Blade template indentation from
        // inserting spaces between adjacent DOCX runs (for example, a-MEN-i-tees).
        $fragment = e((string) ($run['text'] ?? ''));

        if ($run['underline'] ?? false) {
            $fragment = '<u>'.$fragment.'</u>';
        }

        if ($run['italic'] ?? false) {
            $fragment = '<em>'.$fragment.'</em>';
        }

        if ($run['bold'] ?? false) {
            $fragment = '<strong>'.$fragment.'</strong>';
        }

        $richTextHtml .= $fragment;
    }

    if ($richTextHtml === '') {
        $richTextHtml = e((string) ($text ?? ''));
    }
@endphp
{!! $richTextHtml !!}
