import assert from 'node:assert/strict';

// Read-only HTTP checks. Never submits a form or executes a Google event.
const paths = ['/', '/elerhetosegeink/', '/property/a1-1-l2/', '/koszonjuk/', '/sales/', '/agent/', '/client/'];
for (const path of paths) {
  const response = await fetch('https://harmat22.hu' + path, {
    signal: AbortSignal.timeout(30000),
    headers: { 'User-Agent': 'Harmat22 maintenance tracking verification' },
  });
  const html = await response.text();
  assert.equal(response.status, 200, path);
  const privatePage = ['/sales/', '/agent/', '/client/'].includes(path);
  const match = html.match(/<script id="harmat-confirmed-lead-tracking"[^>]*>([\s\S]*?)<\/script>/);
  assert.equal(Boolean(match), !privatePage, path + ' tracking scope');
  if (match) {
    const config = JSON.parse(match[1].match(/var config = (.+);/)[1]);
    assert.equal(config.ads, 'AW-18191634808/7FpbCJ-ahLQcEPiiueJD');
    assert.equal(config.analytics, 'G-5ZHKLHYE3F');
    assert.equal(config.thankYou, path === '/koszonjuk/');
    assert.ok(html.includes('window.harmatTrackConfirmedOffer(result.data)'));
    assert.ok(html.includes('window.harmatTrackConfirmedOffer(data)'));
    assert.equal((html.match(/id="harmat-confirmed-lead-tracking"/g) || []).length, 1);
  }
  console.log(JSON.stringify({ path, status: response.status, measurement: !privatePage, verified: true }));
}
