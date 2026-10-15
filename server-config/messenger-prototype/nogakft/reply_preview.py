"""Deterministic, synthetic-only grounding preview; never a sender or classifier.

Caller supplies reviewed seed bytes, snapshot bytes and an aware validation time.
No file, clock, network, model, persistence or handover operations happen here.
Unknown prose is not classified: only reviewed questions and bounded aliases are
eligible for FAQ output. A later model's enum hint cannot authorize unknown text.
"""
from decimal import Decimal
from enum import Enum
import hashlib
import json
import re
import unicodedata

from inventory_reader import InventoryUnavailable, validate_inventory


MAX_TEXT_LENGTH = 512
MAX_SEED_BYTES = 16_000
# Reviewed October 15 seed bytes, with CRLF normalized for Git checkouts.
SEED_SHA256 = '8b29aeacc6ebf085060f7db367ad56339a06f5206d67395623de73236e3f791e'


class Topic(str, Enum):
    APARTMENT_SEARCH = 'apartment_search'
    PRICE = 'price'
    VIEWING = 'viewing'
    LOCATION = 'location'
    CONSTRUCTION = 'construction'
    FLOORPLANS = 'floorplans'
    PARKING_STORAGE = 'parking_storage'
    MANUAL_CONTACT = 'manual_contact'
    RECRUITMENT = 'recruitment'
    SENSITIVE_DATA = 'sensitive_data'
    FINANCE_OR_CONTRACT = 'finance_or_contract'
    APARTMENT = 'apartment'


FAQ_TOPICS = tuple(topic.value for topic in Topic)[:8]
ALIASES = {
    'apartment_search': ('lakast keresek', 'milyen lakasok vannak',
                         'mely lakasok erhetoek el', 'mely lakasok erhetok el'),
    'price': ('arak', 'lakasarak', 'mennyibe kerul egy lakas',
              'szemelyes arajanlatot szeretnek'),
    'viewing': ('megtekintest szeretnek', 'szeretnek idopontot egyeztetni'),
    'location': ('mi a cim', 'hol van a lakopark'),
    'construction': ('epitesi naplo', 'hogy halad az epitkezes'),
    'floorplans': ('alaprajz', 'alaprajzot szeretnek'),
    'parking_storage': ('parkolot keresek', 'tarolot keresek', 'parkolo es tarolo'),
    'manual_contact': ('kapcsolat',),
}
SENSITIVE = re.compile(
    r'@|\d(?:[\s().+\-]*\d){5}|\b(?:cv|oneletrajz\w*|szemelyi\w*|'
    r'szemelyazonos\w*|okmany\w*|utlevel\w*|igazolvany\w*|lakcimkartya\w*|'
    r'taj\w*|adoszam\w*|adoazonosito\w*|bankszamla\w*|iban|bankkartya\w*|'
    r'elerhetosegem|nevem|lakcimem|cimem|telefonom)\b')
FINANCE = re.compile(
    r'\b(?:hitel\w*|kamat\w*|szerzodes\w*|jogi|ugyved\w*|finansz\w*|'
    r'kolcson\w*|torleszt\w*|csok\w*|babavaro\w*|adozas\w*)\b')
RECRUITMENT = re.compile(
    r'\b(?:allas\w*|munka\w*|toborz\w*|karrier\w*|recruitment|job|salary)\b')
HUMAN = re.compile(
    r'\b(?:ember\w*|ugyintezo\w*|ertekesitovel|visszahivas\w*|human|operator)\b')
BOOKING = re.compile(r'\b(?:foglal\w*|lefoglal\w*|rezerval\w*|visszaigazol\w*|'
                     r'garant\w*|argarancia\w*|booking|reserve)\b')
# Broad candidate extraction also catches unsupported buildings/floors. Only an
# exact, case-sensitive code in the validated snapshot can ever produce facts.
CODE_CANDIDATE = re.compile(r'(?<!\w)[aA][0-9]+-[a-zA-Z0-9]+-[lL][0-9]+(?!\w)')
UNIT_TEMPLATES = (
    '{code}', '{code} lakas', '{code} lakas adatai',
    'az {code} lakas adatai', 'mit lehet tudni az {code} lakasrol',
    'mennyibe kerul az {code} lakas', 'az {code} lakas ara',
    'az {code} lakas elerheto', 'az {code} lakas alaprajza',
)


def _normalize(text):
    text = ''.join(char for char in unicodedata.normalize('NFD', text.lower())
                   if unicodedata.category(char) != 'Mn')
    return ' '.join(text.split()).rstrip('?!.')


def _unique_object(pairs):
    result = {}
    for key, value in pairs:
        if key in result:
            raise ValueError('duplicate_key')
        result[key] = value
    return result


def _approved_seed(payload):
    if type(payload) is not bytes or len(payload) > MAX_SEED_BYTES:
        return None
    normalized = payload.replace(b'\r\n', b'\n')
    if hashlib.sha256(normalized).hexdigest() != SEED_SHA256:
        return None
    try:
        return json.loads(normalized.decode('utf-8'), object_pairs_hook=_unique_object)
    except (ValueError, UnicodeError, RecursionError):
        return None


