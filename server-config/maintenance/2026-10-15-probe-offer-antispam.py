"""Negative-only live probes. Email/phone/privacy/date stay empty; never a lead."""
import argparse
import json
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
OFFER = "/harmat-sales-manager/v1/offer"
CF7 = "/contact-form-7/v1/contact-forms/1002/feedback"


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--benign-only", action="store_true", help="Skip URL-name rejection probes; do not work around an upstream403")
    args = parser.parse_args()
    cases = [
        ("offer_url_name", OFFER, "https://example.invalid/offer-safety-test", {}, "harmat_offer_antispam_invalid_name"),
        ("offer_www_name", OFFER, "WWW.example.invalid", {}, "harmat_offer_antispam_invalid_name"),
        ("cf7_url_name", CF7, "https://example.invalid/offer-safety-test", {}, "harmat_offer_antispam_invalid_name"),
        ("cf7_entity_url_name", CF7, "https&colon;&sol;&sol;example.invalid", {}, "harmat_offer_antispam_invalid_name"),
        ("normal_hungarian_name", OFFER, "Kov\u00e1cs \u00c1gnes", {}, "harmat_offer_required"),
        ("normal_chinese_name", OFFER, "\u5f20\u4f1f", {}, "harmat_offer_required"),
        ("normal_english_name_message_url", OFFER, "John Smith", {"your-message": "https://harmat22.hu/property/a1-1-l2/"}, "harmat_offer_required"),
    ]
    for source in ("K\u00fclt\u00e9ri hirdet\u00e9s", "Google keres\u00e9s", "ingatlan.com", "K\u00f6z\u00f6ss\u00e9gi m\u00e9dia", "Egy\u00e9b"):
        cases.append(("source_" + source, OFFER, "Harmat safety check", {"lead_source": source}, "harmat_offer_required"))
    if args.benign_only:
        cases = [case for case in cases if case[4] == "harmat_offer_required"]
    results = []
    for label, route, name, extra, expected in cases:
        params = {"your-name": name, "your-email": "", "your-phone": "", "privacy-acceptance": "", "your-date": "", "source_url": "https://harmat22.hu/", **extra}
        if any(params[key] for key in ("your-email", "your-phone", "privacy-acceptance", "your-date")):
            raise RuntimeError("No valid inquiry is permitted")
        request = urllib.request.Request(
            "https://harmat22.hu/wp-json" + route,
            data=urllib.parse.urlencode(params).encode(), method="POST",
            headers={"Origin": "https://harmat22.hu", "Referer": "https://harmat22.hu/",
                     "User-Agent": "HarmatOfferSafety/1.0", "Content-Type": "application/x-www-form-urlencoded"},
        )
        try:
            with urllib.request.urlopen(request, timeout=30) as response:
                status, body = response.status, response.read()
        except urllib.error.HTTPError as error:
            status, body = error.code, error.read()
        try:
            payload = json.loads(body)
        except json.JSONDecodeError:
            raise RuntimeError("Non-JSON rejection; stopped without retry: " + json.dumps({
                "case": label, "status": status, "body_bytes": len(body),
                "body_start": body[:240].decode("utf-8", errors="replace"),
            })) from None
        passed = status == 400 and payload.get("code") == expected
        results.append({"case": label, "status": status, "code": payload.get("code"), "expected": expected, "pass": passed})
        if not passed:
            raise RuntimeError("Unexpected live response; stopped without retry: " + json.dumps(results[-1]))
    report = {"utc": datetime.now(timezone.utc).isoformat(), "scope": "INVALID_ONLY_NO_CUSTOMER_EMAIL_PHONE_PRIVACY_DATE_NO_SUCCESS_SUBMISSION", "results": results, "all_passed": True, "positive_booking_or_mail_delivery_tested": False}
    directory = ROOT / "outputs/2026-10-15-offer-spam"
    directory.mkdir(parents=True, exist_ok=True)
    with (directory / (datetime.now(timezone.utc).strftime("negative-probes-%H%M%S-%f.json"))).open("x", encoding="utf-8") as handle:
        json.dump(report, handle, indent=2)
    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
