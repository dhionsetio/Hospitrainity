<?php

namespace App\Services;

use Illuminate\Support\Arr;
use InvalidArgumentException;

class PolicyDocumentRegistry
{
    /** @var list<string> */
    public const TYPES = ['privacy', 'terms', 'accessibility', 'acceptable-use', 'support'];

    /** @return array<string, mixed> */
    public function document(string $type, ?string $locale = null): array
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Unknown public policy document.');
        }

        $locale = in_array($locale, ['id', 'en'], true) ? $locale : app()->getLocale();
        $version = (string) config('privacy.policy.version');
        $effectiveDate = (string) config('privacy.policy.effective_date');
        $content = $this->content($type, $locale);

        return [
            'type' => $type,
            'locale' => $locale,
            'version' => $version,
            'effective_date' => $effectiveDate,
            'authoritative_locale' => (string) config('privacy.policy.authoritative_locale'),
            'review_status' => (string) config('privacy.policy.review_status'),
            'operator' => config('privacy.operator'),
            'title' => $content['title'],
            'summary' => $content['summary'],
            'sections' => $content['sections'],
            'content_sha256' => hash('sha256', json_encode([
                $type,
                $locale,
                $version,
                $effectiveDate,
                $content,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
        ];
    }

    /** @return array<string, array{title: string, body: string|list<string>}> */
    private function sections(string $type, string $locale): array
    {
        return Arr::get($this->translations(), "$locale.$type.sections", []);
    }

    /** @return array{title: string, summary: string, sections: array<string, array{title: string, body: string|list<string>}>} */
    private function content(string $type, string $locale): array
    {
        $document = Arr::get($this->translations(), "$locale.$type");
        if (! is_array($document)) {
            throw new InvalidArgumentException('Policy translation is unavailable.');
        }

        $document['sections'] = $this->sections($type, $locale);

        return $document;
    }

    /** @return array<string, mixed> */
    private function translations(): array
    {
        $operator = (string) config('privacy.operator.name');
        $email = (string) config('privacy.operator.email');
        $exportHours = (int) config('privacy.export_expiry_hours');
        $requestYears = max(1, (int) round(((int) config('privacy.request_evidence_retention_days')) / 365));
        $backupDays = (int) config('privacy.backup_expiry_days');
        $retentionEn = [
            'Account details are kept while the account is active. Identifying details are replaced after an approved deletion request.',
            'Institution and Class enrollment records are kept while active and during the institution archive period.',
            'Learning progress and submitted responses are kept while the account or Class is active. Deletion follows the privacy-request process.',
            'Administration records are kept for three years and no longer identify a deleted account.',
            'Security records are kept for one year.',
            "Data-download files are removed {$exportHours} hours after they are prepared.",
            "Privacy-request history is kept for {$requestYears} years after the request closes without keeping deleted account content.",
            "Backups may remain for up to {$backupDays} days. Approved account deletion still applies if a backup is restored.",
        ];
        $retentionId = [
            'Data akun disimpan selama akun aktif. Data pengenal diganti setelah permintaan penghapusan disetujui.',
            'Catatan pendaftaran institusi dan Kelas disimpan selama aktif dan selama masa arsip institusi.',
            'Progres belajar dan jawaban yang dikirim disimpan selama akun atau Kelas aktif. Penghapusan mengikuti proses permintaan privasi.',
            'Catatan administrasi disimpan selama tiga tahun dan tidak lagi mengenali akun yang telah dihapus.',
            'Catatan keamanan disimpan selama satu tahun.',
            "Berkas unduhan data dihapus {$exportHours} jam setelah disiapkan.",
            "Riwayat permintaan privasi disimpan selama {$requestYears} tahun setelah permintaan ditutup tanpa menyimpan isi akun yang telah dihapus.",
            "Cadangan dapat tersimpan hingga {$backupDays} hari. Penghapusan akun yang telah disetujui tetap berlaku jika cadangan dipulihkan.",
        ];

        return [
            'en' => [
                'privacy' => [
                    'title' => 'Privacy notice',
                    'summary' => 'How Hospitrainity handles account, institution, and learning data.',
                    'sections' => [
                        'data' => ['title' => 'Data we handle', 'body' => ['Your name, email address, sign-in settings, and account security information.', 'Institution memberships, invitations, classroom-code requests, and roles.', 'Learning progress, attempts, and submitted responses.', 'Account, privacy-request, administration, and security history. Passwords and full classroom codes are not stored in readable form.']],
                        'purpose' => ['title' => 'Why we use it', 'body' => 'Hospitrainity uses this data to provide personal and institution learning, protect accounts, answer requests, and keep authorized learning records. It does not sell personal data or use it for advertising.'],
                        'scope-separation' => ['title' => 'Personal and institution learning', 'body' => 'Personal progress stays separate. Institution staff see only progress saved for their institution or Class. Joining does not copy your earlier personal progress.'],
                        'retention' => ['title' => 'How long data is kept', 'body' => $retentionEn],
                        'rights' => ['title' => 'Your requests and choices', 'body' => 'You can request a data download, correction, restriction, objection, deletion, consent withdrawal, or appeal. Staff review each request. Some institution or request records may need to be kept for the periods above. A recent password confirmation is required for downloads and deletion requests.'],
                        'contact' => ['title' => 'Contact', 'body' => "Use the privacy-request page after signing in. If you cannot access your account, contact {$operator} at {$email}."],
                    ],
                ],
                'terms' => [
                    'title' => 'Terms of use',
                    'summary' => 'The rules for using Hospitrainity.',
                    'sections' => [
                        'prototype' => ['title' => 'Learning and testing', 'body' => 'Use Hospitrainity for learning and authorized testing. The service does not promise certification, grades, employment, or uninterrupted availability.'],
                        'accounts' => ['title' => 'Accounts', 'body' => 'Use your own account, keep sign-in details private, and follow institution and role restrictions. Demo accounts and sample institutions contain testing data only.'],
                        'content' => ['title' => 'Learning content', 'body' => 'Learning activities support practice. They do not provide professional certification or guarantee a workplace outcome.'],
                        'conduct' => ['title' => 'Acceptable use', 'body' => 'Do not disrupt the service, probe other users, upload harmful content, submit another person’s data without authority, or use progress controls to misrepresent learning activity.'],
                        'changes' => ['title' => 'Changes', 'body' => 'Hospitrainity may ask you to review and accept updated terms when important changes are made.'],
                    ],
                ],
                'accessibility' => [
                    'title' => 'Accessibility statement',
                    'summary' => 'Accessibility options and how to report a problem.',
                    'sections' => [
                        'target' => ['title' => 'Target', 'body' => 'Hospitrainity targets WCAG 2.2 Level AA and supports keyboard operation, visible labels, semantic landmarks, focus handling, zoom, and reduced motion.'],
                        'feedback' => ['title' => 'Report a barrier', 'body' => "Describe the page, task, browser/device, and barrier by contacting {$email}. Do not include passwords, classroom codes, or sensitive learning responses."],
                    ],
                ],
                'acceptable-use' => [
                    'title' => 'Acceptable use',
                    'summary' => 'Safety rules for accounts, learning activity, and administration.',
                    'sections' => [
                        'allowed' => ['title' => 'Expected use', 'body' => 'Use the service for authorized learning, teaching, content review, administration, and consented usability testing.'],
                        'prohibited' => ['title' => 'Prohibited use', 'body' => ['Do not access another person’s account or institution.', 'Do not share invitation or classroom codes beyond the intended Class.', 'Do not upload malware, unlawful material, or unnecessary personal data.', 'Do not change another person’s requests or progress.', 'Do not test the service for security weaknesses without written permission.']],
                        'response' => ['title' => 'Response', 'body' => 'Hospitrainity may disable an account or sign it out when needed to protect users. You may ask staff to review the decision.'],
                    ],
                ],
                'support' => [
                    'title' => 'Support',
                    'summary' => 'How to obtain account, institution, privacy, and accessibility assistance.',
                    'sections' => [
                        'account' => ['title' => 'Account access', 'body' => "Use password reset first. If you cannot access your email account, contact {$email}. Staff must verify your identity before changing an account or privacy request."],
                        'institution' => ['title' => 'Institution access', 'body' => 'Ask your instructor or Institution Admin for a current classroom code or invitation. Support cannot silently add an institution or expose earlier personal progress.'],
                        'privacy' => ['title' => 'Privacy and accessibility', 'body' => "Signed-in users should use the tracked request page. Other support and accessibility reports may be sent to {$email}."],
                    ],
                ],
            ],
            'id' => [
                'privacy' => [
                    'title' => 'Pemberitahuan privasi',
                    'summary' => 'Cara Hospitrainity menangani data akun, institusi, dan pembelajaran.',
                    'sections' => [
                        'data' => ['title' => 'Data yang kami tangani', 'body' => ['Nama, alamat surel, pengaturan masuk, dan informasi keamanan akun.', 'Keanggotaan institusi, undangan, permintaan kode kelas, dan peran.', 'Progres belajar, percobaan, dan jawaban yang dikirim.', 'Riwayat akun, permintaan privasi, administrasi, dan keamanan. Kata sandi dan kode kelas lengkap tidak disimpan dalam bentuk yang dapat dibaca.']],
                        'purpose' => ['title' => 'Tujuan penggunaan', 'body' => 'Hospitrainity menggunakan data ini untuk menyediakan pembelajaran pribadi dan institusi, melindungi akun, menjawab permintaan, dan menyimpan catatan belajar yang diizinkan. Data pribadi tidak dijual atau digunakan untuk iklan.'],
                        'scope-separation' => ['title' => 'Pembelajaran pribadi dan institusi', 'body' => 'Progres pribadi tetap terpisah. Staf institusi hanya melihat progres yang disimpan untuk institusi atau Kelas mereka. Bergabung tidak menyalin progres pribadi Anda sebelumnya.'],
                        'retention' => ['title' => 'Lama penyimpanan data', 'body' => $retentionId],
                        'rights' => ['title' => 'Permintaan dan pilihan Anda', 'body' => 'Anda dapat meminta unduhan data, koreksi, pembatasan, keberatan, penghapusan, penarikan persetujuan, atau banding. Staf meninjau setiap permintaan. Beberapa catatan institusi atau permintaan mungkin perlu disimpan selama jangka waktu di atas. Konfirmasi kata sandi terbaru diperlukan untuk unduhan dan permintaan penghapusan.'],
                        'contact' => ['title' => 'Kontak', 'body' => "Gunakan halaman permintaan privasi setelah masuk. Jika akun tidak dapat diakses, hubungi {$operator} melalui {$email}."],
                    ],
                ],
                'terms' => [
                    'title' => 'Ketentuan penggunaan',
                    'summary' => 'Aturan penggunaan Hospitrainity.',
                    'sections' => [
                        'prototype' => ['title' => 'Pembelajaran dan pengujian', 'body' => 'Gunakan Hospitrainity untuk pembelajaran dan pengujian yang diizinkan. Layanan tidak menjanjikan sertifikasi, nilai, pekerjaan, atau ketersediaan tanpa gangguan.'],
                        'accounts' => ['title' => 'Akun', 'body' => 'Gunakan akun sendiri, jaga informasi masuk, dan ikuti batas institusi serta peran. Akun demo dan institusi contoh hanya berisi data pengujian.'],
                        'content' => ['title' => 'Materi belajar', 'body' => 'Aktivitas belajar mendukung latihan. Aktivitas ini tidak memberikan sertifikasi profesional atau menjamin hasil kerja.'],
                        'conduct' => ['title' => 'Penggunaan yang dapat diterima', 'body' => 'Jangan mengganggu layanan, mencoba mengakses pengguna lain, mengunggah konten berbahaya, mengirim data orang lain tanpa wewenang, atau memanipulasi progres.'],
                        'changes' => ['title' => 'Perubahan', 'body' => 'Hospitrainity dapat meminta Anda meninjau dan menerima ketentuan baru ketika terjadi perubahan penting.'],
                    ],
                ],
                'accessibility' => [
                    'title' => 'Pernyataan aksesibilitas',
                    'summary' => 'Pilihan aksesibilitas dan cara melaporkan masalah.',
                    'sections' => [
                        'target' => ['title' => 'Target', 'body' => 'Hospitrainity menargetkan WCAG 2.2 Tingkat AA serta mendukung keyboard, label terlihat, landmark semantik, penanganan fokus, zoom, dan pengurangan gerak.'],
                        'feedback' => ['title' => 'Laporkan hambatan', 'body' => "Jelaskan halaman, tugas, browser/perangkat, dan hambatan melalui {$email}. Jangan sertakan kata sandi, kode kelas, atau respons belajar sensitif."],
                    ],
                ],
                'acceptable-use' => [
                    'title' => 'Penggunaan yang dapat diterima',
                    'summary' => 'Aturan keselamatan untuk akun, aktivitas belajar, dan administrasi.',
                    'sections' => [
                        'allowed' => ['title' => 'Penggunaan yang diharapkan', 'body' => 'Gunakan layanan untuk pembelajaran, pengajaran, peninjauan konten, administrasi, dan pengujian kegunaan yang disahkan.'],
                        'prohibited' => ['title' => 'Penggunaan terlarang', 'body' => ['Jangan akses akun atau institusi orang lain.', 'Jangan bagikan undangan atau kode kelas di luar Kelas tujuan.', 'Jangan unggah malware, materi melanggar hukum, atau data pribadi yang tidak perlu.', 'Jangan ubah permintaan atau progres milik orang lain.', 'Jangan menguji kelemahan keamanan layanan tanpa izin tertulis.']],
                        'response' => ['title' => 'Tindakan', 'body' => 'Hospitrainity dapat menonaktifkan akun atau mengeluarkannya jika diperlukan untuk melindungi pengguna. Anda dapat meminta staf meninjau keputusan tersebut.'],
                    ],
                ],
                'support' => [
                    'title' => 'Bantuan',
                    'summary' => 'Cara memperoleh bantuan akun, institusi, privasi, dan aksesibilitas.',
                    'sections' => [
                        'account' => ['title' => 'Akses akun', 'body' => "Gunakan pengaturan ulang kata sandi terlebih dahulu. Jika surel tidak dapat diakses, hubungi {$email}. Staf harus memverifikasi identitas Anda sebelum mengubah akun atau permintaan privasi."],
                        'institution' => ['title' => 'Akses institusi', 'body' => 'Minta kode kelas aktif atau undangan kepada instruktur/Admin Institusi. Bantuan tidak dapat diam-diam menambahkan institusi atau membuka progres pribadi sebelumnya.'],
                        'privacy' => ['title' => 'Privasi dan aksesibilitas', 'body' => "Pengguna yang sudah masuk sebaiknya memakai halaman permintaan terlacak. Bantuan lain dan laporan aksesibilitas dapat dikirim ke {$email}."],
                    ],
                ],
            ],
        ];
    }
}
