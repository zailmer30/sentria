<?php

namespace App\Services\Sessions;

use RuntimeException;
use ZipArchive;

class ChamberCaptureStarter
{
    public function __construct(
        private readonly ChamberCaptureTokenService $tokens,
    ) {}

    /**
     * Build a zip the chamber PC can unzip and double-click. Contains a fresh
     * recording key — the previous key of the same name stops working.
     */
    public function zipPath(string $baseUrl): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP zip support is not installed on this server.');
        }

        $token = $this->tokens->issue();
        $url = rtrim($baseUrl, '/');
        $capture = base_path('services/chamber-capture');
        $python = $capture.DIRECTORY_SEPARATOR.'capture.py';
        $requirements = $capture.DIRECTORY_SEPARATOR.'requirements.txt';
        $bootstrap = $capture.DIRECTORY_SEPARATOR.'bootstrap-python.ps1';

        if (! is_readable($python) || ! is_readable($requirements) || ! is_readable($bootstrap)) {
            throw new RuntimeException('Chamber capture files are missing from this install.');
        }

        $path = tempnam(sys_get_temp_dir(), 'sentria-chamber-');

        if ($path === false) {
            throw new RuntimeException('Could not create a temporary file.');
        }

        $zipPath = $path.'.zip';
        @unlink($path);

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the recording starter.');
        }

        $zip->addFile($python, 'capture.py');
        $zip->addFile($requirements, 'requirements.txt');
        $zip->addFile($bootstrap, 'bootstrap-python.ps1');
        $zip->addFromString('start.bat', $this->windowsBatch($url, $token));
        $zip->addFromString('start.ps1', $this->windowsPowershell($url, $token));
        $zip->addFromString('start.sh', $this->unixShell($url, $token));
        $zip->setExternalAttributesName('start.sh', ZipArchive::OPSYS_UNIX, 0100755 << 16);
        $zip->addFromString('README.txt', $this->readme($url));
        $zip->close();

        return $zipPath;
    }

    private function windowsBatch(string $url, string $token): string
    {
        $url = str_replace('%', '%%', $url);
        $token = str_replace('%', '%%', $token);

        return <<<BAT
@echo off
setlocal
cd /d "%~dp0"
echo Setting up the recording program. The first run can take a minute.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0bootstrap-python.ps1"
if errorlevel 1 (
  echo.
  echo Setup did not finish. Read the lines above.
  pause
  exit /b 1
)
if not exist "%~dp0python.path" (
  echo Python setup did not write python.path.
  pause
  exit /b 1
)
set /p PYTHON=<"%~dp0python.path"
set "SENTRIA_URL={$url}"
set "SENTRIA_CHAMBER_TOKEN={$token}"
echo Starting. Leave this window open.
"%PYTHON%" capture.py
echo.
pause
BAT;
    }

    private function windowsPowershell(string $url, string $token): string
    {
        $url = str_replace("'", "''", $url);
        $token = str_replace("'", "''", $token);

        return <<<PS1
Set-Location -LiteralPath \$PSScriptRoot
powershell -NoProfile -ExecutionPolicy Bypass -File "\$PSScriptRoot\\bootstrap-python.ps1"
if (\$LASTEXITCODE -ne 0) { exit \$LASTEXITCODE }
\$python = (Get-Content -LiteralPath "\$PSScriptRoot\\python.path" -TotalCount 1).Trim()
\$env:SENTRIA_URL = '{$url}'
\$env:SENTRIA_CHAMBER_TOKEN = '{$token}'
& \$python capture.py
PS1;
    }

    private function unixShell(string $url, string $token): string
    {
        $url = str_replace("'", "'\\''", $url);
        $token = str_replace("'", "'\\''", $token);

        return <<<SH
#!/bin/sh
cd "\$(dirname "\$0")"
if ! command -v python3 >/dev/null 2>&1; then
  echo "Install Python 3 first."
  exit 1
fi
if [ ! -d .venv ]; then
  python3 -m venv .venv
fi
. .venv/bin/activate
python -m pip install -q -r requirements.txt
export SENTRIA_URL='{$url}'
export SENTRIA_CHAMBER_TOKEN='{$token}'
python capture.py
SH;
    }

    private function readme(string $url): string
    {
        return <<<TXT
Sentria chamber recording

1. Copy this folder to the computer that has the seat audio box plugged in.
2. Double-click start.bat (Windows) or run ./start.sh (Linux).
3. The first Windows run downloads Python if this PC does not already have it. That needs internet once.
4. Leave that window open. Sentria uses this computer when a sitting is recording.

This starter calls: {$url}
If the chamber computer cannot open that address, open Sentria from an address that computer can reach, then download again.

A new recording key is inside this folder. Do not email or print it. Downloading again replaces the old key.
TXT;
    }
}
