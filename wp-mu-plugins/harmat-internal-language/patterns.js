/* Bounded display templates preserve current codes and numeric tokens verbatim. */
(function (global) {
  'use strict';
  const code = '(A[1-4]-(?:F|[1-4])-L[1-9][0-9]?)';
  const quantity = '([0-9]+(?:[ ,\u00a0\u202f][0-9]+)*)';
  const patterns = [
    [new RegExp('^' + code + ' részletes adatok$'), m => m[1] + ' 详细信息'],
    [new RegExp('^' + code + ' adatlap megnyitása$'), m => '打开 ' + m[1] + ' 详情'],
    [new RegExp('^' + code + ' lakás röviden$'), m => m[1] + ' 公寓概况'],
    [new RegExp('^' + code + ' lakás \\| ([1-5]) szobás új lakás Budapest X\\. kerület$'), m => m[1] + ' 公寓 | 布达佩斯第十区 ' + m[2] + '间房新建公寓'],
    [/^(A[1-4]) épület lakásai$/, m => m[1] + ' 栋公寓'],
    [/^Harmat Lakópark látványterv - (eladolakas_[A-Za-z0-9]+)$/, m => 'Harmat Lakópark 项目效果图 - ' + m[1]],
    [/^([1-5]) szoba(?: \/ ([1-5]) háló)?$/, m => m[1] + ' 间房' + (m[2] ? ' / ' + m[2] + ' 间卧室' : '')],
    [/^Találat: ([0-9]+) lakás$/, m => '找到 ' + m[1] + ' 套公寓'],
    [/^([1-5]) szobás$/, m => m[1] + ' 间房'],
    [/^([1-4])\. emelet$/, m => '地面以上第 ' + m[1] + ' 层'],
    [/^([1-4])\. em\.$/, m => '地面以上第 ' + m[1] + ' 层'],
    [/^([0-9]+) lakás$/, m => m[1] + ' 套公寓']
  ];
  function paragraph(text) {
    const first = new RegExp('^Az ' + code + ' a Harmat Lakópark (A[1-4]) épületének (?:(földszintjén)|([1-4])\\. emeletén) található, ([1-5]) szobás lakás\\. ');
    const m = text.match(first);
    if (!m || !m[1].startsWith(m[2] + '-')) return null;
    let translated = m[1] + ' 位于 Harmat Lakópark ' + m[2] + ' 栋' + (m[3] ? '地面层' : '地面以上第 ' + m[4] + ' 层') + '，共有 ' + m[5] + ' 间房。';
    let tail = text.slice(m[0].length);
    const sentences = [
      [new RegExp('^(?:Az alapterület|A belső alapterület) ' + quantity + ' m²\\.(?: |$)'), n => '室内面积为 ' + n[1] + ' m²。'],
      [new RegExp('^A lakáshoz ' + quantity + ' m²-es (terasz / erkély|kert / terasz) kapcsolódik\\.(?: |$)'), n => '另有 ' + n[1] + ' m² 的' + (n[2] === 'kert / terasz' ? '花园 / 露台' : '露台 / 阳台') + '。'],
      [new RegExp('^Az értékesítési terület ' + quantity + ' m²\\.(?: |$)'), n => '销售面积为 ' + n[1] + ' m²。'],
      [/^Az ingatlan jelenlegi státusza: (elérhető|foglalva|foglalt|értékesített|eladva)\.(?: |$)/, n => '当前状态：' + ({'elérhető':'可售','foglalva':'已预订','foglalt':'已预订','értékesített':'已售','eladva':'已售'})[n[1]] + '。'],
      [new RegExp('^Az aktuális vételár ' + quantity + ' Ft\\.(?: |$)'), n => '当前售价为 ' + n[1] + ' Ft。']
    ];
    let count = 0;
    while (tail && count++ < 6) {
      let matched = false;
      for (const [regex, format] of sentences) {
        const found = tail.match(regex);
        if (found) { translated += format(found); tail = tail.slice(found[0].length); matched = true; break; }
      }
      if (!matched) return null;
    }
    return tail ? null : translated;
  }
  function translate(text) {
    if (text.includes(' | ')) {
      const parts = text.split(' | ');
      if (parts.length >= 2 && parts.length <= 5 && new RegExp('^' + code + '$').test(parts[0])) {
        const translated = [];
        for (const part of parts) {
          if (/^A[1-4]-(?:F|[1-4])-L[1-9][0-9]?$/.test(part) || /^[0-9\s.,]+(?: (?:m²|Ft))?$/.test(part)) translated.push(part);
          else {
            const match=part.match(/^([1-5]) szoba(?: \/ ([1-5]) háló)?$/);
            if (!match) return null;
            translated.push(match[1] + ' 间房' + (match[2] ? ' / ' + match[2] + ' 间卧室' : ''));
          }
        }
        return translated.join(' | ');
      }
    }
    for (const [regex, format] of patterns) {
      const match = text.match(regex);
      if (match) return format(match);
    }
    return paragraph(text);
  }
  if (typeof module !== 'undefined' && module.exports) module.exports = { translate };
  else global.HarmatLanguagePatterns = Object.freeze({ translate });
})(typeof window !== 'undefined' ? window : globalThis);
