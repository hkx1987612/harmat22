# Desktop Complete-Frame Video Correction

## Scope And Correction

User confirms mobile is fine and requests desktop only. Earlier1.0.10 moved the
video's internal cover crop to the top, sacrificing the bottom; it did not solve
complete-frame presentation. That approach is superseded, not described as a
successful no-crop fix. Actual1920x900 before this change: menu bottom76, hero
top76, box1905x570, cover/top, about502px source-scaled vertical excess cropped.

Current1.0.11 changes only the final min-width768 hero CSS rule and version.
The whole section stays in native document flow directly below the76px header.
Natural16:9 preferred ratio, no minimum crop height, viewport maximum leaves32px
of the next section; video/poster use contain/center, with dark side margins where
necessary. Complete video aspect ratio takes priority over edge-to-edge filling.
User was informed of side margins. Original title/buttons remain existing overlays.
Mobile rules and all pinned body/CSS/JS/media/header/form/3D sources unchanged.

## Release And Verification

RELEASED and independently VERIFIED. Exact controller LF SHA256
e641ee934d125b07bfd05a9680ef438125bb00caf99f8c9257a99a58dabdc4a1.
Prior exact1.0.10 SHA168e24940d01fa1dd108544346c83c0e500fa7635d7ccf710c8effbcb51b5a05.
Fresh private0700/0600 backup `home-video-complete-ddee492b28644a92aeb6a3fad3fef3ea`.
Staged/formalPHP8.3 lint, exact preimage/metadata, atomic controller replacement
and cache purge pass. Two Sol source cases, five parent fake-transport/ownership/
source-only recovery cases and76 synthetic PHP controller cases pass. Sol worker
completed and closed. No second production target installed or modified.

Release and separate verify confirm9 routes200/no fatal,124 native-public apartment
authority, unchanged native form/SEO and protected source/page/business hashes,
including current payment-discussion module/assistant.35 pure offer plus17 anti-spam
validators pass without submission/mail. Existing controller transport retains its
historical HEADER_FLOW status name; proof correctly reports desktop_complete_frame.

Actual IAB1920x900: menu bottom=hero top76, hero height792/bottom868, next section
starts868, contain/center and no page overflow. Actual1280x720: box1265x612,
complete1920x1080 source paints1088x612, no intrinsic crop, video advances muted.
Actual390x844 remains original box375x520/top76, cover/58%50%, no page overflow.
Mobile was measured for non-regression, not redesigned. No physical device claim.
Actual1280 header quote opens one native form with empty contacts and required
unchecked privacy; closes normally without send. No real test record, email,
calculated price or appointment logic change. No additional Chinese-dictionary,
SEO, payment or advertising work inferred.

Actual desktop screenshot stays ignored, not Git:
`outputs/2026-10-19-home-payment/home-video-complete-desktop.png`.
Normal viewport restored and current home retained. No Safari/CWV, every-frame
overlay clearance, complete visual crawl or inbox delivery claim.

## Recovery

From this matching checkout:

```text
python server-config/maintenance/2026-10-19-update-home-video-complete.py --verify /home/harmath2/codex-backups/home-video-complete-ddee492b28644a92aeb6a3fad3fef3ea --expect e641ee934d125b07bfd05a9680ef438125bb00caf99f8c9257a99a58dabdc4a1
```

Use `--rollback` instead of `--verify` to restore only this exact owned controller
to1.0.10. Foreign source changes refuse overwrite; no database recovery. Rollback
would reinstate the known bottom-cropping behavior and is only an emergency source
recovery path, not the preferred presentation. Never run an old whole-site installer.
Stable tag: `stable-2026-10-19-home-video-complete-current`.
