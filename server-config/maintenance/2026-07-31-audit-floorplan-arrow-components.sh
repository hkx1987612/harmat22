#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -lt 1 ]; then
    printf 'Usage: %s DISPLAY_IMAGE...\n' "$0" >&2
    exit 64
fi

printf '%s\n' 'file,width,height,x,y,area,density_percent'

for input_file in "$@"; do
    components_file=$(mktemp)
    trap 'rm -f "$components_file"' EXIT

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

        if \
            [ "$area" -ge 80 ] \
            && [ "$width" -le 64 ] \
            && [ "$height" -le 64 ] \
            && [ "$width" -ge 8 ] \
            && [ "$height" -ge 8 ] \
            && { [ $((width * 100)) -ge $((height * 160)) ] \
                || [ $((height * 100)) -ge $((width * 160)) ]; }
        then
            density=$((area * 100 / (width * height)))
            printf '%s,%s,%s,%s,%s,%s,%s\n' \
                "$(basename "$input_file")" \
                "$width" \
                "$height" \
                "$x" \
                "$y" \
                "$area" \
                "$density"
        fi
    done < "$components_file"

    rm -f "$components_file"
    trap - EXIT
done
