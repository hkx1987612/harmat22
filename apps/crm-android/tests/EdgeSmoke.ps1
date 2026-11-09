#Requires -Version 7.0
param(
    [string]$Adb='C:/Android/Sdk/platform-tools/adb.exe',
    [string]$Serial='emulator-5554',
    [Parameter(Mandatory=$true)][string]$OutputDirectory
)
$ErrorActionPreference='Stop'
# Font screenshots still require visual review; stale dialogs are now regression failures.
if ($Serial -notmatch '^emulator-\d+$') {throw 'Emulator only'}
$qa='hu.harmat22.crm.mobile.qa'; $release='hu.harmat22.crm.mobile'
[void](New-Item -ItemType Directory -Force -Path $OutputDirectory)
function Run([string[]]$Command) {
    $result=& $Adb -s $Serial @Command
    if ($LASTEXITCODE -ne 0) {throw "adb failed: $($Command -join ' ')"}; return $result
}
function Dump([string]$Name) {
    for ($i=0; $i -lt 3; $i++) {
        $raw=(Run @('exec-out','uiautomator','dump','/dev/tty')) -join "`n"
        $start=$raw.IndexOf('<?xml'); $end=$raw.LastIndexOf('</hierarchy>')
        if ($start -ge 0 -and $end -gt $start) {
            $xml=$raw.Substring($start,$end+12-$start)
            [IO.File]::WriteAllText((Join-Path $OutputDirectory "$Name.xml"),$xml)
            return [xml]$xml
        }
    }
    throw 'UI tree unavailable'
}
function Tap([string]$Label) {
    $tree=Dump 'tap'
    $node=$tree.SelectNodes('//node') | Where-Object {$_.text -eq $Label -or $_.'content-desc' -eq $Label} | Select-Object -First 1
    if (-not $node) {throw "Missing $Label"}
    $b=[regex]::Matches($node.bounds,'\d+') | ForEach-Object {[int]$_.Value}
    [void](Run @('shell','input','tap',[string][int](($b[0]+$b[2])/2),[string][int](($b[1]+$b[3])/2)))
}
function Screen([string]$Name) {
    [void](Dump $Name)
    [void](Run @('shell','screencap','-p','/sdcard/harmat-edge.png'))
    [void](Run @('pull','/sdcard/harmat-edge.png',(Join-Path $OutputDirectory "$Name.png")))
}
function StartQa { [void](Run @('shell','am','start','-W','-n',"$qa/hu.harmat22.crm.mobile.MainActivity",'--ez','demo','true')) }
$font=((Run @('shell','settings','get','system','font_scale')) -join '').Trim()
$size=(Run @('shell','wm','size')) -join "`n"; $density=(Run @('shell','wm','density')) -join "`n"
$oldSize=if($size -match 'Override size: (\d+x\d+)'){$Matches[1]}else{'reset'}
$oldDensity=if($density -match 'Override density: (\d+)'){$Matches[1]}else{'reset'}
$wifi=((Run @('shell','settings','get','global','wifi_on')) -join '').Trim()
$data=((Run @('shell','settings','get','global','mobile_data')) -join '').Trim()
try {
    [void](Run @('shell','pm','clear',$qa)); StartQa
    Tap '查看测试数据'; Tap '设置'; Tap '中文 / Magyar'
    [void](Run @('shell','wm','size','640x1280')); [void](Run @('shell','wm','density','320'))
    [void](Run @('shell','settings','put','system','font_scale','2.0'))
    Tap 'Mintaadatok megnyitása'; Tap 'Ügyfelek'; Screen 'font200-320dp-hu'
    [void](Run @('shell','settings','put','system','font_scale','1.0'))
    Tap 'Mintaadatok megnyitása'; Tap 'Ügyfelek'; Tap 'Ügyfelek'; Tap 'DEMO Client 401 adatlap'
    [void](Run @('shell','input','keyevent','3')); [void](Dump 'home-detail'); StartQa
    $locked=Dump 'detail-after-background'
    if ($locked.OuterXml.Contains('DEMO Client')) {throw 'Private detail survived background lock'}
    Tap 'Mintaadatok megnyitása'; Tap 'Beállítások'
    [void](Run @('shell','input','keyevent','3')); [void](Dump 'home-menu'); StartQa
    $menu=Dump 'menu-after-background'
    $stale=$menu.OuterXml.Contains('Kilépés ezen az eszközön')
    if ($stale) {throw 'Authenticated settings menu survived background lock'}
    if (-not $menu.OuterXml.Contains('Értékesítési központ')) {throw 'Locked gate missing after menu background'}
    Tap 'Mintaadatok megnyitása'; Tap 'Beállítások'; Tap 'Kilépés ezen az eszközön'
    [void](Run @('shell','input','keyevent','3')); [void](Dump 'home-confirm'); StartQa
    $confirm=Dump 'confirm-after-background'
    if ($confirm.OuterXml.Contains('Törli az eszköz belépését?')) {throw 'Old signout confirmation survived lock'}
    [void](Run @('shell','am','force-stop',$qa))
    [void](Run @('shell','svc','wifi','disable')); [void](Run @('shell','svc','data','disable'))
    [void](Run @('shell','am','start','-W','-n',"$release/hu.harmat22.crm.mobile.MainActivity"))
    Tap '账号登录'
    $offline=$false
    for ($i=0; $i -lt 12; $i++) {
        $tree=Dump 'offline-login'
        if ($tree.OuterXml.Contains('登录页暂时无法打开')) {$offline=$true; break}
    }
    if (-not $offline) {throw 'Offline login did not reach its bounded error state'}
    Write-Output 'PASS offline login, background detail/menu/confirmation clearing; large-font evidence captured'
} finally {
    [void](Run @('shell','settings','put','system','font_scale',$font))
    [void](Run @('shell','wm','size',$oldSize)); [void](Run @('shell','wm','density',$oldDensity))
    $wifiMode=if($wifi -eq '1'){'enable'}else{'disable'}
    $dataMode=if($data -eq '1'){'enable'}else{'disable'}
    [void](Run @('shell','svc','wifi',$wifiMode)); [void](Run @('shell','svc','data',$dataMode))
}
