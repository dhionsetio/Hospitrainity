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
        $retention = config('privacy.retention');
        $operator = (string) config('privacy.operator.name');
        $email = (string) config('privacy.operator.email');

        return [
            'en' => [
                'privacy' => [
                    'title' => 'Privacy notice',
                    'summary' => 'How the Hospitrainity thesis prototype handles account, institution, and learning data.',
                    'sections' => [
                        'scope' => ['title' => 'Prototype status', 'body' => "Hospitrainity is a non-production thesis prototype operated by {$operator}. A statutory production data controller, processors, hosting locations, and international-transfer arrangements have not been established. This notice describes verified current behavior and is not a claim of legal compliance."],
                        'data' => ['title' => 'Data handled', 'body' => ['Account name, email, password hash, verification and security state.', 'Institution memberships, invitations, classroom-code requests, and assigned roles.', 'Learning progress, structured attempts and responses, and curriculum version references.', 'Bounded administration, identity, privacy-request, and security evidence. Passwords, raw tokens, and microphone recordings are not stored as audit content.']],
                        'purpose' => ['title' => 'Why it is handled', 'body' => 'Data is used to provide self-study and institution-attributed learning, secure accounts, answer user requests, preserve authorized academic evidence, and diagnose the prototype. No advertising or sale of personal data is implemented.'],
                        'scope-separation' => ['title' => 'Personal and institution learning', 'body' => 'Personal progress remains separate. Institution staff can see only progress written to an approved institution membership scope; joining does not copy earlier personal progress.'],
                        'retention' => ['title' => 'Prototype retention policy', 'body' => array_values($retention)],
                        'rights' => ['title' => 'Requests and choices', 'body' => 'A verified user can submit access/export, correction, restriction, objection, deletion, consent-withdrawal, or appeal requests. Requests are reviewed; deletion is not immediate because retained audit or institution evidence may require a documented decision. Export and deletion require recent password confirmation.'],
                        'contact' => ['title' => 'Contact', 'body' => "Prototype privacy requests are handled through the authenticated request page. If account access is unavailable, contact {$email}. This address is the prototype operator contact, not a claim that a statutory DPO has been appointed."],
                    ],
                ],
                'terms' => [
                    'title' => 'Prototype terms',
                    'summary' => 'Conditions for using Hospitrainity during development and thesis evaluation.',
                    'sections' => [
                        'prototype' => ['title' => 'Testing service', 'body' => 'Hospitrainity is supplied for learning, testing, and thesis evaluation. Availability, production durability, certification, grades, and employment outcomes are not promised.'],
                        'accounts' => ['title' => 'Accounts', 'body' => 'Use your own account, keep credentials private, and do not bypass institutional approval or role restrictions. Demo accounts and Hotel A/Hotel B are testing fixtures only.'],
                        'content' => ['title' => 'Learning content', 'body' => 'The active curriculum identifies its source version and release state. Preview or draft labels must not be interpreted as final publication or professional certification.'],
                        'conduct' => ['title' => 'Acceptable use', 'body' => 'Do not disrupt the service, probe other users, upload harmful content, submit another person’s data without authority, or use progress controls to misrepresent learning activity.'],
                        'changes' => ['title' => 'Changes', 'body' => 'Material changes receive a new version and effective date. Continued production use will require reviewed final terms; this prototype version remains explicitly non-production.'],
                    ],
                ],
                'accessibility' => [
                    'title' => 'Accessibility statement',
                    'summary' => 'Current accessibility target, evidence boundary, and support route.',
                    'sections' => [
                        'target' => ['title' => 'Target', 'body' => 'Hospitrainity targets WCAG 2.2 Level AA and supports keyboard operation, visible labels, semantic landmarks, focus handling, zoom, and reduced motion.'],
                        'status' => ['title' => 'Current status', 'body' => 'Automated and code-level checks are not a conformance claim. The required browser, screen-reader, physical-device, and independent human review matrix is still in progress.'],
                        'feedback' => ['title' => 'Report a barrier', 'body' => "Describe the page, task, browser/device, and barrier by contacting {$email}. Do not include passwords, classroom codes, or sensitive learning responses."],
                    ],
                ],
                'acceptable-use' => [
                    'title' => 'Acceptable use',
                    'summary' => 'Safety rules for prototype accounts, learning activity, and administration.',
                    'sections' => [
                        'allowed' => ['title' => 'Expected use', 'body' => 'Use the service for authorized learning, teaching, content review, administration, and consented usability testing.'],
                        'prohibited' => ['title' => 'Prohibited use', 'body' => ['Do not access another person’s account or institution scope.', 'Do not share invitation or classroom codes beyond the intended class.', 'Do not upload malware, unlawful material, or unnecessary personal data.', 'Do not manipulate requests, progress, or audit evidence.', 'Do not conduct security testing against shared or production systems without written authorization.']],
                        'response' => ['title' => 'Response', 'body' => 'Accounts may be reversibly disabled and sessions revoked when necessary to protect users or evidence. Material actions are audited and may be reviewed through the request process.'],
                    ],
                ],
                'support' => [
                    'title' => 'Support',
                    'summary' => 'How to obtain account, institution, privacy, and accessibility assistance.',
                    'sections' => [
                        'account' => ['title' => 'Account access', 'body' => 'Use password reset first. If the email account is inaccessible, contact the prototype operator; identity must be re-verified before an account or privacy request is changed.'],
                        'institution' => ['title' => 'Institution access', 'body' => 'Ask your instructor or Institution Admin for a current classroom code or invitation. Support cannot silently add an institution or expose earlier personal progress.'],
                        'privacy' => ['title' => 'Privacy and accessibility', 'body' => "Signed-in users should use the tracked request page. Other support and accessibility reports may be sent to {$email}."],
                    ],
                ],
            ],
            'id' => [
                'privacy' => [
                    'title' => 'Pemberitahuan privasi',
                    'summary' => 'Cara prototipe tesis Hospitrainity menangani data akun, institusi, dan pembelajaran.',
                    'sections' => [
                        'scope' => ['title' => 'Status prototipe', 'body' => "Hospitrainity adalah prototipe tesis nonproduksi yang dioperasikan oleh {$operator}. Pengendali data produksi menurut hukum, prosesor, lokasi hosting, dan pengaturan transfer internasional belum ditetapkan. Pemberitahuan ini menjelaskan perilaku sistem yang telah diverifikasi dan bukan klaim kepatuhan hukum."],
                        'data' => ['title' => 'Data yang ditangani', 'body' => ['Nama akun, surel, hash kata sandi, verifikasi, dan status keamanan.', 'Keanggotaan institusi, undangan, permintaan kode kelas, dan peran.', 'Progres belajar, percobaan dan respons terstruktur, serta versi kurikulum.', 'Bukti administrasi, identitas, permintaan privasi, dan keamanan yang dibatasi. Kata sandi, token mentah, dan rekaman mikrofon tidak disimpan sebagai isi audit.']],
                        'purpose' => ['title' => 'Tujuan', 'body' => 'Data digunakan untuk belajar mandiri dan belajar dalam institusi, mengamankan akun, menjawab permintaan pengguna, menjaga bukti akademik yang disahkan, dan mendiagnosis prototipe. Sistem tidak menerapkan iklan atau penjualan data pribadi.'],
                        'scope-separation' => ['title' => 'Pembelajaran pribadi dan institusi', 'body' => 'Progres pribadi tetap terpisah. Staf institusi hanya dapat melihat progres yang dibuat dalam cakupan keanggotaan institusi yang disetujui; bergabung tidak menyalin progres pribadi sebelumnya.'],
                        'retention' => ['title' => 'Kebijakan retensi prototipe', 'body' => array_values($retention)],
                        'rights' => ['title' => 'Permintaan dan pilihan', 'body' => 'Pengguna terverifikasi dapat meminta akses/ekspor, koreksi, pembatasan, keberatan, penghapusan, penarikan persetujuan, atau banding. Permintaan ditinjau; penghapusan tidak langsung karena bukti audit atau institusi mungkin memerlukan keputusan terdokumentasi. Ekspor dan penghapusan memerlukan konfirmasi kata sandi terbaru.'],
                        'contact' => ['title' => 'Kontak', 'body' => "Permintaan privasi prototipe ditangani melalui halaman permintaan setelah masuk. Jika akun tidak dapat diakses, hubungi {$email}. Alamat ini adalah kontak operator prototipe, bukan klaim bahwa DPO resmi telah ditunjuk."],
                    ],
                ],
                'terms' => [
                    'title' => 'Ketentuan prototipe',
                    'summary' => 'Ketentuan penggunaan Hospitrainity selama pengembangan dan evaluasi tesis.',
                    'sections' => [
                        'prototype' => ['title' => 'Layanan pengujian', 'body' => 'Hospitrainity disediakan untuk pembelajaran, pengujian, dan evaluasi tesis. Ketersediaan, ketahanan produksi, sertifikasi, nilai, dan hasil kerja tidak dijanjikan.'],
                        'accounts' => ['title' => 'Akun', 'body' => 'Gunakan akun sendiri, jaga kredensial, dan jangan melewati persetujuan institusi atau batas peran. Akun demo serta Hotel A/Hotel B hanya perlengkapan pengujian.'],
                        'content' => ['title' => 'Materi belajar', 'body' => 'Kurikulum aktif menunjukkan versi sumber dan status rilis. Label pratinjau atau draf bukan publikasi final atau sertifikasi profesional.'],
                        'conduct' => ['title' => 'Penggunaan yang dapat diterima', 'body' => 'Jangan mengganggu layanan, mencoba mengakses pengguna lain, mengunggah konten berbahaya, mengirim data orang lain tanpa wewenang, atau memanipulasi progres.'],
                        'changes' => ['title' => 'Perubahan', 'body' => 'Perubahan material mendapat versi dan tanggal berlaku baru. Penggunaan produksi akan memerlukan ketentuan final yang ditinjau; versi ini tetap khusus prototipe.'],
                    ],
                ],
                'accessibility' => [
                    'title' => 'Pernyataan aksesibilitas',
                    'summary' => 'Target aksesibilitas, batas bukti, dan jalur bantuan saat ini.',
                    'sections' => [
                        'target' => ['title' => 'Target', 'body' => 'Hospitrainity menargetkan WCAG 2.2 Tingkat AA serta mendukung keyboard, label terlihat, landmark semantik, penanganan fokus, zoom, dan pengurangan gerak.'],
                        'status' => ['title' => 'Status saat ini', 'body' => 'Pemeriksaan otomatis dan kode bukan klaim kesesuaian. Matriks browser, pembaca layar, perangkat fisik, dan tinjauan manusia independen masih berlangsung.'],
                        'feedback' => ['title' => 'Laporkan hambatan', 'body' => "Jelaskan halaman, tugas, browser/perangkat, dan hambatan melalui {$email}. Jangan sertakan kata sandi, kode kelas, atau respons belajar sensitif."],
                    ],
                ],
                'acceptable-use' => [
                    'title' => 'Penggunaan yang dapat diterima',
                    'summary' => 'Aturan keselamatan untuk akun prototipe, aktivitas belajar, dan administrasi.',
                    'sections' => [
                        'allowed' => ['title' => 'Penggunaan yang diharapkan', 'body' => 'Gunakan layanan untuk pembelajaran, pengajaran, peninjauan konten, administrasi, dan pengujian kegunaan yang disahkan.'],
                        'prohibited' => ['title' => 'Penggunaan terlarang', 'body' => ['Jangan akses akun atau cakupan institusi orang lain.', 'Jangan bagikan undangan atau kode kelas di luar kelas tujuan.', 'Jangan unggah malware, materi melanggar hukum, atau data pribadi yang tidak perlu.', 'Jangan manipulasi permintaan, progres, atau bukti audit.', 'Jangan menguji keamanan sistem bersama/produksi tanpa izin tertulis.']],
                        'response' => ['title' => 'Tindakan', 'body' => 'Akun dapat dinonaktifkan secara reversibel dan sesi dicabut untuk melindungi pengguna atau bukti. Tindakan material diaudit dan dapat ditinjau melalui proses permintaan.'],
                    ],
                ],
                'support' => [
                    'title' => 'Bantuan',
                    'summary' => 'Cara memperoleh bantuan akun, institusi, privasi, dan aksesibilitas.',
                    'sections' => [
                        'account' => ['title' => 'Akses akun', 'body' => 'Gunakan pengaturan ulang kata sandi terlebih dahulu. Jika surel tidak dapat diakses, hubungi operator prototipe; identitas harus diverifikasi kembali sebelum akun atau permintaan privasi diubah.'],
                        'institution' => ['title' => 'Akses institusi', 'body' => 'Minta kode kelas aktif atau undangan kepada instruktur/Admin Institusi. Bantuan tidak dapat diam-diam menambahkan institusi atau membuka progres pribadi sebelumnya.'],
                        'privacy' => ['title' => 'Privasi dan aksesibilitas', 'body' => "Pengguna yang sudah masuk sebaiknya memakai halaman permintaan terlacak. Bantuan lain dan laporan aksesibilitas dapat dikirim ke {$email}."],
                    ],
                ],
            ],
        ];
    }
}
