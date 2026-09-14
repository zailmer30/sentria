# Downloads a portable CPython into .\runtime when this PC has no real Python.
# Ignores the Microsoft Store python.exe stub that only opens the Store.

$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot

$Runtime = Join-Path $PSScriptRoot 'runtime'
$Python = Join-Path $Runtime 'python.exe'
$PyVersion = '3.12.10'

function Test-RealPython {
    param([string] $Exe)
    if (-not $Exe -or -not (Test-Path -LiteralPath $Exe)) {
        return $false
    }
    if ($Exe -match '\\WindowsApps\\') {
        return $false
    }
    try {
        $output = & $Exe -c "import sys; print(sys.version_info[0])" 2>$null
        return ($LASTEXITCODE -eq 0 -and $output -match '^3$')
    } catch {
        return $false
    }
}

function Get-SystemPython {
    foreach ($command in @('py', 'python3', 'python')) {
        $cmd = Get-Command $command -ErrorAction SilentlyContinue
        if (-not $cmd) {
            continue
        }
        $candidate = $cmd.Source
        if ($command -eq 'py') {
            try {
                $listed = & $candidate -3 -c "import sys; print(sys.executable)" 2>$null
                if ($LASTEXITCODE -eq 0 -and $listed) {
                    $candidate = $listed.Trim()
                }
            } catch {
                continue
            }
        }
        if (Test-RealPython $candidate) {
            return $candidate
        }
    }
    return $null
}

function Fetch-File {
    param([string] $Url, [string] $Destination)
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    $partial = "$Destination.part"
    if (Get-Command curl.exe -ErrorAction SilentlyContinue) {
        & curl.exe -fsSL --retry 3 -o $partial $Url
        if ($LASTEXITCODE -ne 0) {
            throw "Download failed: $Url"
        }
    } else {
        Invoke-WebRequest -Uri $Url -OutFile $partial -UseBasicParsing
    }
    Move-Item -Force $partial $Destination
}

function Install-PortablePython {
    New-Item -ItemType Directory -Force -Path $Runtime | Out-Null
    $arch = if ($env:PROCESSOR_ARCHITECTURE -eq 'ARM64') { 'arm64' } else { 'amd64' }
    $zipName = "python-$PyVersion-embed-$arch.zip"
    $zipPath = Join-Path $Runtime $zipName
    $url = "https://www.python.org/ftp/python/$PyVersion/$zipName"

    Write-Host "Downloading Python $PyVersion ($arch). This happens once."
    try {
        Fetch-File -Url $url -Destination $zipPath
    } catch {
        if ($arch -eq 'arm64') {
            Write-Host "ARM64 package missing; trying 64-bit instead."
            $arch = 'amd64'
            $zipName = "python-$PyVersion-embed-$arch.zip"
            $zipPath = Join-Path $Runtime $zipName
            $url = "https://www.python.org/ftp/python/$PyVersion/$zipName"
            Fetch-File -Url $url -Destination $zipPath
        } else {
            throw
        }
    }

    Expand-Archive -LiteralPath $zipPath -DestinationPath $Runtime -Force
    Remove-Item -LiteralPath $zipPath -Force -ErrorAction SilentlyContinue

    Get-ChildItem -LiteralPath $Runtime -Filter 'python3*._pth' | ForEach-Object {
        $text = [System.IO.File]::ReadAllText($_.FullName)
        $text = $text -replace '#import site', 'import site'
        if ($text -notmatch '(?m)^import site') {
            $text = $text.TrimEnd() + "`r`nimport site`r`n"
        }
        [System.IO.File]::WriteAllText($_.FullName, $text)
    }

    if (-not (Test-Path -LiteralPath $Python)) {
        throw 'Portable Python did not unpack python.exe.'
    }

    $getPip = Join-Path $Runtime 'get-pip.py'
    Write-Host 'Installing pip into the portable Python.'
    Fetch-File -Url 'https://bootstrap.pypa.io/get-pip.py' -Destination $getPip
    & $Python $getPip --no-warn-script-location
    if ($LASTEXITCODE -ne 0) {
        throw 'pip setup failed.'
    }
    Remove-Item -LiteralPath $getPip -Force -ErrorAction SilentlyContinue
}

if (-not (Test-RealPython $Python)) {
    $system = Get-SystemPython
    if ($system) {
        Write-Host "Using Python at $system"
        if (-not (Test-Path -LiteralPath (Join-Path $PSScriptRoot '.venv\Scripts\python.exe'))) {
            Write-Host 'Creating a local environment.'
            & $system -m venv (Join-Path $PSScriptRoot '.venv')
            if ($LASTEXITCODE -ne 0) {
                throw 'Could not create .venv. Download a new starter if this folder is read-only.'
            }
        }
        $Python = Join-Path $PSScriptRoot '.venv\Scripts\python.exe'
    } else {
        Write-Host 'No Python on this PC (the Store shortcut does not count).'
        try {
            Install-PortablePython
        } catch {
            Write-Host ''
            Write-Host $_.Exception.Message
            Write-Host 'This computer needs internet once to fetch Python from python.org.'
            Write-Host 'If that is blocked, install Python from python.org (tick Add python.exe to PATH) and run start.bat again.'
            exit 1
        }
    }
}

if (-not (Test-RealPython $Python)) {
    Write-Host 'Python is still missing after setup.'
    exit 1
}

Write-Host 'Installing recording libraries.'
& $Python -m pip install -q -r (Join-Path $PSScriptRoot 'requirements.txt')
if ($LASTEXITCODE -ne 0) {
    Write-Host 'Could not install libraries. Check internet access to pypi.org, then run start.bat again.'
    exit 1
}

$Python | Set-Content -LiteralPath (Join-Path $PSScriptRoot 'python.path') -Encoding ASCII
