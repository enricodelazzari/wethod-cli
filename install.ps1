# Wethod CLI installer for Windows.
#
#   irm https://raw.githubusercontent.com/enricodelazzari/wethod-cli/main/install.ps1 | iex
#
# Environment variables:
#   WETHOD_INSTALL_DIR   Directory to install into (default: %LOCALAPPDATA%\wethod\bin).
#   WETHOD_VERSION       Release tag to install (default: the latest release).

$ErrorActionPreference = 'Stop'

$repo = 'enricodelazzari/wethod-cli'
$platform = 'windows-x64.exe'

function Write-Info($msg) { Write-Host "==> $msg" -ForegroundColor Cyan }

# Resolve the release tag to install.
$tag = $env:WETHOD_VERSION
if (-not $tag) {
    Write-Info 'Resolving the latest release...'
    $release = Invoke-RestMethod "https://api.github.com/repos/$repo/releases/latest" `
        -Headers @{ 'User-Agent' = 'wethod-cli' }
    $tag = $release.tag_name
}
if (-not $tag) { throw 'Could not determine the latest release.' }

$asset = "wethod-$tag-$platform"
$url = "https://github.com/$repo/releases/download/$tag/$asset"

# Pick an install directory.
$installDir = $env:WETHOD_INSTALL_DIR
if (-not $installDir) { $installDir = Join-Path $env:LOCALAPPDATA 'wethod\bin' }
New-Item -ItemType Directory -Force -Path $installDir | Out-Null

$target = Join-Path $installDir 'wethod.exe'

Write-Info "Downloading wethod $tag ($platform)..."
Invoke-WebRequest -Uri $url -OutFile $target -UseBasicParsing

Write-Info "Installed wethod to $target"

# Add the install directory to the user PATH if it is missing.
$userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
if (($userPath -split ';') -notcontains $installDir) {
    [Environment]::SetEnvironmentVariable('Path', "$userPath;$installDir", 'User')
    Write-Host "note: Added $installDir to your PATH. Restart your terminal to use 'wethod'." -ForegroundColor Yellow
}

Write-Info "Run 'wethod login' to get started."
