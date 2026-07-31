#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -ne 3 ]; then
    printf 'Usage: %s SOURCE_DIR OUTPUT_DIR REPORT_CSV\n' "$0" >&2
    exit 64
fi

source_dir=${1%/}
output_dir=${2%/}
report_csv=$3

if [ ! -d "$source_dir" ]; then
    printf 'Source directory does not exist: %s\n' "$source_dir" >&2
    exit 66
fi

mkdir -p "$output_dir"
if find "$output_dir" -maxdepth 1 -type f -iname '*-cn-floorplan-display.jpg' | grep -q .; then
    printf 'Output directory already contains display images: %s\n' "$output_dir" >&2
    exit 73
fi

printf '%s\n' \
    'file,action,main_width,main_height,structural_components,arrow_components,removed_components,output_width,output_height,bytes,sha256' \
    > "$report_csv"

processed=0
cleaned=0
preserved=0
input_list=$(mktemp)
components_file=

trap 'rm -f "$input_list" ${components_file:+"$components_file"}' EXIT

find "$source_dir" \
    -maxdepth 1 \
    -type f \
    -iname '*-cn-floorplan-display.jpg' \
    -print0 \
    | sort -z \
    > "$input_list"

while IFS= read -r -d '' input_file; do
    file_name=$(basename "$input_file")
    output_file="$output_dir/$file_name"
    components_file=$(mktemp)

    convert "$input_file" \
        -fuzz 8% \
        -transparent white \
        -alpha extract \
        -threshold 1% \
        -morphology Close Disk:5 \
        -define connected-components:verbose=true \
        -connected-components 8 \
        null: 2>&1 \
        | grep 'gray(255)' \
        > "$components_file"

    main_line=$(head -n 1 "$components_file")
    if [[ ! "$main_line" =~ ([0-9]+)x([0-9]+)\+([0-9]+)\+([0-9]+).*[[:space:]]([0-9]+)[[:space:]]gray\(255\) ]]; then
        printf 'Unable to read main component for %s\n' "$file_name" >&2
        rm -f "$components_file"
        exit 65
    fi

    main_width=${BASH_REMATCH[1]}
    main_height=${BASH_REMATCH[2]}
    min_x=${BASH_REMATCH[3]}
    min_y=${BASH_REMATCH[4]}
    main_area=${BASH_REMATCH[5]}
    max_x=$((min_x + main_width))
    max_y=$((min_y + main_height))

    structural_components=1
    arrow_components=0
    removed_components=0
    erase_args=()
    structural_threshold=$((main_area / 100))
    if [ "$structural_threshold" -lt 1000 ]; then
        structural_threshold=1000
    fi

    component_index=0
    while IFS= read -r component_line; do
        component_index=$((component_index + 1))
        if [ "$component_index" -eq 1 ]; then
            continue
        fi

        if [[ ! "$component_line" =~ ([0-9]+)x([0-9]+)\+([0-9]+)\+([0-9]+).*[[:space:]]([0-9]+)[[:space:]]gray\(255\) ]]; then
            continue
        fi

        width=${BASH_REMATCH[1]}
        height=${BASH_REMATCH[2]}
        x=${BASH_REMATCH[3]}
        y=${BASH_REMATCH[4]}
        area=${BASH_REMATCH[5]}
        component_max_x=$((x + width))
        component_max_y=$((y + height))

        if [ "$area" -lt 4 ]; then
            continue
        fi

        is_structure=0
        is_arrow=0
        if [ "$area" -ge "$structural_threshold" ]; then
            is_structure=1
        elif \
            [ "$area" -ge 180 ] \
            && [ "$width" -le 64 ] \
            && [ "$height" -le 64 ] \
            && [ "$width" -ge 8 ] \
            && [ "$height" -ge 8 ] \
            && [ $((area * 100)) -ge $((width * height * 45)) ] \
            && [ $((area * 100)) -le $((width * height * 60)) ] \
            && { [ $((width * 100)) -ge $((height * 160)) ] \
                || [ $((height * 100)) -ge $((width * 160)) ]; }
        then
            is_arrow=1
        fi

        if [ "$is_structure" -eq 1 ] || [ "$is_arrow" -eq 1 ]; then
            if [ "$x" -lt "$min_x" ]; then min_x=$x; fi
            if [ "$y" -lt "$min_y" ]; then min_y=$y; fi
            if [ "$component_max_x" -gt "$max_x" ]; then max_x=$component_max_x; fi
            if [ "$component_max_y" -gt "$max_y" ]; then max_y=$component_max_y; fi

            if [ "$is_structure" -eq 1 ]; then
                structural_components=$((structural_components + 1))
            else
                arrow_components=$((arrow_components + 1))
            fi
        else
            removed_components=$((removed_components + 1))
            erase_x=$((x - 2))
            erase_y=$((y - 2))
            if [ "$erase_x" -lt 0 ]; then erase_x=0; fi
            if [ "$erase_y" -lt 0 ]; then erase_y=0; fi
            erase_max_x=$((component_max_x + 1))
            erase_max_y=$((component_max_y + 1))
            erase_args+=(
                -draw
                "rectangle ${erase_x},${erase_y} ${erase_max_x},${erase_max_y}"
            )
        fi
    done < "$components_file"

    rm -f "$components_file"
    components_file=

    if [ "$removed_components" -gt 0 ]; then
        crop_width=$((max_x - min_x))
        crop_height=$((max_y - min_y))
        convert "$input_file" \
            -fill white \
            "${erase_args[@]}" \
            -crop "${crop_width}x${crop_height}+${min_x}+${min_y}" \
            +repage \
            -bordercolor white \
            -border 32x32 \
            -strip \
            -sampling-factor 4:4:4 \
            -quality 98 \
            "$output_file"
        action=cleaned
        cleaned=$((cleaned + 1))
    else
        cp -p "$input_file" "$output_file"
        action=preserved
        preserved=$((preserved + 1))
    fi

    output_dimensions=$(identify -format '%w,%h' "$output_file")
    output_width=${output_dimensions%,*}
    output_height=${output_dimensions#*,}
    bytes=$(stat -c '%s' "$output_file")
    checksum=$(sha256sum "$output_file" | cut -d ' ' -f 1)

    printf '%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n' \
        "$file_name" \
        "$action" \
        "$main_width" \
        "$main_height" \
        "$structural_components" \
        "$arrow_components" \
        "$removed_components" \
        "$output_width" \
        "$output_height" \
        "$bytes" \
        "$checksum" \
        >> "$report_csv"

    processed=$((processed + 1))
done < "$input_list"

printf 'PROCESSED=%s CLEANED=%s PRESERVED=%s\n' \
    "$processed" \
    "$cleaned" \
    "$preserved"
