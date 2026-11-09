#Requires -Version 7.0
param(
    [string]$Adb='C:/Android/Sdk/platform-tools/adb.exe',
    [string]$Serial='emulator-5554',
    [switch]$Compact,
    [Parameter(Mandatory=$true)][string]$OutputDirectory
)
$ErrorActionPreference='Stop'
if ($Serial -notmatch '^emulator-\d+$') { throw 'Synthetic QA is emulator-only' }
$package='hu.harmat22.crm.mobile.qa'
$script:checks=0
[void](New-Item -ItemType Directory -Force -Path $OutputDirectory)
function Run([string[]]$Command) {
    $value=& $Adb -s $Serial @Command
    if ($LASTEXITCODE -ne 0) { throw "adb failed: $($Command -join ' ')" }
    return $value
}
function Dump([string]$Name='current') {
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
    throw 'No Android UI tree after bounded retries'
}
function Find([xml]$Tree,[string]$Label) {
    return $Tree.SelectNodes('//node') | Where-Object { $_.text -eq $Label -or $_.'content-desc' -eq $Label } | Select-Object -First 1
}
function Tap([string]$Label) {
    $tree=Dump 'before-tap'; $node=Find $tree $Label
    if (-not $node) { throw "Missing tap target: $Label" }
    $bounds=[regex]::Matches($node.bounds,'\d+') | ForEach-Object { [int]$_.Value }
    [void](Run @('shell','input','tap',[string][int](($bounds[0]+$bounds[2])/2),[string][int](($bounds[1]+$bounds[3])/2)))
}
function Assert([string]$Label) {
    $tree=Dump 'assert'
    if (-not (Find $tree $Label)) { throw "Missing UI: $Label" }
    $script:checks++
}
function AssertContains([string]$Value) {
    for ($attempt=0; $attempt -lt 6; $attempt++) {
        $tree=Dump 'assert-contains'
        if ($tree.SelectNodes('//node') | Where-Object { $_.text.Contains($Value) }) { $script:checks++; return }
        $scroll=$tree.SelectNodes('//node[@scrollable="true"]') | Select-Object -First 1
        if (-not $scroll) { break }
        $b=[regex]::Matches($scroll.bounds,'\d+') | ForEach-Object { [int]$_.Value }
        $x=[string][int](($b[0]+$b[2])/2)
        $start=[string][int]($b[1]+0.8*($b[3]-$b[1]))
        $end=[string][int]($b[1]+0.2*($b[3]-$b[1]))
        [void](Run @('shell','input','swipe',$x,$start,$x,$end,'350'))
    }
    throw "Missing value after scrolling: $Value"
}
function Screen([string]$Name) {
    [void](Dump $Name)
    [void](Run @('shell','screencap','-p','/sdcard/harmat-mobile-qa.png'))
    [void](Run @('pull','/sdcard/harmat-mobile-qa.png',(Join-Path $OutputDirectory "$Name.png")))
}
$font=((Run @('shell','settings','get','system','font_scale')) -join '').Trim()
$ime=((Run @('shell','settings','get','secure','show_ime_with_hard_keyboard')) -join '').Trim()
$size=(Run @('shell','wm','size')) -join "`n"; $density=(Run @('shell','wm','density')) -join "`n"
$oldSize=if($size -match 'Override size: (\d+x\d+)'){$Matches[1]}else{'reset'}
$oldDensity=if($density -match 'Override density: (\d+)'){$Matches[1]}else{'reset'}
try {
    if($Compact){ [void](Run @('shell','wm','size','720x1280')); [void](Run @('shell','wm','density','320')) }
    [void](Run @('shell','settings','put','system','font_scale','1.0'))
    [void](Run @('shell','settings','put','secure','show_ime_with_hard_keyboard','1'))
    [void](Run @('shell','am','force-stop',$package))
    [void](Run @('shell','pm','clear',$package))
    [void](Run @('shell','am','start','-W','-n',"$package/hu.harmat22.crm.mobile.MainActivity",'--ez','demo','true'))
    Assert '公司销售后台'; Assert '测试数据 · 不连接正式CRM'
    Tap '查看测试数据'; Assert '销售概览'; Assert '每日访问'; Screen 'overview-zh'
    Tap '客户'; Assert '询价汇总'; Assert 'DEMO Inquiry 102'
    Tap '搜索记录'; [void](Run @('shell','input','text','missing-record'))
    [void](Run @('shell','input','keyevent','66')); Assert '没有匹配的记录'
    Tap '跟单'; Assert '销售跟单'; Assert 'DEMO Deal 202'; Screen 'deals-zh'
    Tap 'DEMO Deal 202 详情'; AssertContains 'CRM: DEMO-202'; Tap '关闭'
    Tap '房源'; Assert '房源库存'; Assert 'DEMO-A1-01'; Tap 'DEMO-A1-01 详情'; AssertContains '价格待议'; Tap '关闭'
    Tap '客户'; Tap '客户档案'; Assert 'DEMO Client 401'; Screen 'clients-zh'
    Tap '设置'; Tap '中文 / Magyar'; Assert 'Ügyféladatok'; Assert 'DEMO Client 401'; Screen 'clients-hu'
    Tap 'Zárolás'; Assert 'Értékesítési központ'
    if ((Dump).OuterXml.Contains('DEMO Client')) { throw 'Customer data visible while locked' }
    $script:checks++
    Tap 'Mintaadatok megnyitása'; Tap 'Áttekintés'; Assert 'Értékesítési áttekintés'; Screen 'overview-hu'
    [void](Run @('shell','settings','put','system','font_scale','1.3'))
    Assert 'Értékesítési központ'; Tap 'Mintaadatok megnyitása'
    Tap 'Ügyfelek'; Assert 'Érdeklődések'; Screen 'font130-hu'
    [void](Run @('shell','input','keyevent','3'))
    [void](Dump 'home')
    [void](Run @('shell','am','start','-W','-n',"$package/hu.harmat22.crm.mobile.MainActivity",'--ez','demo','true'))
    Assert 'Értékesítési központ'
    Tap 'Beállítások'; Tap '中文 / Magyar'; Assert '公司销售后台'
    $crashes=(Run @('logcat','-d','-b','crash','-v','brief')) -join "`n"
    if ($crashes -match 'Process: hu\.harmat22\.crm\.mobile') { throw 'App crash recorded' }
    Write-Output "PASS $script:checks native UI and background-lock checks"
} finally {
    [void](Run @('shell','settings','put','system','font_scale',$font))
    [void](Run @('shell','settings','put','secure','show_ime_with_hard_keyboard',$ime))
    if($Compact){ [void](Run @('shell','wm','size',$oldSize)); [void](Run @('shell','wm','density',$oldDensity)) }
}
