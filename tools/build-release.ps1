param([Parameter(Mandatory=$true)][ValidatePattern('^\d+\.\d+\.\d+$')][string]$Version)
$ErrorActionPreference='Stop'
$projectRoot=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$versionFile=(Get-Content -LiteralPath (Join-Path $projectRoot 'VERSION') -Raw).Trim()
if($versionFile -ne $Version){throw "VERSION contains '$versionFile', expected '$Version'."}
$status=git -C $projectRoot status --porcelain
if($status){throw 'The Git working tree must be clean before building a release.'}
$outputDir=Join-Path $projectRoot 'dist'
New-Item -ItemType Directory -Path $outputDir -Force|Out-Null
$staging=Join-Path ([System.IO.Path]::GetTempPath()) ('filamentmanager-release-'+[guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $staging|Out-Null
try{
    $archiveRoot=Join-Path $staging ('filamentmanager-server-'+$Version)
    New-Item -ItemType Directory -Path $archiveRoot|Out-Null
    $sourceArchive=Join-Path $staging 'source.zip'
    git -C $projectRoot archive --format=zip "--output=$sourceArchive" HEAD
    if($LASTEXITCODE -ne 0){throw "git archive failed with exit code $LASTEXITCODE."}
    Expand-Archive -LiteralPath $sourceArchive -DestinationPath $archiveRoot
    $zipPath=Join-Path $outputDir ('filamentmanager-server-'+$Version+'.zip')
    if(Test-Path -LiteralPath $zipPath){Remove-Item -LiteralPath $zipPath -Force}
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zip=[System.IO.Compression.ZipFile]::Open($zipPath,[System.IO.Compression.ZipArchiveMode]::Create)
    try{
        $rootName=[System.IO.Path]::GetFileName($archiveRoot)
        foreach($file in Get-ChildItem -LiteralPath $archiveRoot -Recurse -File){
            $relative=$file.FullName.Substring($archiveRoot.Length+1).Replace('\','/')
            $entryName=$rootName+'/'+$relative
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip,$file.FullName,$entryName,[System.IO.Compression.CompressionLevel]::Optimal)|Out-Null
        }
    }finally{$zip.Dispose()}
    $hash=(Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()
    [System.IO.File]::WriteAllText($zipPath+'.sha256',$hash+'  '+[System.IO.Path]::GetFileName($zipPath)+[Environment]::NewLine,[System.Text.UTF8Encoding]::new($false))
    Write-Output $zipPath
    Write-Output ($zipPath+'.sha256')
}finally{if(Test-Path -LiteralPath $staging){Remove-Item -LiteralPath $staging -Recurse -Force}}