def _result(route, topic=None, reply=None):
    return {'dry_run': True, 'sendable': False, 'route': route,
            'topic': topic, 'reply': reply}


def _decimal(value):
    rendered = format(Decimal(str(value)), 'f')
    if '.' in rendered:
        rendered = rendered.rstrip('0').rstrip('.')
    return rendered.replace('.', ',')


def _apartment_reply(unit, seed):
    price = ('Ár egyeztetés alapján' if unit['price_huf'] is None
             else format(unit['price_huf'], ',').replace(',', ' ') + ' Ft')
    status = {'current': 'Aktuális kínálat', 'reserved': 'Foglalt', 'sold': 'Eladva'}
    # Every interpolated value is from the validator's exact public allowlist.
    lines = [unit['code'], 'Nyilvános jelzés: ' + status[unit['status']],
             'Épület: ' + unit['building'], 'Szint: ' + unit['floor'],
             'Szobák: ' + _decimal(unit['rooms']),
             'Értékesítési terület: ' + _decimal(unit['sales_area_m2']) + ' m²',
             'Kültéri terület: ' + _decimal(unit['outdoor_area_m2']) + ' m²',
             'Nyilvános ár: ' + price, unit['url'],
             seed['copy']['faq']['price']['answer'],
             seed['copy']['faq']['viewing']['answer']]
    return '\n'.join(lines)


def preview_reply(text, *, seed_payload, topic_hint=None, inventory_payload=None, now=None):
    """Return a NONSENDABLE preview for synthetic text of at most 512 characters.

    topic_hint is None, Topic, or its exact string value; it is never freeform
    model output or an apartment identifier. Snapshot bytes are validated anew
    at the caller's trusted time for each unit response. Parsed dicts are not
    accepted as a substitute for freshness/duplicate-key validation. No source
    query, customer prose or error detail is included in the output.
    """
    seed = _approved_seed(seed_payload)
    if seed is None:
        return _result('seed_unavailable')
    copy = seed['copy']

    def fallback():
        return _result('fallback', reply=copy['fallback'])

    def clarify():
        return _result('clarification', reply=copy['clarification'])

    def manual(topic):
        reply = (seed['recruitment']['reply'] if topic == 'recruitment'
                 else copy['faq']['manual_contact']['answer'] if topic == 'manual_contact'
                 else copy[topic])
        return _result('human_contact', topic, reply)

    if type(text) is not str or not 0 < len(text) <= MAX_TEXT_LENGTH:
        return fallback()
    normalized = _normalize(text)
    if not normalized or len(normalized) > MAX_TEXT_LENGTH:
        return fallback()
    # Safety routing precedes *all* hints and inventory work. These are coarse
    # conservative triggers, not a complete PII detector or language classifier.
    if SENSITIVE.search(normalized):
        return manual('sensitive_data')
    if FINANCE.search(normalized):
        return manual('finance_or_contract')
    if RECRUITMENT.search(normalized):
        if HUMAN.search(normalized) or 'lakas' in normalized:
            return clarify()
        return manual('recruitment')
    if HUMAN.search(normalized):
        return manual('manual_contact')
    if BOOKING.search(normalized):
        return _result('human_contact', 'viewing', copy['faq']['viewing']['answer'])
    if topic_hint is not None:
        if type(topic_hint) not in (str, Topic):
            return clarify()
        try:
            hint = Topic(topic_hint).value
        except ValueError:
            return clarify()
    else:
        hint = None
    # Do not silently strip controls, foreign scripts, combining/zero-width
    # tricks, URLs, code blocks or arbitrary prose into an approved query.
    if any(unicodedata.category(char).startswith('C') for char in text):
        return fallback()
    if re.search(r'[^a-zA-Z0-9 áéíóöőúüűÁÉÍÓÖŐÚÜŰ,?!.\-]', text):
        return fallback()
    if re.search(r'[^a-z0-9 ,?!.\-]', normalized):
        return fallback()

    candidates = CODE_CANDIDATE.findall(text)
    if candidates:
        if len(candidates) != 1:
            return clarify()
        code = candidates[0]
        if normalized not in {template.format(code=code.lower()) for template in UNIT_TEMPLATES}:
            return clarify()
        if hint not in (None, 'apartment', 'apartment_search', 'price', 'floorplans'):
            return clarify()
        try:
            data = validate_inventory(inventory_payload, now=now)
        except InventoryUnavailable:
            return _result('inventory_unavailable', reply=copy['fallback'])
        unit = next((item for item in data['items'] if item['code'] == code), None)
        if unit is None:
            return clarify()
        return _result('apartment', 'apartment', _apartment_reply(unit, seed))

    phrases = {_normalize(copy['faq'][topic]['question']): topic for topic in FAQ_TOPICS}
    for topic, aliases in ALIASES.items():
        phrases.update((alias, topic) for alias in aliases)
    selected = phrases.get(normalized)
    if selected is None:
        return fallback()
    if hint is not None and selected != hint:
        return clarify()
    if selected == 'manual_contact':
        return manual(selected)
    return _result('faq', selected, copy['faq'][selected]['answer'])
