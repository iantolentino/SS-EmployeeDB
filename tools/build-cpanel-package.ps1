param([string]$OutputDirectory = 'dist')
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$outputRoot = [System.IO.Path]::GetFullPath((Join-Path $projectRoot $OutputDirectory))
if (-not $outputRoot.StartsWith($projectRoot + [System.IO.Path]::DirectorySeparatorChar, [System.StringComparison]::OrdinalIgnoreCase)) { throw 'OutputDirectory must be inside this project.' }
$stagePath = Join-Path $projectRoot ('.local\cpanel-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $stagePath,(Join-Path $stagePath 'vendor'),$outputRoot -Force | Out-Null
$appFiles = @('index.php','bootstrap.php','views.php','.htaccess','employee-db-ui.css','employee-db-app.css','employee-db-app.js','README.md','DEPLOYMENT.md')
foreach ($file in $appFiles) { Copy-Item -LiteralPath (Join-Path $projectRoot $file) -Destination (Join-Path $stagePath $file) }
foreach ($file in @('htmx.min.js','LICENSE')) { Copy-Item -LiteralPath (Join-Path $projectRoot ('vendor\' + $file)) -Destination (Join-Path $stagePath ('vendor\' + $file)) }
if (Test-Path -LiteralPath (Join-Path $projectRoot 'assets')) {
    New-Item -ItemType Directory -Path (Join-Path $stagePath 'assets') -Force | Out-Null
    Get-ChildItem -LiteralPath (Join-Path $projectRoot 'assets') -File | Where-Object { $_.Extension -in @('.png','.svg','.webp','.jpg','.jpeg','.ico') } | ForEach-Object { Copy-Item -LiteralPath $_.FullName -Destination (Join-Path $stagePath ('assets\' + $_.Name)) }
}
$keyBytes = New-Object byte[] 32
$random = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$random.GetBytes($keyBytes)
$random.Dispose()
$setupKey = [System.BitConverter]::ToString($keyBytes).Replace('-','').ToLowerInvariant()
$configuration = "<?php`nreturn [`n    'database_path' => dirname(__DIR__, 3) . '/employee-db-private/employees.sqlite',`n    'timezone' => 'Asia/Manila',`n    'seed_demo' => false,`n    'setup_token' => '$setupKey',`n];`n"
$utf8 = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText((Join-Path $stagePath 'config.php'), $configuration, $utf8)
[System.IO.File]::WriteAllText((Join-Path $outputRoot 'SETUP-KEY.txt'), "First administrator setup key:`r`n$setupKey`r`n`r`nUse at https://stratastaff.com/employee/db/ after extracting the matching ZIP.`r`nKeep this file private. Do not upload it.`r`n", $utf8)
$archive = Join-Path $outputRoot 'SS-EmployeeDB-cpanel.zip'
$stageArchive = $stagePath + '.zip'
Add-Type -AssemblyName System.IO.Compression.FileSystem
Add-Type -AssemblyName System.IO.Compression
$archiveStream = [System.IO.File]::Open($stageArchive, [System.IO.FileMode]::CreateNew)
$zip = New-Object System.IO.Compression.ZipArchive($archiveStream, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($file in (Get-ChildItem -LiteralPath $stagePath -Recurse -File)) {
        $entryName = $file.FullName.Substring($stagePath.Length + 1).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $entryName, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally { $zip.Dispose(); $archiveStream.Dispose() }
Copy-Item -LiteralPath $stageArchive -Destination $archive -Force
$temporaryRoot = [System.IO.Path]::GetFullPath((Join-Path $projectRoot '.local'))
foreach ($generatedPath in @($stagePath, $stageArchive)) {
    $resolvedGeneratedPath = [System.IO.Path]::GetFullPath($generatedPath)
    if (-not $resolvedGeneratedPath.StartsWith($temporaryRoot + [System.IO.Path]::DirectorySeparatorChar, [System.StringComparison]::OrdinalIgnoreCase)) { throw 'Generated path escaped .local.' }
    Remove-Item -LiteralPath $resolvedGeneratedPath -Recurse -Force
}
Write-Output "Upload package: $archive"
Write-Output "Setup key: $(Join-Path $outputRoot 'SETUP-KEY.txt')"
