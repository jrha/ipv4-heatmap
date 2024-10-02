#!/bin/bash

source "./config"

################################################################

function graphql_query {
    query="${1//\"/\\\"}"
    curl -s \
        -H "Authorization: Token $NETBOX_TOKEN" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        $NETBOX_GRAPHQL_URL \
        --data "{\"query\": \"query $query\"}"
}

function update_json {
    query="$1"
    index="$2"
    properties="$3"
    filename="$4"
    graphql_query "$query" | jq "$index | map( { $properties } ) | add" > "$filename"
}

function update_tsv {
    query="$1"
    index="$2"
    properties="$3"
    filename="$4"
    graphql_query "$query" | jq -rc "$index | .[] | [$properties] | @tsv" | sort > "$filename"
}

################################################################


# IPs to Hostnames
update_json \
    '{ ip_address_list( vrf_id: null family: 4  parent: "130.246.0.0/16" ) { address dns_name } }' \
    '.data.ip_address_list' \
    '(.address): .dns_name' \
    ip2hostname_raw.json

sed -i 's#/[0-9]\+##' ip2hostname_raw.json

jq 'with_entries(if .value == null or .value == "" then empty else . end)' ip2hostname_raw.json > ip2hostname.json


# Network Subnets/Prefixes for annotating map
i=0
for len in 22 24 26 28 30 30 30; do
    update_tsv \
        '{ prefix_list(family: 4, within: "130.246.0.0/16", mask_length__lte: '$len') { prefix description tenant { name } } }' \
        '.data.prefix_list' \
	'.prefix, (.tenant.name + "\n" + .description)' \
        annotations_$i
    i=$((i + 1))
done


# Last seen dates for IP addresses
i=3 # Initial Palette Offset
tmpdir="$(mktemp -d)"
for l in overayear year month week yesterday today; do
    update_tsv \
        '{ ip_address_list( dns_name__empty: false vrf_id: null family: 4 parent: "130.246.0.0/16" tag: ["lastseen'$l'"] ) { address } }' \
        '.data.ip_address_list' \
        ".address, $i" \
        "$tmpdir/lastseen$l"
    i=$((i + 1))
done

jq -rc 'keys[]' ip2hostname.json | sed 's/$/\t1/' > "$tmpdir/lastseenNetBoxIP"

jq -rc 'with_entries(if .value == null or .value == "" then . else empty end) | keys[]' ip2hostname_raw.json | sed 's/$/\t0/' > "$tmpdir/lastseenNetBoxDNS"

sed -i 's#/[0-9]\+##' "$tmpdir"/lastseen*

sort "$tmpdir"/lastseen* > ips_seen
rm -f "$tmpdir"/lastseen*
rmdir "$tmpdir"

# Lookup tables
./hilbert_lut.py $SUBNET
