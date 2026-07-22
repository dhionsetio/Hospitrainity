# Hospitrainity source compiler

The compiler reads the authoritative `Hospitrainity.docx` without modifying it and creates a deterministic canonical package. It uses the DOM, libxml, and ZipArchive extensions supplied by the PHP runtime already required by the Laravel project; application dependencies remain pinned by `composer.lock`.

Windows command (PowerShell):

```powershell
& 'C:\php\php.exe' scripts\curriculum\compile.php `
  --source 'C:\path\to\Hospitrainity.docx' `
  --baseline curriculum\hospitrainity\0.3.0-draft `
  --output curriculum\hospitrainity\0.4.0-draft `
  --force
```

The command fails closed if the source SHA-256 differs from the declared authority hash, if required chapter/section markers are absent or out of order, if entity IDs are duplicated, if table rows are malformed, if an external hyperlink relationship is missing, if an unsupported body block is encountered, or if any required coverage count differs.

`verify.php` builds twice into separate temporary directories, compares the complete trees byte-for-byte, and runs the six required negative probes. It requires the same `--source` and `--baseline` arguments.

## Web-native v0.8 candidate

The v0.8 source uses coded Heading 1 and Heading 2 records rather than the v0.4 book-style marker contract. Compile it into a separate, non-active candidate bundle:

```powershell
& 'C:\php\php.exe' scripts\curriculum\compile_next.php `
  --source 'C:\path\to\Hospitrainity New.docx' `
  --output storage\framework\testing\hospitrainity-v0.8.0-candidate `
  --source-artifact 'Hospitrainity.docx' `
  --force
```

Verify deterministic output and fail-closed structural probes:

```powershell
& 'C:\php\php.exe' scripts\curriculum\verify_next.php `
  --source 'C:\path\to\Hospitrainity New.docx'
```

This compiler preserves the active `curriculum/hospitrainity/0.4.0-draft` package. It separates learner blocks from build-only metadata, compiles 151 coded activities and response items, records the source CEFR claims with their unreviewed status, and rejects learner-facing em dashes. A candidate bundle is not an application release. The external relationship records in the supplied DOCX are preserved as unmapped resources because the document body contains no hyperlink anchors.
