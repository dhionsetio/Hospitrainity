[CmdletBinding()]
param(
    [string] $ConfigurationPath = 'config/authority-sources.json'
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $ConfigurationPath -PathType Leaf)) {
    throw 'Authority-source configuration is unavailable.'
}

$configuration = Get-Content -LiteralPath $ConfigurationPath -Raw | ConvertFrom-Json
if ($configuration.schema_version -ne '1.0.0' -or -not $configuration.sources) {
    throw 'Authority-source configuration does not satisfy schema version 1.0.0.'
}

foreach ($source in $configuration.sources) {
    $sourcePath = [Environment]::GetEnvironmentVariable($source.runner_environment_variable)
    if ([string]::IsNullOrWhiteSpace($sourcePath)) {
        throw "Required protected runner input is not configured for authority '$($source.id)'."
    }

    if (-not (Test-Path -LiteralPath $sourcePath -PathType Leaf)) {
        throw "Protected authority '$($source.id)' is unavailable on this runner."
    }

    if ([System.IO.Path]::GetExtension($sourcePath) -ine '.docx') {
        throw "Protected authority '$($source.id)' is not a DOCX file."
    }

    $actualHash = (Get-FileHash -LiteralPath $sourcePath -Algorithm SHA256).Hash.ToLowerInvariant()
    $expectedHash = ([string] $source.expected_sha256).ToLowerInvariant()
    if ($actualHash -ne $expectedHash) {
        throw "Protected authority '$($source.id)' does not match the approved SHA-256."
    }

    Write-Output "AUTHORITY_OK id=$($source.id) sha256=$actualHash"
}

Write-Output "AUTHORITY_SET_OK count=$($configuration.sources.Count)"
