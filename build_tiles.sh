#!/bin/bash

mkdir -p web/raw/

echo '{' > xy_ip.json
cat have_dns have_reverse_dns ips_seen |
    nice ./ipv4-heatmap \
        -d -y 130.246.0.0/16 \
        -z 0 \
        -a annotations \
        -o web/raw/0.png \
        2>&1 |
    perl -ne 'm/^(?<ip>(?:(?:\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5]))\s=>\s\d+\s=>\s\((?<x>\d+),(?<y>\d+)\)$/; print "\"$2,$3\":\"$1\",\n";' |
    sort |
    uniq >> xy_ip.json

sed -i '$ s/.$//' xy_ip.json
echo '}' >> xy_ip.json

parallel ./build_zoom_level.sh ::: 0 1 2 3 4 5 6
