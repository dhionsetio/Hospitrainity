<?php

namespace App\Services;

use Illuminate\Support\Collection;

final class HelpContentRegistry
{
    /** @return Collection<int, array> */
    public function topics(?string $locale = null): Collection
    {
        $locale = $this->locale($locale);

        return collect($this->content()[$locale]['topics'])->map(
            fn (array $topic, string $slug): array => $this->withMetadata($topic + ['slug' => $slug], $locale),
        )->values();
    }

    /** @return array<string, mixed>|null */
    public function topic(string $slug, ?string $locale = null): ?array
    {
        return $this->topics($locale)->firstWhere('slug', $slug);
    }

    /** @return Collection<int, array> */
    public function glossary(?string $locale = null): Collection
    {
        $locale = $this->locale($locale);

        return collect($this->content()[$locale]['glossary'])->map(
            fn (array $entry, string $slug): array => $this->withMetadata($entry + ['slug' => $slug], $locale),
        )->values();
    }

    /** @return array<string, mixed> */
    public function about(?string $locale = null): array
    {
        $locale = $this->locale($locale);

        return $this->withMetadata($this->content()[$locale]['about'], $locale);
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([
            'version' => config('help.version'),
            'updated_at' => config('help.updated_at'),
            'content' => $this->content(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function withMetadata(array $content, string $locale): array
    {
        $content['locale'] = $locale;
        $content['version'] = (string) config('help.version');
        $content['updated_at'] = (string) config('help.updated_at');
        $content['content_sha256'] = hash('sha256', json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $content;
    }

    private function locale(?string $locale): string
    {
        $locale ??= app()->getLocale();

        return in_array($locale, config('help.supported_locales', ['id', 'en']), true) ? $locale : 'en';
    }

    /** @return array<string, mixed> */
    private function content(): array
    {
        return [
            'en' => [
                'about' => [
                    'title' => 'About Hospitrainity',
                    'summary' => 'A practical overview for learners and educational institutions.',
                    'sections' => [
                        ['title' => 'Purpose', 'body' => ['Hospitrainity helps you practise English used in hospitality situations.', 'Learning materials include modules, lesson sections, vocabulary, and activities.']],
                        ['title' => 'Who it supports', 'body' => ['Learners can study independently or with an institution.', 'Institution staff can invite learners and review progress saved for their Classes.', 'Content Authors create shared learning materials, and System Admins manage the platform.']],
                        ['title' => 'Understanding progress', 'body' => ['Completion shows that you took part in an activity. It is not a grade or proof of mastery.', 'A confidence check records your own reflection, not a test score.']],
                    ],
                ],
                'topics' => [
                    'getting-started' => [
                        'title' => 'Getting started',
                        'summary' => 'Choose personal or institution learning, then open your first published module.',
                        'sections' => [
                            ['title' => 'Personal learning', 'body' => ['A verified learner account can study without an institution.', 'Only you can see personal learning progress.']],
                            ['title' => 'Learning with an institution', 'body' => ['Use a current classroom code from authorized institution staff and wait for approval.', 'The institution receives a new, separate progress view. Earlier personal progress is not copied into it.']],
                            ['title' => 'Your next step', 'body' => ['Use the dashboard primary action to resume started work or open the next incomplete published module.']],
                        ],
                    ],
                    'invitations-and-codes' => [
                        'title' => 'Invitations and classroom codes',
                        'summary' => 'How institution staff invite learners and how time-limited classroom codes work.',
                        'sections' => [
                            ['title' => 'Invitation', 'body' => ['An invitation targets one email address and one institution.', 'Use only a link that was sent for your address; expired, revoked, or used links cannot be redeemed again.']],
                            ['title' => 'Classroom code', 'body' => ['Authorized institution staff choose the lifetime when creating a code: seconds, minutes, hours, or days, up to 30 days.', 'A code may be used by multiple learners while it remains active. Approval is still required before institution tracking begins.']],
                            ['title' => 'Your progress', 'body' => ['Joining does not transfer earlier personal progress. Your institution starts with a separate progress record.']],
                        ],
                    ],
                    'learning-and-progress' => [
                        'title' => 'Learning and progress',
                        'summary' => 'What modules, activities, completion, and confidence history mean.',
                        'sections' => [
                            ['title' => 'Available learning', 'body' => ['Your learning dashboard shows the modules available to you.']],
                            ['title' => 'Completion', 'body' => ['Completion means you finished the required activity steps.', 'It is not automatically a score, grade, or mastery judgment.']],
                            ['title' => 'Confidence check', 'body' => ['Confidence ratings are optional self-reflection. They are not a proficiency diagnostic and can be explicitly skipped where offered.']],
                        ],
                    ],
                    'account-and-recovery' => [
                        'title' => 'Account access and recovery',
                        'summary' => 'Password reset, multi-factor recovery, security settings, and institution support.',
                        'sections' => [
                            ['title' => 'Password', 'body' => ['Use the password-reset link on the sign-in page if you can access your registered email.', 'Hospitrainity never asks you to send a password or recovery code to another person.']],
                            ['title' => 'Authenticator recovery', 'body' => ['Use a saved one-time recovery code if your authenticator is unavailable.', 'Ask a System Admin for help if you still cannot sign in.']],
                            ['title' => 'Institution help', 'body' => ['Ask your Instructor or Institution Admin about invitations, classroom codes, membership approval, or Class progress.']],
                        ],
                    ],
                    'institution-staff' => [
                        'title' => 'For institution staff',
                        'summary' => 'Invite learners, create classroom codes, approve membership requests, and review attributed progress.',
                        'sections' => [
                            ['title' => 'Bring learners in', 'body' => ['Use Invitations for one addressed learner or Classroom codes for a time-limited group entry path.', 'Review pending membership requests before institution tracking starts.']],
                            ['title' => 'Review progress', 'body' => ['The team dashboard shows learners from the institution you selected.', 'Current progress represents activity completion, not grading or mastery.']],
                        ],
                    ],
                    'content-and-evidence' => [
                        'title' => 'Managing learning content',
                        'summary' => 'Create, preview, and review learning content.',
                        'sections' => [
                            ['title' => 'Learner view', 'body' => ['Learners see available modules, lesson titles, activities, and progress actions.']],
                            ['title' => 'Content workspace', 'body' => ['Content Authors create and preview changes before submitting them for review.', 'Published content stays unchanged until an authorized reviewer approves an update.']],
                            ['title' => 'Older content', 'body' => ['Older content records remain read-only and do not change what learners currently see.']],
                        ],
                    ],
                    'accessibility-and-display' => [
                        'title' => 'Accessibility and display',
                        'summary' => 'Keyboard access, browser zoom, display preferences, motion, contrast, and alternatives.',
                        'sections' => [
                            ['title' => 'Always available', 'body' => ['Keyboard operation, semantic reading order, error recovery, and browser zoom do not depend on an accessibility mode.']],
                            ['title' => 'Display preferences', 'body' => ['Signed-in users can choose system/light/dark theme, motion, text size, stronger contrast, and a no-audio preference.', 'The no-audio preference records a need for alternatives; it does not remove required information.']],
                        ],
                    ],
                ],
                'glossary' => [
                    'learner' => ['term' => 'Learner', 'definition' => 'A person using personal learning or an approved institution learning context.'],
                    'institution-supervisor' => ['term' => 'Institution Supervisor', 'definition' => 'Institution-scoped staff who can invite learners and review institution-attributed participation progress.'],
                    'content-admin' => ['term' => 'Content Admin', 'definition' => 'A person with the separate capability to author and review shared curriculum content.'],
                    'system-admin' => ['term' => 'System Admin', 'definition' => 'A platform-wide role that manages Hospitrainity settings and accounts. Institutions do not grant this role.'],
                    'learning-context' => ['term' => 'Learning choice', 'definition' => 'The personal account, institution, or Class where new progress is saved.'],
                    'classroom-code' => ['term' => 'Classroom code', 'definition' => 'A reusable, time-limited code created by authorized institution staff to request institution membership.'],
                    'completion' => ['term' => 'Completion', 'definition' => 'A record that you finished the required activity steps. It is not automatically a score or mastery judgment.'],
                    'confidence-check' => ['term' => 'Confidence check', 'definition' => 'Optional self-reflection on confidence, not a proficiency diagnostic.'],
                    'canonical-content' => ['term' => 'Published content', 'definition' => 'The learning content currently available to learners.'],
                    'legacy-evidence' => ['term' => 'Older content', 'definition' => 'Read-only content records that do not change what learners currently see.'],
                ],
            ],
            'id' => [
                'about' => [
                    'title' => 'Tentang Hospitrainity',
                    'summary' => 'Ringkasan praktis untuk pelajar dan institusi pendidikan.',
                    'sections' => [
                        ['title' => 'Tujuan', 'body' => ['Hospitrainity membantu Anda berlatih bahasa Inggris dalam situasi perhotelan.', 'Materi pembelajaran mencakup modul, bagian pelajaran, kosakata, dan aktivitas.']],
                        ['title' => 'Pengguna yang didukung', 'body' => ['Pelajar dapat belajar mandiri atau bersama institusi.', 'Staf institusi dapat mengundang pelajar dan meninjau progres yang disimpan untuk Kelas mereka.', 'Penulis Konten membuat materi pembelajaran bersama, dan Admin Sistem mengelola platform.']],
                        ['title' => 'Memahami progres', 'body' => ['Penyelesaian menunjukkan bahwa Anda mengikuti suatu aktivitas. Ini bukan nilai atau bukti penguasaan.', 'Pemeriksaan keyakinan mencatat refleksi Anda sendiri, bukan skor tes.']],
                    ],
                ],
                'topics' => [
                    'getting-started' => [
                        'title' => 'Memulai',
                        'summary' => 'Pilih pembelajaran pribadi atau institusi, lalu buka modul terbit pertama.',
                        'sections' => [
                            ['title' => 'Pembelajaran pribadi', 'body' => ['Akun pelajar terverifikasi dapat belajar tanpa institusi.', 'Hanya Anda yang dapat melihat progres belajar pribadi.']],
                            ['title' => 'Belajar bersama institusi', 'body' => ['Gunakan kode kelas aktif dari staf institusi yang berwenang lalu tunggu persetujuan.', 'Institusi menerima tampilan progres baru yang terpisah. Progres pribadi sebelumnya tidak disalin ke dalamnya.']],
                            ['title' => 'Langkah berikutnya', 'body' => ['Gunakan tindakan utama di dasbor untuk melanjutkan pekerjaan yang sudah dimulai atau membuka modul terbit berikutnya yang belum selesai.']],
                        ],
                    ],
                    'invitations-and-codes' => [
                        'title' => 'Undangan dan kode kelas',
                        'summary' => 'Cara staf institusi mengundang pelajar dan cara kerja kode kelas berbatas waktu.',
                        'sections' => [
                            ['title' => 'Undangan', 'body' => ['Undangan ditujukan kepada satu alamat email dan satu institusi.', 'Gunakan hanya tautan yang dikirim untuk alamat Anda; tautan kedaluwarsa, dicabut, atau sudah digunakan tidak dapat ditebus lagi.']],
                            ['title' => 'Kode kelas', 'body' => ['Staf institusi yang berwenang memilih masa berlaku saat membuat kode: detik, menit, jam, atau hari, hingga 30 hari.', 'Kode dapat dipakai oleh beberapa pelajar selama masih aktif. Persetujuan tetap diperlukan sebelum pelacakan institusi dimulai.']],
                            ['title' => 'Progres Anda', 'body' => ['Bergabung tidak memindahkan progres pribadi sebelumnya. Institusi Anda memulai dengan catatan progres yang terpisah.']],
                        ],
                    ],
                    'learning-and-progress' => [
                        'title' => 'Pembelajaran dan progres',
                        'summary' => 'Arti modul, aktivitas, penyelesaian, dan riwayat keyakinan.',
                        'sections' => [
                            ['title' => 'Pembelajaran yang tersedia', 'body' => ['Dasbor pembelajaran menampilkan modul yang tersedia untuk Anda.']],
                            ['title' => 'Penyelesaian', 'body' => ['Penyelesaian berarti Anda menuntaskan langkah aktivitas yang diwajibkan.', 'Ini tidak otomatis menjadi skor, nilai, atau penilaian penguasaan.']],
                            ['title' => 'Pemeriksaan keyakinan', 'body' => ['Penilaian keyakinan adalah refleksi diri opsional. Ini bukan diagnosis kemahiran dan dapat dilewati secara eksplisit jika tersedia.']],
                        ],
                    ],
                    'account-and-recovery' => [
                        'title' => 'Akses dan pemulihan akun',
                        'summary' => 'Atur ulang kata sandi, pemulihan multifaktor, pengaturan keamanan, dan dukungan institusi.',
                        'sections' => [
                            ['title' => 'Kata sandi', 'body' => ['Gunakan tautan atur ulang kata sandi di halaman masuk jika Anda dapat mengakses email terdaftar.', 'Hospitrainity tidak pernah meminta Anda mengirim kata sandi atau kode pemulihan kepada orang lain.']],
                            ['title' => 'Pemulihan autentikator', 'body' => ['Gunakan kode pemulihan sekali pakai yang tersimpan jika autentikator tidak tersedia.', 'Minta bantuan Admin Sistem jika Anda tetap tidak dapat masuk.']],
                            ['title' => 'Bantuan institusi', 'body' => ['Tanyakan kepada Instruktur atau Admin Institusi tentang undangan, kode kelas, persetujuan keanggotaan, atau progres Kelas.']],
                        ],
                    ],
                    'institution-staff' => [
                        'title' => 'Untuk staf institusi',
                        'summary' => 'Undang pelajar, buat kode kelas, setujui permintaan keanggotaan, dan tinjau progres terkait.',
                        'sections' => [
                            ['title' => 'Memasukkan pelajar', 'body' => ['Gunakan Undangan untuk satu pelajar tertentu atau Kode kelas untuk jalur masuk kelompok berbatas waktu.', 'Tinjau permintaan keanggotaan yang tertunda sebelum pelacakan institusi dimulai.']],
                            ['title' => 'Meninjau progres', 'body' => ['Dasbor tim menampilkan pelajar dari institusi yang Anda pilih.', 'Progres saat ini menunjukkan penyelesaian aktivitas, bukan penilaian atau penguasaan.']],
                        ],
                    ],
                    'content-and-evidence' => [
                        'title' => 'Mengelola materi pembelajaran',
                        'summary' => 'Buat, pratinjau, dan tinjau materi pembelajaran.',
                        'sections' => [
                            ['title' => 'Tampilan pelajar', 'body' => ['Pelajar melihat modul, judul pelajaran, aktivitas, dan tindakan progres yang tersedia.']],
                            ['title' => 'Ruang kerja konten', 'body' => ['Penulis Konten membuat dan melakukan pratinjau perubahan sebelum mengirimkannya untuk ditinjau.', 'Konten terbit tetap sama sampai peninjau berwenang menyetujui pembaruan.']],
                            ['title' => 'Konten lama', 'body' => ['Catatan konten lama tetap hanya-baca dan tidak mengubah materi yang saat ini dilihat pelajar.']],
                        ],
                    ],
                    'accessibility-and-display' => [
                        'title' => 'Aksesibilitas dan tampilan',
                        'summary' => 'Akses papan ketik, zoom peramban, preferensi tampilan, gerakan, kontras, dan alternatif.',
                        'sections' => [
                            ['title' => 'Selalu tersedia', 'body' => ['Pengoperasian papan ketik, urutan baca semantik, pemulihan kesalahan, dan zoom peramban tidak bergantung pada mode aksesibilitas.']],
                            ['title' => 'Preferensi tampilan', 'body' => ['Pengguna yang masuk dapat memilih tema sistem/terang/gelap, gerakan, ukuran teks, kontras lebih kuat, dan preferensi tanpa audio.', 'Preferensi tanpa audio mencatat kebutuhan alternatif; pilihan ini tidak menghapus informasi wajib.']],
                        ],
                    ],
                ],
                'glossary' => [
                    'learner' => ['term' => 'Pelajar', 'definition' => 'Orang yang menggunakan pembelajaran pribadi atau konteks pembelajaran institusi yang disetujui.'],
                    'institution-supervisor' => ['term' => 'Supervisor Institusi', 'definition' => 'Staf dalam lingkup institusi yang dapat mengundang pelajar dan meninjau progres partisipasi terkait institusi.'],
                    'content-admin' => ['term' => 'Admin Konten', 'definition' => 'Orang dengan kapabilitas terpisah untuk menyusun dan meninjau konten kurikulum bersama.'],
                    'system-admin' => ['term' => 'Admin Sistem', 'definition' => 'Peran tingkat platform yang mengelola pengaturan dan akun Hospitrainity. Institusi tidak memberikan peran ini.'],
                    'learning-context' => ['term' => 'Pilihan belajar', 'definition' => 'Akun pribadi, institusi, atau Kelas tempat progres baru disimpan.'],
                    'classroom-code' => ['term' => 'Kode kelas', 'definition' => 'Kode pakai-ulang berbatas waktu yang dibuat staf institusi berwenang untuk meminta keanggotaan institusi.'],
                    'completion' => ['term' => 'Penyelesaian', 'definition' => 'Catatan bahwa Anda telah menuntaskan langkah aktivitas yang diwajibkan. Ini tidak otomatis menjadi skor atau penilaian penguasaan.'],
                    'confidence-check' => ['term' => 'Pemeriksaan keyakinan', 'definition' => 'Refleksi diri opsional tentang keyakinan, bukan diagnosis kemahiran.'],
                    'canonical-content' => ['term' => 'Konten terbit', 'definition' => 'Materi pembelajaran yang saat ini tersedia untuk pelajar.'],
                    'legacy-evidence' => ['term' => 'Konten lama', 'definition' => 'Catatan konten hanya-baca yang tidak mengubah materi yang saat ini dilihat pelajar.'],
                ],
            ],
        ];
    }
}
