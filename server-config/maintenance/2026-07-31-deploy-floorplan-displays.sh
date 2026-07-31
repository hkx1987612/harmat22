#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -ne 3 ]; then
    printf 'Usage: %s CANDIDATE_DIR REPORT_CSV LIVE_DIR\n' "$0" >&2
    exit 64
fi

candidate_dir=${1%/}
report_csv=$2
live_dir=${3%/}
staged_files=

cleanup() {
    if [ -n "$staged_files" ] && [ -f "$staged_files" ]; then
        while IFS= read -r staged_file; do
            rm -f "$staged_file"
        done < "$staged_files"
        rm -f "$staged_files"
    fi
}

trap cleanup EXIT

for directory in "$candidate_dir" "$live_dir"; do
    if [ ! -d "$directory" ]; then
        printf 'Directory does not exist: %s\n' "$directory" >&2
        exit 66
    fi
done

if [ ! -f "$report_csv" ]; then
    printf 'Report does not exist: %s\n' "$report_csv" >&2
    exit 66
fi

candidate_count=$(
    find "$candidate_dir" \
        -maxdepth 1 \
        -type f \
        -iname '*-cn-floorplan-display.jpg' \
        | wc -l
)
live_count=$(
    find "$live_dir" \
        -maxdepth 1 \
        -type f \
        -iname '*-cn-floorplan-display.jpg' \
        | wc -l
)
report_count=$(($(wc -l < "$report_csv") - 1))

if \
    [ "$candidate_count" -ne 124 ] \
    || [ "$live_count" -ne 124 ] \
    || [ "$report_count" -ne 124 ]
then
    printf 'Preflight count mismatch: candidate=%s live=%s report=%s\n' \
        "$candidate_count" \
        "$live_count" \
        "$report_count" \
        >&2
    exit 65
fi

if find "$live_dir" -maxdepth 1 -type f -name '.*.codex-tmp' | grep -q .; then
    printf 'Live directory already contains Codex temporary files\n' >&2
    exit 73
fi

staged_files=$(mktemp)
cleaned=0
preserved=0

while IFS=, read -r \
    file_name \
    action \
    main_width \
    main_height \
    structural_components \
    arrow_components \
    removed_components \
    output_width \
    output_height \
    bytes \
    expected_hash
do
    if [ "$file_name" = "file" ]; then
        continue
    fi

    candidate_file="$candidate_dir/$file_name"
    live_file="$live_dir/$file_name"
    candidate_hash=$(sha256sum "$candidate_file" | cut -d ' ' -f 1)

    if [ "$candidate_hash" != "$expected_hash" ]; then
        printf 'Candidate hash mismatch: %s\n' "$file_name" >&2
        exit 65
    fi

    identify "$candidate_file" >/dev/null

    if [ "$action" = "cleaned" ]; then
        staged_file="$live_dir/.$file_name.codex-tmp"
        cp -p "$candidate_file" "$staged_file"
        identify "$staged_file" >/dev/null
        staged_hash=$(sha256sum "$staged_file" | cut -d ' ' -f 1)
        if [ "$staged_hash" != "$candidate_hash" ]; then
            printf 'Staged hash mismatch: %s\n' "$file_name" >&2
            exit 65
        fi
        printf '%s\n' "$staged_file" >> "$staged_files"
        cleaned=$((cleaned + 1))
    elif [ "$action" = "preserved" ]; then
        live_hash=$(sha256sum "$live_file" | cut -d ' ' -f 1)
        if [ "$live_hash" != "$candidate_hash" ]; then
            printf 'Preserved live file differs from candidate: %s\n' "$file_name" >&2
            exit 65
        fi
        preserved=$((preserved + 1))
    else
        printf 'Unknown report action for %s: %s\n' "$file_name" "$action" >&2
        exit 65
    fi
done < "$report_csv"

if [ "$cleaned" -ne 24 ] || [ "$preserved" -ne 100 ]; then
    printf 'Action totals mismatch: cleaned=%s preserved=%s\n' \
        "$cleaned" \
        "$preserved" \
        >&2
    exit 65
fi

while IFS= read -r staged_file; do
    file_name=$(basename "$staged_file")
    file_name=${file_name#.}
    file_name=${file_name%.codex-tmp}
    mv -f "$staged_file" "$live_dir/$file_name"
done < "$staged_files"

: > "$staged_files"

verified=0
while IFS=, read -r \
    file_name \
    action \
    main_width \
    main_height \
    structural_components \
    arrow_components \
    removed_components \
    output_width \
    output_height \
    bytes \
    expected_hash
do
    if [ "$file_name" = "file" ]; then
        continue
    fi

    live_file="$live_dir/$file_name"
    identify "$live_file" >/dev/null
    live_hash=$(sha256sum "$live_file" | cut -d ' ' -f 1)
    if [ "$live_hash" != "$expected_hash" ]; then
        printf 'Final live hash mismatch: %s\n' "$file_name" >&2
        exit 65
    fi
    verified=$((verified + 1))
done < "$report_csv"

if [ "$verified" -ne 124 ]; then
    printf 'Final verification count mismatch: %s\n' "$verified" >&2
    exit 65
fi

printf 'DEPLOYED=%s PRESERVED=%s VERIFIED=%s TEMP_FILES=0\n' \
    "$cleaned" \
    "$preserved" \
    "$verified"
