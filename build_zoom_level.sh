#!/bin/bash

if [[ $# -ne 1 ]]; then
    echo "Usage: zoom_level.sh ZOOM_LEVEL"
    exit 1
fi

zoom=$1

echo "INFO: Generating tiles for zoom level $zoom"

mkdir -p web/tiles/$zoom;

cat labels/have_reverse_dns labels/have_dns labels/ips_seen | nice ./ipv4-heatmap \
    -y 130.246.0.0/16 \
    -z 0 \
    -x $((2**zoom)) \
    -a labels/annotations_$zoom \
    -o web/raw/$zoom.png || exit 1

nice convert web/raw/$zoom.png \
    -crop 256x256 \
    -set filename:tile "%[fx:page.x/256]-%[fx:(page.height-page.y)/256]" \
    +repage \
    +adjoin \
    "web/tiles/$zoom/%[filename:tile].png"
