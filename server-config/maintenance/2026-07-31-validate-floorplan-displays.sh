#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -ne 4 ]; then
    printf 'Usage: %s SOURCE_DIR CANDIDATE_DIR REPORT_CSV AUDIT_DIR\n' "$0" >&2
    exit 64
fi

source_dir=${1%/}
candidate_dir=${2%/}
report_csv=$3
audit_dir=${4%/}

for directory in "$source_dir" "$candidate_dir" "$audit_dir"; do
    if [ ! -d "$directory" ]; then
        printf 'Directory does not exist: %s\n' "$directory" >&2
        exit 66
    fi
done

if [ ! -f "$report_csv" ]; then
    printf 'Report does not exist: %s\n' "$report_csv" >&2
    exit 66
fi

source_names="$audit_dir/source-display-names.txt"
candidate_names="$audit_dir/candidate-display-names.txt"
name_diff="$audit_dir/display-name-diff.txt"
cleaned_files="$audit_dir/cleaned-files.txt"
candidate_hashes="$audit_dir/candidates.sha256"

find "$source_dir" \
    -maxdepth 1 \
    -type f \
    -iname '*-cn-floorplan-display.jpg' \
    -printf '%f\n' \
    | sort \
    > "$source_names"

find "$candidate_dir" \
    -maxdepth 1 \
    -type f \
    -iname '*-cn-floorplan-display.jpg' \
    -printf '%f\n' \
    | sort \
    > "$candidate_names"

source_count=$(wc -l < "$source_names")
candidate_count=$(wc -l < "$candidate_names")
report_count=$(($(wc -l < "$report_csv") - 1))

if [ "$source_count" -ne 124 ]; then
    printf 'Expected 124 source display images, found %s\n' "$source_count" >&2
    exit 65
fi

if [ "$candidate_count" -ne "$source_count" ]; then
    printf 'Candidate count mismatch: source=%s candidate=%s\n' \
        "$source_count" \
        "$candidate_count" \
        >&2
    exit 65
fi

if [ "$report_count" -ne "$candidate_count" ]; then
    printf 'Report count mismatch: candidate=%s report=%s\n' \
        "$candidate_count" \
        "$report_count" \
        >&2
    exit 65
fi

if ! diff -u "$source_names" "$candidate_names" > "$name_diff"; then
    printf 'Candidate file names do not match source names\n' >&2
    exit 65
fi

decoded=0
while IFS= read -r file_name; do
    identify "$candidate_dir/$file_name" >/dev/null
    decoded=$((decoded + 1))
done < "$candidate_names"

cleaned=0
preserved=0
: > "$cleaned_files"

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
    actual_hash=$(sha256sum "$candidate_file" | cut -d ' ' -f 1)
    if [ "$actual_hash" != "$expected_hash" ]; then
        printf 'Candidate hash mismatch: %s\n' "$file_name" >&2
        exit 65
    fi

    if [ "$action" = "cleaned" ]; then
        printf '%s\n' "$file_name" >> "$cleaned_files"
        cleaned=$((cleaned + 1))
    elif [ "$action" = "preserved" ]; then
        source_hash=$(sha256sum "$source_dir/$file_name" | cut -d ' ' -f 1)
        if [ "$actual_hash" != "$source_hash" ]; then
            printf 'Preserved file differs from source: %s\n' "$file_name" >&2
            exit 65
        fi
        preserved=$((preserved + 1))
    else
        printf 'Unknown report action for %s: %s\n' "$file_name" "$action" >&2
        exit 65
    fi
done < "$report_csv"

if [ $((cleaned + preserved)) -ne "$candidate_count" ]; then
    printf 'Action totals mismatch: cleaned=%s preserved=%s candidate=%s\n' \
        "$cleaned" \
        "$preserved" \
        "$candidate_count" \
        >&2
    exit 65
fi

(
    cd "$candidate_dir"
    sha256sum ./*.jpg | sed 's#  \./#  #' | sort -k2 > "$candidate_hashes"
)

for batch in 1 2 3; do
    first=$(((batch - 1) * 8 + 1))
    last=$((batch * 8))
    batch_list="$audit_dir/cleaned-contact-$batch.txt"
    sed -n "${first},${last}p" "$cleaned_files" > "$batch_list"

    set --
    while IFS= read -r file_name; do
        set -- "$@" "$candidate_dir/$file_name"
    done < "$batch_list"

    if [ "$#" -gt 0 ]; then
        montage "$@" \
            -thumbnail '390x280>' \
            -background white \
            -fill black \
            -pointsize 15 \
            -set label '%t' \
            -geometry '410x320+10+10' \
            -tile 4x2 \
            "$audit_dir/cleaned-contact-$batch.jpg"
    fi
done

for batch in 1 2 3 4 5 6 7 8; do
    first=$(((batch - 1) * 16 + 1))
    last=$((batch * 16))
    batch_list="$audit_dir/all-contact-$batch.txt"
    sed -n "${first},${last}p" "$candidate_names" > "$batch_list"

    set --
    while IFS= read -r file_name; do
        set -- "$@" "$candidate_dir/$file_name"
    done < "$batch_list"

    if [ "$#" -gt 0 ]; then
        montage "$@" \
            -thumbnail '300x210>' \
            -background white \
            -fill black \
            -pointsize 13 \
            -set label '%t' \
            -geometry '320x245+8+8' \
            -tile 4x4 \
            "$audit_dir/all-contact-$batch.jpg"
    fi
done

printf 'SOURCE=%s CANDIDATES=%s REPORT=%s DECODED=%s CLEANED=%s PRESERVED=%s NAMES=OK HASHES=OK\n' \
    "$source_count" \
    "$candidate_count" \
    "$report_count" \
    "$decoded" \
    "$cleaned" \
    "$preserved"
