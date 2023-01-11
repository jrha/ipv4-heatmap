#!/bin/bash

if [[ $# -ne 1 ]]; then
    echo "Usage: zoom_level.sh ZOOM_LEVEL"
    exit 1
fi

source "build_config"

zoom=$1

echo "INFO: Generating tiles for zoom level $zoom"

mkdir -p "$OUTPUT_DIR/tiles/$zoom";

cat labels/have_reverse_dns labels/have_dns labels/ips_seen | nice ./ipv4-heatmap \
    -y "$SUBNET" \
    -z 0 \
    -x $((2**zoom)) \
    -a labels/annotations_$zoom \
    -o "$OUTPUT_DIR/raw/$zoom.png" || exit 1

nice convert "$OUTPUT_DIR/raw/$zoom.png" \
    -crop 256x256 \
    -set filename:tile "%[fx:page.x/256]-%[fx:(page.height-page.y)/256]" \
    +repage \
    +adjoin \
    "$OUTPUT_DIR/tiles/$zoom/%[filename:tile].png"
