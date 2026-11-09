#Requires -Version 7.0
param(
    [string]$Adb='C:/Android/Sdk/platform-tools/adb.exe',
    [string]$Serial='emulator-5554',
    [switch]$Compact,
    [Parameter(Mandatory=$true)][string]$OutputDirectory
)
$ErrorActionPreference='Stop'
if ($Serial -notmatch '^emulator-\d+$') { throw 'Anonymous login QA is emulator-only' }
$package='hu.harmat22.crm.mobile'
[void](New-Item -ItemType Directory -Force -Path $OutputDirectory)
function Run([string[]]$Command) {
    $result=& $Adb -s $Serial @Command
    if ($LASTEXITCODE -ne 0) { throw "adb failed: $($Command -join ' ')" }; return $result
}
function Dump([string]$Name) {
    for ($attempt=0; $attempt -lt 3; $attempt++) {
        $raw=(Run @('exec-out','uiautomator','dump','/dev/tty')) -join "`n"
        $start=$raw.IndexOf('<?xml'); $end=$raw.LastIndexOf('</hierarchy>')
        if ($start -ge 0 -and $end -gt $start) {
            $xml=$raw.Substring($start,$end+12-$start)
            [IO.File]::WriteAllText((Join-Path $OutputDirectory "$Name.xml"),$xml)
            return [xml]$xml
        }
        Start-Sleep -Milliseconds 300
    }
    throw 'No UI tree after bounded retries'
}
function Tap($Node) {
    if (-not $Node) { throw 'Missing tap target' }
    $b=[regex]::Matches($Node.bounds,'\d+') | ForEach-Object { [int]$_.Value }
    if ($b.Count -ne 4 -or $b[2] -le $b[0] -or $b[3] -le $b[1]) { throw 'Target is offscreen' }
    [void](Run @('shell','input','tap',[string][int](($b[0]+$b[2])/2),[string][int](($b[1]+$b[3])/2)))
}
$size=(Run @('shell','wm','size')) -join "`n"; $density=(Run @('shell','wm','density')) -join "`n"
$oldSize=if($size -match 'Override size: (\d+x\d+)'){$Matches[1]}else{'reset'}
$oldDensity=if($density -match 'Override density: (\d+)'){$Matches[1]}else{'reset'}
$ime=((Run @('shell','settings','get','secure','show_ime_with_hard_keyboard')) -join '').Trim()
try {
    if ($Compact) { [void](Run @('shell','wm','size','720x1280')); [void](Run @('shell','wm','density','320')) }
    [void](Run @('shell','settings','put','secure','show_ime_with_hard_keyboard','1'))
    [void](Run @('shell','am','force-stop',$package))
    [void](Run @('shell','am','start','-W','-n',"$package/hu.harmat22.crm.mobile.MainActivity",'--ez','demo','true'))
    $gate=Dump 'release-gate'; $nodes=$gate.SelectNodes('//node')
    if ($gate.OuterXml.Contains('测试数据') -or $gate.OuterXml.Contains('text="指纹解锁"')) { throw 'Requires a fresh release profile, without saved real sessions' }
    Tap ($nodes | Where-Object {$_.text -eq '账号登录'} | Select-Object -First 1)
    $password=$null
    for ($attempt=0; $attempt -lt 10; $attempt++) {
        $tree=Dump 'release-form'; $nodes=$tree.SelectNodes('//node')
        $password=$nodes | Where-Object {$_.password -eq 'true' -and $_.bounds -ne '[0,0][0,0]'} | Select-Object -First 1
        if ($password) {
            $p=[regex]::Matches($password.bounds,'\d+') | ForEach-Object { [int]$_.Value }
            if ($p.Count -eq 4 -and $p[3] -gt $p[1]) { break }; $password=$null
        }
        $web=$nodes | Where-Object {$_.class -eq 'android.webkit.WebView' -and $_.scrollable -eq 'true'} | Select-Object -First 1
        if ($web) {
            $b=[regex]::Matches($web.bounds,'\d+') | ForEach-Object { [int]$_.Value }; $x=[string][int](($b[0]+$b[2])/2)
            [void](Run @('shell','input','swipe',$x,[string][int]($b[1]+0.8*($b[3]-$b[1])),$x,[string][int]($b[1]+0.2*($b[3]-$b[1])),'450'))
        }
    }
    if (-not $password) { throw 'Official login password field could not be scrolled into view' }
    Tap $password
    $focused=Dump 'release-keyboard'
    if (-not ($focused.SelectNodes('//node') | Where-Object {$_.password -eq 'true' -and $_.focused -eq 'true'})) { throw 'Password field cannot receive focus' }
    [void](Run @('shell','input','keyevent','4'))
    $tree=Dump 'before-cancel'
    Tap ($tree.SelectNodes('//node') | Where-Object {$_.text -eq '取消登录'} | Select-Object -First 1)
    $tree=Dump 'cancelled'
    if (-not ($tree.SelectNodes('//node') | Where-Object {$_.text -eq '公司销售后台'})) { throw 'Cancel did not return to gate' }
    Write-Output 'PASS signed-release demo isolation, official login scroll, password focus and cancel; no credentials or submission'
} finally {
    [void](Run @('shell','settings','put','secure','show_ime_with_hard_keyboard',$ime))
    if ($Compact) { [void](Run @('shell','wm','size',$oldSize)); [void](Run @('shell','wm','density',$oldDensity)) }
}
