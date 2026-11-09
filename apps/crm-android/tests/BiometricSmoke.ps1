#Requires -Version 7.0
param(
    [string]$Adb='C:/Android/Sdk/platform-tools/adb.exe',
    [string]$Serial='emulator-5554',
    [int]$EnrolledId=1,
    [int]$WrongId=2,
    [Parameter(Mandatory=$true)][string]$OutputDirectory
)
$ErrorActionPreference='Stop'
if ($Serial -notmatch '^emulator-\d+$') { throw 'Never simulate biometrics on a physical device' }
$package='hu.harmat22.crm.mobile.qa'
[void](New-Item -ItemType Directory -Force -Path $OutputDirectory)
$script:checks=0
function Run([string[]]$Command) {
    $result=& $Adb -s $Serial @Command
    if ($LASTEXITCODE -ne 0) { throw "adb failure: $($Command -join ' ')" }; return $result
}
function Dump([string]$Name='state') {
    $raw=(Run @('exec-out','uiautomator','dump','/dev/tty')) -join "`n"
    $start=$raw.IndexOf('<?xml'); $end=$raw.LastIndexOf('</hierarchy>')
    if ($start -lt 0 -or $end -lt $start) { throw 'Missing XML' }
    $xml=$raw.Substring($start,$end+12-$start)
    [IO.File]::WriteAllText((Join-Path $OutputDirectory "$Name.xml"),$xml)
    return [xml]$xml
}
function Tap([string]$Text) {
    $tree=Dump 'tap'
    $node=$tree.SelectNodes('//node') | Where-Object { $_.text -eq $Text -or $_.'content-desc' -eq $Text } | Select-Object -First 1
    if (-not $node) { throw "Missing control: $Text" }
    $b=[regex]::Matches($node.bounds,'\d+') | ForEach-Object { [int]$_.Value }
    [void](Run @('shell','input','tap',[string][int](($b[0]+$b[2])/2),[string][int](($b[1]+$b[3])/2)))
}
function Assert([string]$Text,[string]$Name='assert') {
    $tree=Dump $Name
    if (-not ($tree.SelectNodes('//node') | Where-Object { $_.text -eq $Text -or $_.'content-desc' -eq $Text })) { throw "Missing UI: $Text" }
    $script:checks++
}
function Finger([int]$Id) { [void](Run @('emu','finger','touch',"$Id")); [void](Run @('emu','finger','remove')) }
function StartQa { [void](Run @('shell','am','start','-W','-n',"$package/hu.harmat22.crm.mobile.MainActivity",'--ez','demo','true')) }
[void](Run @('shell','am','force-stop',$package)); [void](Run @('shell','pm','clear',$package)); StartQa
Assert '公司销售后台'
Tap '账号登录'; Assert '启用指纹解锁？'; Tap '启用'; Assert 'Harmat CRM 指纹验证' 'save-prompt'
Finger $WrongId; Assert 'Harmat CRM 指纹验证' 'wrong-save-finger'
Finger $EnrolledId; Assert '销售概览' 'saved'
$prefs=(Run @('shell','run-as',$package,'cat','shared_prefs/session_v1_fixture.xml')) -join ''
if (!$prefs.Contains('ciphertext') -or !$prefs.Contains('iv') -or $prefs.Contains('synthetic-session') -or $prefs.Contains('wordpress_logged_in')) { throw 'Session was not stored as ciphertext only' }
$script:checks++
Tap '锁定'; Assert '公司销售后台'; Assert '指纹解锁'
Tap '指纹解锁'; Assert 'Harmat CRM 指纹验证' 'unlock-prompt'
Finger $WrongId; Assert 'Harmat CRM 指纹验证' 'wrong-unlock-finger'
Tap '取消'; Assert '公司销售后台' 'cancelled'
if ((Dump 'cancelled-hidden').OuterXml.Contains('DEMO Deal')) { throw 'Private records visible after cancel' }; $script:checks++
Tap '指纹解锁'; Assert 'Harmat CRM 指纹验证'; Finger $EnrolledId; Assert '销售概览' 'unlocked'
[void](Run @('shell','input','keyevent','3')); [void](Dump 'home'); StartQa
Assert '公司销售后台' 'background-locked'
[void](Run @('shell','am','force-stop',$package)); StartQa
Assert '公司销售后台'; Assert '指纹解锁' 'restart-locked'
Tap '指纹解锁'; Assert 'Harmat CRM 指纹验证'; Finger $EnrolledId; Assert '销售概览'
Tap '设置'; Tap '退出此设备'; Tap '清除'; Assert '公司销售后台' 'local-signout'
if ((Dump 'no-saved-session').OuterXml.Contains('text="指纹解锁"')) { throw 'Saved biometric session survived signout' }; $script:checks++
Write-Output "PASS $script:checks biometric CryptoObject, wrong-finger/cancel, encrypted-storage, restart and signout checks"
