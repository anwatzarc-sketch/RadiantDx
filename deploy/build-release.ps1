<#
.SYNOPSIS
    Packages RadiantDx into a ZIP that can be extracted straight into the
    Plesk webspace at pulsecore.med.et.

.DESCRIPTION
    The production host has neither Node nor a usable Composer, so the two
    things the repository deliberately does not track -- vendor/ and
    public/build/ -- have to be produced here and shipped inside the archive.

    Two shapes of archive, because they carry very different risk:

      * -Full   everything, vendor included. Needed for the first deployment
                and after any change to composer.lock. ~11 MB.

      * default code and built assets only, no vendor. This is the everyday
                archive: it is small, and because it cannot touch vendor it
                cannot leave the server with a half-replaced dependency tree.

    What is deliberately NOT in either archive:

      * .env              -- holds the production database password and
                             APP_KEY. It lives on the server and is never
                             transported; overwriting it would take the site
                             down and leak credentials through the ZIP.
      * storage/app/**    -- uploaded staff photographs. These exist only on
                             the server. Extracting an archive containing this
                             directory would delete every photo uploaded since
                             the last deployment.
      * bootstrap/cache/  -- compiled config and routes belonging to the
                             machine that built them. Shipping them would
                             pin the server to this laptop's absolute paths.

    vendor/ is built in a staging copy rather than in place, so running this
    never strips the dev dependencies the local test suite needs.

.EXAMPLE
    .\deploy\build-release.ps1 -Full
    .\deploy\build-release.ps1
#>

[CmdletBinding()]
param(
    # Include vendor/. Required for the first deployment and whenever
    # composer.lock has changed.
    [switch]$Full,

    # Where the finished archive is written.
    [string]$OutDir = "$env:USERPROFILE\Downloads",

    # Skip the Vite build and reuse whatever is already in public/build.
    [switch]$SkipAssets
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$Root = Split-Path -Parent $PSScriptRoot
$Stamp = Get-Date -Format 'yyyyMMdd-HHmm'
$Kind = if ($Full) { 'full' } else { 'update' }
$Stage = Join-Path ([System.IO.Path]::GetTempPath()) "radiantdx-$Kind-$Stamp"
$Zip = Join-Path $OutDir "radiantdx-$Kind-$Stamp.zip"

function Step($text) { Write-Host "`n==> $text" -ForegroundColor Cyan }
function Note($text) { Write-Host "    $text" -ForegroundColor DarkGray }

function Invoke-Native {
    <#
        Runs a native tool and judges it by its exit code alone.

        npm and composer both report progress on stderr. Under Windows
        PowerShell, $ErrorActionPreference = 'Stop' turns any stderr line from a
        native command into a terminating NativeCommandError as soon as the
        stream is redirected -- so piping this script to a file, or into
        Select-Object, made a perfectly good build abort on composer's first
        "Installing dependencies from lock file". The exit code is the only
        trustworthy signal here.
    #>
    param(
        [Parameter(Mandatory)][scriptblock]$Command,
        [Parameter(Mandatory)][string]$What,
        [int]$MaxOk = 0
    )
    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try { & $Command } finally { $ErrorActionPreference = $previous }
    if ($LASTEXITCODE -gt $MaxOk) { throw "$What failed (exit $LASTEXITCODE)" }
}

# --------------------------------------------------------------- assets ----
if (-not $SkipAssets) {
    Step 'Building front-end assets (vite)'
    Push-Location $Root
    try { Invoke-Native { npm run build } 'npm run build' }
    finally { Pop-Location }
}

if (-not (Test-Path (Join-Path $Root 'public\build\manifest.json'))) {
    throw 'public/build/manifest.json is missing -- the site would render unstyled. Run without -SkipAssets.'
}

# ---------------------------------------------------------------- stage ----
Step "Staging into $Stage"

# Excluded by absolute path, never by bare name: a bare "app" would also match
# the application's own app/ directory, and a bare "cache" would match more
# than intended.
$excludeDirs = @(
    "$Root\node_modules", "$Root\.git", "$Root\.github", "$Root\vendor",
    "$Root\tests", "$Root\docs", "$Root\deploy",
    "$Root\.phpunit.cache", "$Root\.vscode", "$Root\.idea",
    "$Root\bootstrap\cache",
    "$Root\storage\app", "$Root\storage\logs", "$Root\storage\pail",
    "$Root\storage\framework\cache", "$Root\storage\framework\sessions",
    "$Root\storage\framework\views", "$Root\storage\framework\testing"
)

$excludeFiles = @(
    '.env', '.env.backup', '.env.production', '.phpunit.result.cache',
    'hot', 'fonts-manifest.dev.json', '*.zip', '*.log',
    'phpunit.xml', 'vite.config.js', 'package-lock.json'
)

# An update archive must not carry the storage skeleton at all: the server's
# copy already exists and only the uploads inside it matter.
if (-not $Full) { $excludeDirs += "$Root\storage" }

$rcArgs = @($Root, $Stage, '/E', '/NFL', '/NDL', '/NJH', '/NJS', '/NP', '/R:1', '/W:1')
$rcArgs += '/XD'; $rcArgs += $excludeDirs
$rcArgs += '/XF'; $rcArgs += $excludeFiles

# robocopy reports success as exit codes 0-7 (files copied, extras present);
# 8 and above are genuine failures.
Invoke-Native { & robocopy @rcArgs | Out-Null } 'robocopy' -MaxOk 7

# Laravel needs these to exist and be writable, but empty.
foreach ($d in @('bootstrap\cache', 'storage\framework\cache\data',
        'storage\framework\sessions', 'storage\framework\views',
        'storage\logs', 'storage\app\private', 'storage\app\public')) {
    if (-not $Full -and $d.StartsWith('storage')) { continue }
    $p = Join-Path $Stage $d
    New-Item -ItemType Directory -Force -Path $p | Out-Null
    Set-Content -Path (Join-Path $p '.gitignore') -Value "*`n!.gitignore" -Encoding utf8
}

# ------------------------------------------------------------ composer -----
if ($Full) {
    Step 'Installing production dependencies (--no-dev) in the staging copy'
    Note 'The working vendor/ is left alone, so local tests keep their dev packages.'
    Push-Location $Stage
    try {
        Invoke-Native {
            composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
        } 'composer install'
    }
    finally { Pop-Location }
}

# ------------------------------------------------------------------ zip ----
Step 'Compressing'
New-Item -ItemType Directory -Force -Path $OutDir | Out-Null
if (Test-Path $Zip) { Remove-Item $Zip -Force }

Add-Type -AssemblyName System.IO.Compression.FileSystem

# Entries are written one at a time, with the separator forced to "/".
# CreateFromDirectory on Windows PowerShell stores native backslashes, which is
# outside the ZIP specification: Linux unzip then produces single files literally
# named "app\Models\User.php" instead of a directory tree, and the extracted site
# is a heap of files with no folders.
$zipStream = [System.IO.Compression.ZipFile]::Open($Zip, 'Create')
try {
    $prefix = $Stage.TrimEnd('\').Length + 1
    foreach ($file in Get-ChildItem -Path $Stage -Recurse -File -Force) {
        $entry = $file.FullName.Substring($prefix).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $zipStream, $file.FullName, $entry,
            [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
}
finally { $zipStream.Dispose() }

Remove-Item $Stage -Recurse -Force

# --------------------------------------------------------------- report ----
$size = '{0:N1} MB' -f ((Get-Item $Zip).Length / 1MB)
Step 'Done'
Write-Host "    $Zip  ($size)" -ForegroundColor Green

# The archive is about to be extracted over a live site, so confirm the three
# things that must never be in it actually are not, rather than trusting the
# exclude lists above to have stayed correct.
$archive = [System.IO.Compression.ZipFile]::OpenRead($Zip)
try {
    $names = $archive.Entries.FullName

    # Matched exactly, not as a prefix: ".env*" would also flag .env.example,
    # which is a template with no secrets in it and is meant to ship.
    foreach ($guard in @('.env', 'bootstrap/cache/config.php', 'bootstrap/cache/routes-v7.php')) {
        if ($names -contains $guard) { throw "REFUSING TO SHIP: archive contains $guard" }
    }
    if ($names | Where-Object { $_ -like 'storage/app/*' -and $_ -notlike '*/.gitignore' }) {
        throw 'REFUSING TO SHIP: archive contains uploaded files under storage/app'
    }
    if ($names | Where-Object { $_ -like '*\*' }) {
        throw 'REFUSING TO SHIP: archive has backslash entry names; it will not extract on Linux'
    }

    Write-Host "    $($names.Count) files, POSIX paths; no .env, uploads or stale caches." -ForegroundColor DarkGray
}
finally { $archive.Dispose() }

Write-Host ''
Write-Host '    Extract into httpdocs, then run over SSH:' -ForegroundColor DarkGray
Write-Host '      php artisan migrate --force && php artisan optimize:clear && php artisan optimize' -ForegroundColor DarkGray

# robocopy signals success with exit codes 1-7, and PowerShell would otherwise
# hand that on as this script's own status and make a good build look failed.
exit 0
