#Requires -Version 7.0
param(
    [string]$AndroidSdk='C:/Android/Sdk',
    [string]$OutputDirectory=(Join-Path $PSScriptRoot '../../outputs/2026-11-09-crm-android'),
    [switch]$SkipBuild
)
$ErrorActionPreference='Stop'
$keyDirectory=Join-Path $env:LOCALAPPDATA 'HarmatCRM/Signing'
$keystore=Join-Path $keyDirectory 'harmat-crm-mobile.p12'
$passwordFile=Join-Path $keyDirectory 'password.dpapi.xml'
[void](New-Item -ItemType Directory -Force -Path $keyDirectory,$OutputDirectory)
if (-not (Test-Path -LiteralPath $passwordFile)) {
    if (Test-Path -LiteralPath $keystore) { throw 'Signing password is missing; do not replace the existing app identity.' }
    $bytes=New-Object byte[] 32
    [Security.Cryptography.RandomNumberGenerator]::Fill($bytes)
    $secure=ConvertTo-SecureString ([Convert]::ToBase64String($bytes)) -AsPlainText -Force
    $secure | Export-Clixml -LiteralPath $passwordFile
}
$secure=Import-Clixml -LiteralPath $passwordFile
$pointer=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
try {
    $env:HARMAT_CRM_KEY_PASSWORD=[Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    if (-not (Test-Path -LiteralPath $keystore)) {
        & keytool -genkeypair -keystore $keystore -storetype PKCS12 -alias harmat-crm -keyalg RSA -keysize 3072 -validity 10000 -dname 'CN=Harmat CRM, O=Harmat Lakopark, C=HU' -storepass:env HARMAT_CRM_KEY_PASSWORD -keypass:env HARMAT_CRM_KEY_PASSWORD
        if ($LASTEXITCODE -ne 0) { throw 'Signing identity creation failed' }
    }
    Push-Location $PSScriptRoot
    try {
        if (-not $SkipBuild) {
            $env:ANDROID_HOME=$AndroidSdk
            $env:JAVA_TOOL_OPTIONS="-Djdk.net.unixdomain.tmpdir=$($PSScriptRoot.Replace('\','/'))/build/no-unix-sockets"
            & gradle :mobile:assembleRelease :mobile:lintRelease --offline --console=plain --quiet
            if ($LASTEXITCODE -ne 0) { throw 'Release build/lint failed' }
        }
        $tools=Join-Path $AndroidSdk 'build-tools/35.0.0'
        $aligned=Join-Path $OutputDirectory 'mobile-aligned-unsigned.apk'
        $apk=Join-Path $OutputDirectory 'Harmat-CRM-Android-1.0.0.apk'
        & "$tools/zipalign.exe" -f -p 4 'mobile/build/outputs/apk/release/mobile-release-unsigned.apk' $aligned
        if ($LASTEXITCODE -ne 0) { throw 'APK alignment failed' }
        & "$tools/apksigner.bat" sign --ks $keystore --ks-key-alias harmat-crm --ks-pass env:HARMAT_CRM_KEY_PASSWORD --key-pass env:HARMAT_CRM_KEY_PASSWORD --out $apk $aligned
        if ($LASTEXITCODE -ne 0) { throw 'APK signing failed' }
        & "$tools/apksigner.bat" verify --verbose --print-certs $apk
        if ($LASTEXITCODE -ne 0) { throw 'APK signature verification failed' }
        $metadata=(& "$tools/aapt.exe" dump badging $apk) -join "`n"
        if ($LASTEXITCODE -ne 0 -or $metadata.Contains('application-debuggable')) { throw 'Invalid release metadata' }
        Add-Type -AssemblyName System.IO.Compression.FileSystem
        $archive=[IO.Compression.ZipFile]::OpenRead($apk)
        try {
            if ($archive.Entries | Where-Object { $_.FullName -like '*crm-fixtures*' }) { throw 'Test fixtures leaked into release' }
            if (-not $archive.GetEntry('assets/THIRD_PARTY_NOTICES.txt')) { throw 'Dependency license notice is missing' }
        } finally { $archive.Dispose() }
        Get-FileHash -LiteralPath $apk -Algorithm SHA256 | Select-Object Path,Hash
    } finally { Pop-Location }
} finally {
    Remove-Item Env:HARMAT_CRM_KEY_PASSWORD -ErrorAction SilentlyContinue
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
}
