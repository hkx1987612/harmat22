import { createHash } from 'node:crypto';

export const MAX_TEXT_LENGTH = 512; // UTF-16 code units, before and after normalization.
export const FAQ_TOPICS = Object.freeze([
  'apartment_search', 'price', 'viewing', 'location', 'construction',
  'floorplans', 'parking_storage', 'manual_contact',
]);
const MANUAL_TOPICS = ['sensitive_data', 'recruitment', 'finance_or_contract'];
const KNOWN_TOPICS = new Set([...FAQ_TOPICS, ...MANUAL_TOPICS]);
// Pins the ordered JSON snapshot of the disabled, reviewed seed, including every gate.
// Any copy/config change needs deliberate review and a new pin; never accept generated copy.
const SEED_SHA256 = 'c2290823401e730e429f632bd8b102419ddca2838c7b85210b15b773b7fb0584';
const SAFE_FALLBACK = 'Erre a k\u00e9rd\u00e9sre nincs ellen\u0151rz\u00f6tt, el\u0151re meg\u00edrt v\u00e1laszunk. Lak\u00e1s\u00fcgyben k\u00e9rj\u00fck, keresse k\u00f6zvetlen\u00fcl \u00e9rt\u00e9kes\u00edt\u0151nket: +36300733375, ertekesites@harmat22.hu. \u00c1ll\u00e1s\u00fcgyben v\u00e1lassza az \u00e1ll\u00e1slehet\u0151s\u00e9g t\u00e9m\u00e1t. Ez az \u00fczenet nem tov\u00e1bb\u00edtotta a k\u00e9rd\u00e9s\u00e9t \u00fcgyint\u00e9z\u0151nek.';
const ALIASES = {
  apartment_search: ['lakast keresek', 'milyen lakasok vannak', 'mely lakasok erhetoek el', 'mely lakasok erhetok el'],
  price: ['arak', 'lakasarak', 'mennyibe kerul egy lakas', 'szemelyes arajanlatot szeretnek'],
  viewing: ['megtekintest szeretnek', 'idopontot szeretnek', 'szeretnek idopontot egyeztetni'],
  location: ['mi a cim', 'hol van a lakopark'],
  construction: ['epitesi naplo', 'hogy halad az epitkezes'],
  floorplans: ['alaprajz', 'alaprajzot szeretnek'],
  parking_storage: ['parkolot keresek', 'tarolot keresek', 'parkolo es tarolo'],
  manual_contact: ['kapcsolat'],
};
const SENSITIVE = /@|\d(?:[\s().+\-]*\d){5}|\b(?:cv|oneletrajz\w*|szemelyi\w*|szemelyazonos\w*|okmany\w*|utlevel\w*|igazolvany\w*|lakcimkartya\w*|taj\w*|adoszam\w*|adoazonosito\w*|bankszamla\w*|iban|bankkartya\w*|elerhetosegem|nevem|lakcimem|cimem|telefonom)\b/;
const RECRUITMENT = /\b(?:allas\w*|munka|munkat|munkalehetoseg\w*|toborz\w*|karrier\w*|recruitment|job|salary)\b/;
const FINANCE = /\b(?:hitel\w*|kamat\w*|szerzodes\w*|jogi|ugyved\w*|finansz\w*|kolcson\w*|torleszt\w*|csok|babavaro|adozas\w*)\b/;
const HUMAN = /\b(?:ember\w*|ugyintezo\w*|ertekesitovel|visszahivas\w*|human|operator)\b/;

const plain = value => value !== null && typeof value === 'object'
  && [Object.prototype, null].includes(Object.getPrototypeOf(value));
const normalize = text => text.normalize('NFD').replace(/\p{M}/gu, '')
  .toLowerCase().trim().replace(/\s+/g, ' ').replace(/[?!.]+$/, '');

function approvedSnapshot(kb) {
  try {
    if (!plain(kb)) return null;
    const serialized = JSON.stringify(kb);
    if (serialized.length > 16000) return null;
    if (createHash('sha256').update(serialized).digest('hex') !== SEED_SHA256) return null;
    return JSON.parse(serialized);
  } catch {
    return null;
  }
}

function result(route, topic, reply, pause_requested) {
  return Object.freeze({
    dryRun: true, sendable: false, route, topic, reply, pause_requested,
    metaAssignment: 'not_attempted',
  });
}

/**
 * Pure local policy preview, NOT a webhook, model, sender or privacy filter.
 * kb: parsed approved disabled JSON. input: synthetic {text?, topic?, language?: 'hu',
 * staffTakeover?: boolean, attachments?: []}. No event/permission facts are consumed.
 * Exact Hungarian phrases only; known topics can render without text, but never
 * override unknown/conflicting text or safety routes. No input is echoed/retained.
 * pause_requested is an advisory result, NOT persisted state or Meta assignment.
 */
export function preview(kb, input = {}) {
  const fallback = () => result('fallback', null, SAFE_FALLBACK, true);
  if (!plain(input)) return fallback();
  if (input.staffTakeover === true) return result('no_reply', null, null, true);
  if (input.staffTakeover !== undefined && input.staffTakeover !== false) return fallback();
  const seed = approvedSnapshot(kb);
  if (!seed) return fallback();
  const manual = topic => result('manual_contact', topic,
    topic === 'recruitment' ? seed.recruitment.reply
      : topic === 'manual_contact' ? seed.copy.faq.manual_contact.answer : seed.copy[topic], true);
  if (input.attachments !== undefined
    && (!Array.isArray(input.attachments) || input.attachments.length > 0)) {
    return manual('sensitive_data');
  }
  const raw = input.text === undefined ? '' : input.text;
  if (typeof raw !== 'string' || raw.length > MAX_TEXT_LENGTH) return fallback();
  const text = normalize(raw);
  if (text.length > MAX_TEXT_LENGTH) return fallback();
  const topic = input.topic ?? null;
  // Text safety takes precedence even when a benign explicit FAQ topic is supplied.
  if (SENSITIVE.test(text) || topic === 'sensitive_data') return manual('sensitive_data');
  if (RECRUITMENT.test(text) || topic === 'recruitment') return manual('recruitment');
  if (FINANCE.test(text) || topic === 'finance_or_contract') return manual('finance_or_contract');
  if (HUMAN.test(text) || topic === 'manual_contact') return manual('manual_contact');
  if (input.language !== undefined && input.language !== 'hu') return fallback();
  if (topic !== null && !KNOWN_TOPICS.has(topic)) return fallback();
  if (/[^a-z0-9 ,?!.-]/.test(text)) return fallback();

  const phrases = new Map();
  for (const key of FAQ_TOPICS) {
    for (const phrase of [normalize(seed.copy.faq[key].question), ...ALIASES[key]]) {
      phrases.set(phrase, key);
    }
  }
  let selected = topic;
  if (text) {
    const parts = phrases.has(text) ? [text] : text.split(/ (?:es|vagy) /);
    if (parts.some(part => !phrases.has(part))) return fallback();
    const matches = new Set(parts.map(part => phrases.get(part)));
    if (matches.size !== 1 || (topic !== null && !matches.has(topic))) {
      return result('clarification', null, seed.copy.clarification, true);
    }
    selected = [...matches][0];
  } else if (raw.trim() || topic === null) {
    return fallback();
  }
  if (selected === 'manual_contact') return manual(selected);
  return result('faq', selected, seed.copy.faq[selected].answer, false);
}
