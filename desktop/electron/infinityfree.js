'use strict';

const crypto = require('crypto');

const CHALLENGE_RE =
  /var a=toNumbers\("([0-9a-f]+)"\),b=toNumbers\("([0-9a-f]+)"\),c=toNumbers\("([0-9a-f]+)"\)/i;

function isChallengeHtml(text) {
  return typeof text === 'string' && text.includes('slowAES.decrypt') && text.includes('__test');
}

function hexToBuf(hex) {
  return Buffer.from(hex, 'hex');
}

function solveChallenge(html) {
  const m = String(html).match(CHALLENGE_RE);
  if (!m) {
    return null;
  }
  const key = hexToBuf(m[1]);
  const iv = hexToBuf(m[2]);
  const ct = hexToBuf(m[3]);
  const decipher = crypto.createDecipheriv('aes-128-cbc', key, iv);
  decipher.setAutoPadding(false);
  return Buffer.concat([decipher.update(ct), decipher.final()]).toString('hex');
}

module.exports = { isChallengeHtml, solveChallenge };
