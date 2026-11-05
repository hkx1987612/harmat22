param(
    [string]$Adb = 'C:/Android/Sdk/platform-tools/adb.exe',
    [string]$Serial = 'emulator-5554',
    [switch]$Compact,
    [Parameter(Mandatory=$true)][string]$OutputDirectory
)
$ErrorActionPreference = 'Stop'
$package = 'hu.harmat22.crm.preview'
if ($Serial -notmatch '^emulator-\d+$') { throw 'Smoke tests are restricted to an emulator.' }
[void](New-Item -ItemType Directory -Force -Path $OutputDirectory)
$script:checks = 0
function AdbRun([string[]]$Command) {
    $result = & $Adb -s $Serial @Command
    if ($LASTEXITCODE -ne 0) { throw "adb failed: $Command" }
    return $result
}
function Dump([string]$Name) {
    $raw = (AdbRun @('exec-out', 'uiautomator', 'dump', '/dev/tty')) -join "`n"
    $start = $raw.IndexOf('<?xml')
    $end = $raw.LastIndexOf('</hierarchy>')
    if ($start -lt 0 -or $end -lt 0) { throw 'Missing Android UI tree' }
    $xml = $raw.Substring($start, $end + 12 - $start)
    [IO.File]::WriteAllText((Join-Path $OutputDirectory "$Name.xml"), $xml)
    return [xml]$xml
}
function AssertText([xml]$Tree, [string]$Expected) {
    $node = @($Tree.SelectNodes('//node') | Where-Object { $_.text -eq $Expected -or $_.'content-desc' -eq $Expected })
    if ($node.Count -eq 0) {
        $scroll = $Tree.SelectNodes('//node') | Where-Object { $_.scrollable -eq 'true' } | Select-Object -First 1
        if ($scroll) {
            $bounds = [regex]::Matches($scroll.bounds, '\d+') | ForEach-Object { [int]$_.Value }
            $x = [int](($bounds[0] + $bounds[2]) / 2)
            $y1 = [int]($bounds[1] + ($bounds[3] - $bounds[1]) * 0.8)
            $y2 = [int]($bounds[1] + ($bounds[3] - $bounds[1]) * 0.25)
            [void](AdbRun @('shell', 'input', 'swipe', "$x", "$y1", "$x", "$y2", '400'))
            $Tree = Dump 'after-scroll'
            $node = @($Tree.SelectNodes('//node') | Where-Object { $_.text -eq $Expected -or $_.'content-desc' -eq $Expected })
        }
    }
    if ($node.Count -eq 0) { throw "Missing UI: $Expected" }
    $script:checks++
}
function Tap([string]$Name) {
    $tree = Dump 'before-tap'
    $node = @($tree.SelectNodes('//node') | Where-Object { $_.'content-desc' -eq $Name -or $_.text -eq $Name }) | Select-Object -First 1
    if (-not $node) { throw "Cannot tap missing UI: $Name" }
    $numbers = [regex]::Matches($node.bounds, '\d+') | ForEach-Object { [int]$_.Value }
    $x = [int](($numbers[0] + $numbers[2]) / 2)
    $y = [int](($numbers[1] + $numbers[3]) / 2)
    [void](AdbRun @('shell', 'input', 'tap', "$x", "$y"))
}
function Screen([string]$Name) {
    [void](Dump "$Name-idle")
    [void](AdbRun @('shell', 'screencap', '-p', '/sdcard/harmat-crm-preview-qa.png'))
    [void](AdbRun @('pull', '/sdcard/harmat-crm-preview-qa.png', (Join-Path $OutputDirectory "$Name.png")))
}
function StartPreview {
    [void](AdbRun @('shell', 'am', 'force-stop', $package))
    [void](AdbRun @('shell', 'am', 'start', '-W', '-n', "$package/.MainActivity"))
}
$font = ((AdbRun @('shell', 'settings', 'get', 'system', 'font_scale')) -join '').Trim()
$ime = ((AdbRun @('shell', 'settings', 'get', 'secure', 'show_ime_with_hard_keyboard')) -join '').Trim()
$size = (AdbRun @('shell', 'wm', 'size')) -join "`n"
$density = (AdbRun @('shell', 'wm', 'density')) -join "`n"
$savedSize = if ($size -match 'Override size: (\d+x\d+)') { $Matches[1] } else { 'reset' }
$savedDensity = if ($density -match 'Override density: (\d+)') { $Matches[1] } else { 'reset' }
try {
    if ($Compact) {
        [void](AdbRun @('shell', 'wm', 'size', '720x1280'))
        [void](AdbRun @('shell', 'wm', 'density', '320'))
    }
    [void](AdbRun @('shell', 'settings', 'put', 'system', 'font_scale', '1.0'))
    [void](AdbRun @('shell', 'settings', 'put', 'secure', 'show_ime_with_hard_keyboard', '1'))
    StartPreview
    $tree = Dump 'overview-zh'
    AssertText $tree '销售概览'
    AssertText $tree '离线测试版 · 演示数据'
    Screen 'overview-zh'
    Tap '客户'
    Tap '网站询价'
    $tree = Dump 'website-zh'
    AssertText $tree '示例客户 01'
    if ($tree.OuterXml.Contains('示例客户 02')) { throw 'Website filter includes walk-in' }
    Tap '自来询价'
    $tree = Dump 'walk-in-zh'
    AssertText $tree '示例客户 02'
    if ($tree.OuterXml.Contains('示例客户 01')) { throw 'Walk-in filter includes website client' }
    Screen 'clients-zh'
    Tap '搜索客户'
    [void](AdbRun @('shell', 'input', 'text', 'no-such-client'))
    [void](AdbRun @('shell', 'input', 'keyevent', '4'))
    AssertText (Dump 'empty-zh') '没有匹配的客户'
    Tap '清除筛选'
    Tap '示例客户 01 详情'
    AssertText (Dump 'details-zh') 'demo-01@example.invalid'
    Screen 'details-zh'
    Tap '关闭'
    Tap '跟进'
    Tap '新询价'
    $tree = Dump 'new-followup-zh'
    AssertText $tree '示例客户 01'
    AssertText $tree '示例客户 05'
    Screen 'followup-zh'
    Tap '房源'
    Tap '关联示例客户'
    $tree = Dump 'property-clients-zh'
    AssertText $tree '示例客户 01'
    AssertText $tree '示例客户 06'
    Tap '设置'
    Tap 'Magyar'
    AssertText (Dump 'settings-hu') 'Nincs kapcsolat'
    Screen 'settings-hu'
    Tap 'Áttekintés'
    AssertText (Dump 'overview-hu') 'Értékesítési áttekintés'
    Screen 'overview-hu'
    Tap 'Ügyfelek'
    Tap 'Minta ügyfél 01 adatlap'
    AssertText (Dump 'details-hu') 'Mintaadat · Csak olvasható'
    Screen 'details-hu'
    Tap 'Bezárás'
    [void](AdbRun @('shell', 'settings', 'put', 'system', 'font_scale', '1.3'))
    StartPreview
    AssertText (Dump 'large-hu') 'Értékesítési áttekintés'
    Screen 'large-hu'
    Tap 'Beállítások'
    Tap '中文'
    Tap '客户'
    Tap '搜索客户'
    Screen 'keyboard-large-zh'
    [void](AdbRun @('shell', 'input', 'keyevent', '4'))
    Tap '概览'
    Screen 'large-zh'
    $crashes = (AdbRun @('logcat', '-b', 'crash', '-d', '-t', '200')) -join "`n"
    [IO.File]::WriteAllText((Join-Path $OutputDirectory 'crash-buffer.txt'), $crashes)
    if ($crashes.Contains($package)) { throw 'App appears in crash buffer; inspect log' }
    $script:checks++
    Write-Output "PASS $script:checks UI checks plus source filtering exclusions"
} finally {
    [void](AdbRun @('shell', 'settings', 'put', 'system', 'font_scale', $font))
    if ($ime -eq 'null') {
        [void](AdbRun @('shell', 'settings', 'delete', 'secure', 'show_ime_with_hard_keyboard'))
    } else {
        [void](AdbRun @('shell', 'settings', 'put', 'secure', 'show_ime_with_hard_keyboard', $ime))
    }
    if ($Compact) {
        [void](AdbRun @('shell', 'wm', 'size', $savedSize))
        [void](AdbRun @('shell', 'wm', 'density', $savedDensity))
    }
}
