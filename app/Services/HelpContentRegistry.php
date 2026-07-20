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
                    'summary' => 'An evidence-bounded overview for learners, educational institutions, and thesis evaluators.',
                    'sections' => [
                        ['title' => 'Purpose', 'body' => ['Hospitrainity is a thesis prototype for practicing English used in hospitality situations.', 'Its current learning material is organized into published modules, lesson sections, vocabulary tables, and accessible activities.']],
                        ['title' => 'Who it supports', 'body' => ['Learners can study personally or in a separately tracked institution context.', 'Institution staff can invite learners and review institution-attributed participation progress.', 'Content and System Admins manage authorized content and platform evidence. Employer-specific capabilities are not currently provided.']],
                        ['title' => 'Evidence boundary', 'body' => ['Completion records participation in the implemented activity workflow; it is not proof of proficiency or mastery.', 'The optional confidence check is self-reflection, not a diagnostic score.', 'This prototype does not claim guaranteed fluency, independent WCAG conformance, or production readiness.']],
                    ],
                ],
                'topics' => [
                    'getting-started' => [
                        'title' => 'Getting started',
                        'summary' => 'Choose personal or institution learning, then open your first published module.',
                        'sections' => [
                            ['title' => 'Personal learning', 'body' => ['A verified learner account can study without an institution.', 'Personal progress belongs to the personal learning context.']],
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
                            ['title' => 'Progress boundary', 'body' => ['Joining never transfers earlier personal progress. The institution starts with a separate no-progress view to preserve the agreed attribution boundary.']],
                        ],
                    ],
                    'learning-and-progress' => [
                        'title' => 'Learning and progress',
                        'summary' => 'What modules, activities, completion, and confidence history mean.',
                        'sections' => [
                            ['title' => 'Published learning', 'body' => ['Learners see the active published curriculum. Drafts and preview responses are not learner search results.']],
                            ['title' => 'Completion', 'body' => ['Completion means the implemented participation and self-check requirements were recorded.', 'It is not automatically a score, grade, mastery judgment, or proof of learning.']],
                            ['title' => 'Confidence check', 'body' => ['Confidence ratings are optional self-reflection. They are not a proficiency diagnostic and can be explicitly skipped where offered.']],
                        ],
                    ],
                    'account-and-recovery' => [
                        'title' => 'Account access and recovery',
                        'summary' => 'Password reset, multi-factor recovery, security settings, and institution support.',
                        'sections' => [
                            ['title' => 'Password', 'body' => ['Use the password-reset link on the sign-in page if you can access your registered email.', 'Hospitrainity never asks you to send a password or recovery code to another person.']],
                            ['title' => 'Multi-factor authentication', 'body' => ['Use a saved one-time recovery code if your authenticator is unavailable.', 'Privileged account recovery requires the documented controlled process; there is no hidden bypass.']],
                            ['title' => 'Institution help', 'body' => ['Ask your institution supervisor or administrator about invitations, classroom codes, membership approval, or institution-tracked progress.', 'No public human-support address has been approved for this prototype.']],
                        ],
                    ],
                    'institution-staff' => [
                        'title' => 'For institution staff',
                        'summary' => 'Invite learners, create classroom codes, approve membership requests, and review attributed progress.',
                        'sections' => [
                            ['title' => 'Bring learners in', 'body' => ['Use Invitations for one addressed learner or Classroom codes for a time-limited group entry path.', 'Review pending membership requests before institution tracking starts.']],
                            ['title' => 'Review progress', 'body' => ['The team dashboard and progress detail show only the active institution scope.', 'Current progress represents participation/completion, not grading or mastery.']],
                            ['title' => 'Current limits', 'body' => ['Classes, assignments, due dates, grading queues, and employer workflows are not currently available.']],
                        ],
                    ],
                    'content-and-evidence' => [
                        'title' => 'Content status and evidence',
                        'summary' => 'Plain-language learning status first, with exact technical evidence kept secondary.',
                        'sections' => [
                            ['title' => 'Learner view', 'body' => ['Learners receive published modules, lesson titles, activities, and progress actions without needing package hashes or lifecycle codes.']],
                            ['title' => 'Content administration', 'body' => ['Content Admins work in versioned draft workspaces and preview before publication.', 'Exact source hashes, provenance, lifecycle data, and legacy records remain available in named evidence views for authorized review.']],
                            ['title' => 'Read-only legacy evidence', 'body' => ['Legacy evidence is retained for audit and rollback context. It does not control current learner delivery and cannot be edited while canonical content is active.']],
                        ],
                    ],
                    'accessibility-and-display' => [
                        'title' => 'Accessibility and display',
                        'summary' => 'Keyboard access, browser zoom, display preferences, motion, contrast, and alternatives.',
                        'sections' => [
                            ['title' => 'Always available', 'body' => ['Keyboard operation, semantic reading order, error recovery, and browser zoom do not depend on an accessibility mode.']],
                            ['title' => 'Display preferences', 'body' => ['Signed-in users can choose system/light/dark theme, motion, text size, stronger contrast, and a no-audio preference.', 'The no-audio preference records a need for alternatives; it does not remove required information.']],
                            ['title' => 'Prototype boundary', 'body' => ['Automated and emulated-browser checks do not prove independent WCAG conformance. Physical-device, screen-reader, and qualified human review remain required.']],
                        ],
                    ],
                ],
                'glossary' => [
                    'learner' => ['term' => 'Learner', 'definition' => 'A person using personal learning or an approved institution learning context.'],
                    'institution-supervisor' => ['term' => 'Institution Supervisor', 'definition' => 'Institution-scoped staff who can invite learners and review institution-attributed participation progress.'],
                    'content-admin' => ['term' => 'Content Admin', 'definition' => 'A person with the separate capability to author and review shared curriculum content.'],
                    'system-admin' => ['term' => 'System Admin', 'definition' => 'A global platform role that manages Hospitrainity-wide authority and evidence. Institutions do not receive this role.'],
                    'learning-context' => ['term' => 'Learning context', 'definition' => 'The selected personal or institution scope in which new progress is recorded.'],
                    'classroom-code' => ['term' => 'Classroom code', 'definition' => 'A reusable, time-limited code created by authorized institution staff to request institution membership.'],
                    'completion' => ['term' => 'Completion', 'definition' => 'A record that the implemented participation or self-check rule was satisfied; not automatically a score or mastery judgment.'],
                    'confidence-check' => ['term' => 'Confidence check', 'definition' => 'Optional self-reflection on confidence, not a proficiency diagnostic.'],
                    'canonical-content' => ['term' => 'Canonical content', 'definition' => 'The versioned source-authoritative curriculum used for current learner delivery.'],
                    'legacy-evidence' => ['term' => 'Legacy evidence', 'definition' => 'Read-only retained records used for audit and rollback context, not current learner delivery.'],
                ],
            ],
            'id' => [
                'about' => [
                    'title' => 'Tentang Hospitrainity',
                    'summary' => 'Ringkasan berbatas bukti untuk pelajar, institusi pendidikan, dan penguji tesis.',
                    'sections' => [
                        ['title' => 'Tujuan', 'body' => ['Hospitrainity adalah prototipe tesis untuk melatih bahasa Inggris yang digunakan dalam situasi perhotelan.', 'Materi saat ini tersusun dalam modul terbit, bagian pelajaran, tabel kosakata, dan aktivitas yang dapat diakses.']],
                        ['title' => 'Pengguna yang didukung', 'body' => ['Pelajar dapat belajar secara pribadi atau dalam konteks institusi yang dilacak secara terpisah.', 'Staf institusi dapat mengundang pelajar dan meninjau progres partisipasi yang terkait dengan institusi.', 'Admin Konten dan Admin Sistem mengelola konten serta bukti platform sesuai kewenangan. Fitur khusus pemberi kerja belum tersedia.']],
                        ['title' => 'Batas bukti', 'body' => ['Penyelesaian mencatat partisipasi dalam alur aktivitas yang diterapkan; ini bukan bukti kemahiran atau penguasaan.', 'Pemeriksaan keyakinan yang opsional adalah refleksi diri, bukan skor diagnostik.', 'Prototipe ini tidak mengklaim kelancaran yang terjamin, kesesuaian WCAG independen, atau kesiapan produksi.']],
                    ],
                ],
                'topics' => [
                    'getting-started' => [
                        'title' => 'Memulai',
                        'summary' => 'Pilih pembelajaran pribadi atau institusi, lalu buka modul terbit pertama.',
                        'sections' => [
                            ['title' => 'Pembelajaran pribadi', 'body' => ['Akun pelajar terverifikasi dapat belajar tanpa institusi.', 'Progres pribadi berada dalam konteks pembelajaran pribadi.']],
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
                            ['title' => 'Batas progres', 'body' => ['Bergabung tidak pernah memindahkan progres pribadi sebelumnya. Institusi memulai dengan tampilan tanpa progres yang terpisah untuk menjaga batas atribusi yang disepakati.']],
                        ],
                    ],
                    'learning-and-progress' => [
                        'title' => 'Pembelajaran dan progres',
                        'summary' => 'Arti modul, aktivitas, penyelesaian, dan riwayat keyakinan.',
                        'sections' => [
                            ['title' => 'Pembelajaran terbit', 'body' => ['Pelajar melihat kurikulum terbit yang aktif. Draf dan jawaban pratinjau tidak menjadi hasil pencarian pelajar.']],
                            ['title' => 'Penyelesaian', 'body' => ['Penyelesaian berarti persyaratan partisipasi dan pemeriksaan diri yang diterapkan telah dicatat.', 'Ini tidak otomatis menjadi skor, nilai, penilaian penguasaan, atau bukti pembelajaran.']],
                            ['title' => 'Pemeriksaan keyakinan', 'body' => ['Penilaian keyakinan adalah refleksi diri opsional. Ini bukan diagnosis kemahiran dan dapat dilewati secara eksplisit jika tersedia.']],
                        ],
                    ],
                    'account-and-recovery' => [
                        'title' => 'Akses dan pemulihan akun',
                        'summary' => 'Atur ulang kata sandi, pemulihan multifaktor, pengaturan keamanan, dan dukungan institusi.',
                        'sections' => [
                            ['title' => 'Kata sandi', 'body' => ['Gunakan tautan atur ulang kata sandi di halaman masuk jika Anda dapat mengakses email terdaftar.', 'Hospitrainity tidak pernah meminta Anda mengirim kata sandi atau kode pemulihan kepada orang lain.']],
                            ['title' => 'Autentikasi multifaktor', 'body' => ['Gunakan kode pemulihan sekali pakai yang tersimpan jika autentikator tidak tersedia.', 'Pemulihan akun berwenang mengikuti proses terkendali yang terdokumentasi; tidak ada jalan pintas tersembunyi.']],
                            ['title' => 'Bantuan institusi', 'body' => ['Tanyakan kepada supervisor atau administrator institusi tentang undangan, kode kelas, persetujuan keanggotaan, atau progres yang dilacak institusi.', 'Belum ada alamat dukungan manusia publik yang disetujui untuk prototipe ini.']],
                        ],
                    ],
                    'institution-staff' => [
                        'title' => 'Untuk staf institusi',
                        'summary' => 'Undang pelajar, buat kode kelas, setujui permintaan keanggotaan, dan tinjau progres terkait.',
                        'sections' => [
                            ['title' => 'Memasukkan pelajar', 'body' => ['Gunakan Undangan untuk satu pelajar tertentu atau Kode kelas untuk jalur masuk kelompok berbatas waktu.', 'Tinjau permintaan keanggotaan yang tertunda sebelum pelacakan institusi dimulai.']],
                            ['title' => 'Meninjau progres', 'body' => ['Dasbor tim dan detail progres hanya menampilkan lingkup institusi yang aktif.', 'Progres saat ini mewakili partisipasi/penyelesaian, bukan penilaian atau penguasaan.']],
                            ['title' => 'Batas saat ini', 'body' => ['Kelas, tugas, tenggat, antrean penilaian, dan alur kerja pemberi kerja belum tersedia.']],
                        ],
                    ],
                    'content-and-evidence' => [
                        'title' => 'Status konten dan bukti',
                        'summary' => 'Status pembelajaran dengan bahasa sederhana terlebih dahulu; bukti teknis rinci tetap menjadi bagian sekunder.',
                        'sections' => [
                            ['title' => 'Tampilan pelajar', 'body' => ['Pelajar menerima modul terbit, judul pelajaran, aktivitas, dan tindakan progres tanpa perlu memahami hash paket atau kode siklus hidup.']],
                            ['title' => 'Administrasi konten', 'body' => ['Admin Konten bekerja dalam ruang kerja draf berversi dan melakukan pratinjau sebelum penerbitan.', 'Hash sumber, asal-usul, data siklus hidup, dan catatan lama tetap tersedia dalam tampilan bukti bernama bagi peninjau berwenang.']],
                            ['title' => 'Bukti lama hanya-baca', 'body' => ['Bukti lama dipertahankan untuk konteks audit dan pemulihan. Bukti ini tidak mengendalikan penyampaian pembelajaran saat ini dan tidak dapat disunting selama konten kanonis aktif.']],
                        ],
                    ],
                    'accessibility-and-display' => [
                        'title' => 'Aksesibilitas dan tampilan',
                        'summary' => 'Akses papan ketik, zoom peramban, preferensi tampilan, gerakan, kontras, dan alternatif.',
                        'sections' => [
                            ['title' => 'Selalu tersedia', 'body' => ['Pengoperasian papan ketik, urutan baca semantik, pemulihan kesalahan, dan zoom peramban tidak bergantung pada mode aksesibilitas.']],
                            ['title' => 'Preferensi tampilan', 'body' => ['Pengguna yang masuk dapat memilih tema sistem/terang/gelap, gerakan, ukuran teks, kontras lebih kuat, dan preferensi tanpa audio.', 'Preferensi tanpa audio mencatat kebutuhan alternatif; pilihan ini tidak menghapus informasi wajib.']],
                            ['title' => 'Batas prototipe', 'body' => ['Pemeriksaan otomatis dan emulasi peramban tidak membuktikan kesesuaian WCAG independen. Tinjauan perangkat fisik, pembaca layar, dan manusia berkualifikasi tetap diperlukan.']],
                        ],
                    ],
                ],
                'glossary' => [
                    'learner' => ['term' => 'Pelajar', 'definition' => 'Orang yang menggunakan pembelajaran pribadi atau konteks pembelajaran institusi yang disetujui.'],
                    'institution-supervisor' => ['term' => 'Supervisor Institusi', 'definition' => 'Staf dalam lingkup institusi yang dapat mengundang pelajar dan meninjau progres partisipasi terkait institusi.'],
                    'content-admin' => ['term' => 'Admin Konten', 'definition' => 'Orang dengan kapabilitas terpisah untuk menyusun dan meninjau konten kurikulum bersama.'],
                    'system-admin' => ['term' => 'Admin Sistem', 'definition' => 'Peran platform global yang mengelola kewenangan dan bukti Hospitrainity secara menyeluruh. Institusi tidak menerima peran ini.'],
                    'learning-context' => ['term' => 'Konteks pembelajaran', 'definition' => 'Lingkup pribadi atau institusi yang dipilih untuk mencatat progres baru.'],
                    'classroom-code' => ['term' => 'Kode kelas', 'definition' => 'Kode pakai-ulang berbatas waktu yang dibuat staf institusi berwenang untuk meminta keanggotaan institusi.'],
                    'completion' => ['term' => 'Penyelesaian', 'definition' => 'Catatan bahwa aturan partisipasi atau pemeriksaan diri yang diterapkan telah terpenuhi; bukan otomatis skor atau penilaian penguasaan.'],
                    'confidence-check' => ['term' => 'Pemeriksaan keyakinan', 'definition' => 'Refleksi diri opsional tentang keyakinan, bukan diagnosis kemahiran.'],
                    'canonical-content' => ['term' => 'Konten kanonis', 'definition' => 'Kurikulum berversi yang menjadi sumber berwenang untuk penyampaian pembelajaran saat ini.'],
                    'legacy-evidence' => ['term' => 'Bukti lama', 'definition' => 'Catatan hanya-baca untuk konteks audit dan pemulihan, bukan penyampaian pembelajaran saat ini.'],
                ],
            ],
        ];
    }
}
