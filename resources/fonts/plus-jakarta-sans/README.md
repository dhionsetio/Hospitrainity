# Plus Jakarta Sans vendoring record

Hospitrainity self-hosts the upright variable font from the official Plus Jakarta Sans 2.7.1 release.

- Project: <https://github.com/tokotype/PlusJakartaSans>
- Release: <https://github.com/tokotype/PlusJakartaSans/releases/tag/2.7.1>
- Release archive: `PlusJakartaSans-2.7.1.zip`
- Archive SHA-256: `4BFC5CDF97D750423BB3D1D40ED8E529BC92288924D9C65E18FF486ACEFAC66C`
- Vendored file: `PlusJakartaSans-Variable.ttf`
- Vendored file SHA-256: `3C9102733D96AF218EA12AAB89FD2C04A6D3C2BEE9ACF37057FC9B29139B451B`
- License: SIL Open Font License 1.1; the unmodified release license is retained as `OFL.txt`.

The file is served by Vite from the application bundle. CSS uses `font-display: swap` and keeps a system-sans fallback so text remains available while the font loads or if the asset cannot be used.
