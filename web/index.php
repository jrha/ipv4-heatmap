<!DOCTYPE html>
<html lang="en">
<head>
    <title>130.246.0.0/16</title>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="shortcut icon" type="image/x-icon" href="docs/images/favicon.ico">

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.4.0/dist/leaflet.css" integrity="sha512-puBpdR0798OZvTTbP4A8Ix/l+A4dHDD0DGqYW6RQ+9jxkRFclaxxQb/SJAWZfWAkuyeQUytO7+7N4QKrDh+drA==" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.4.0/dist/leaflet.js" integrity="sha512-QVftwZFqvtRNi0ZyCtsznlKSWOStnDORoefr1enyq5mVL4tmKB3S/EnC3rRJcxCPavG10IcrVGSmPh6Qw5lwrg==" crossorigin=""></script>
    <script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>

    <style>
        html, body {
            background-color: #333;
            color: #ddd;
            height: 100%;
        }
        .leaflet-container {
            background-color: #222;
        }
        div.scale {
            width: 96px;
            height:<?php echo 100/35 ?>%;
            text-align: center;
            color: white;
            font-size: 1.2vb;
	    padding-top: 0.333vb;
        }
    </style>
</head>
<body>

<div class="container-fluid" style="height: 100%;">
    <div class="row" style="height: 100%;">
        <div class="col-">
            <div><h1>Key</h1></div>
<?php
    echo "            <div class=\"scale\" style=\"background-color: #202020\" title=\"IP in NetBox, but never seen.\">IP</div>\n";
    echo "            <div class=\"scale\" style=\"background-color: #404040\" title=\"DNS for IP in NetBox, but never seen.\">DNS</div>\n";
    echo "            <div class=\"scale\" style=\"background-color: #154b7a\" title=\"IP seen more than a year ago\">&gt;Year</div>\n";
    echo "            <div class=\"scale\" style=\"background-color: #006a95\" title=\"IP seen this year\">Year</div>\n";
    echo "            <div class=\"scale\" style=\"background-color: #00879f\" title=\"IP seen this month \">Month</div>\n";
    echo "            <div class=\"scale\" style=\"background-color: #00a6a5\" title=\"IP seen this week\">Week</div>\n";
    echo "            <div class=\"scale\" style=\"background-color: #00c79c\" title=\"IP seen yesterday\">Yesterday</div>\n";
    echo "            <div class=\"scale\" style=\"background-color: #54e387\" title=\"IP seen today\">Today</div>\n";
?>
        </div>
        <div class="col-lg">
            <div id="map" style="height: 100%; margin: auto;"></div>
        </div>
    </div>
</div>

<div style="position: absolute; top: 0; right: 0; z-index: 4096; text-align: right; margin-top: 2px; margin-right: 2px;">
    <div class="input-group mb-1 was-validated">
        <input class="form-control" type="text" required pattern="(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)(\/(3[0-2]|2[0-9]|1[6-9]))?" id="ipsearch" placeholder="130.246.127.255">
        <div class="input-group-append" style="background-color: white;">
            <button class="btn btn-outline-primary" id="ipfind">Find IP or Subnet</button>
        </div>
    </div>
    <div class="input-group mb-1">
        <button class="btn form-control" id="show_hilbert">Toggle Hilbert Curve</button>
    </div>
</div>

<script>
<!--

var map = L.map('map', {
    crs: L.CRS.Simple
});

var bounds = [[0,0], [255,255]];

// Use timestamps to invalidate the cache every hour, but only on page reload
L.tileLayer('tiles/{z}/{x}{y}.png?' + Math.floor(Date.now() / 3600000), {
    bounds: bounds,
    noWrap: true,
    maxZoom: 6,
    minZoom: 1,
}).addTo(map);

map.fitBounds(bounds);

var popup = L.popup();

var xy2ip = [];
var ip2xy = [];
var hilbert = [];
var polyline = undefined;
var highlight = undefined;

// https://gist.github.com/jppommet/5708697
function int2ip (ipInt) {
    return ( (ipInt>>>24) +'.' + (ipInt>>16 & 255) +'.' + (ipInt>>8 & 255) +'.' + (ipInt & 255) );
}

// https://gist.github.com/jppommet/5708697
function ip2int(ip) {
    return ip.split('.').reduce(function(ipInt, octet) { return (ipInt<<8) + parseInt(octet, 10)}, 0) >>> 0;
}

function subnetOffset(adr, len, offset) {
    return int2ip((ip2int(adr) & (2**32-(2**(32-len)))) + offset);
}

function subnetStart(adr, len) {
    return subnetOffset(adr, len, 0);
}

function subnetEnd(adr, len) {
    return subnetOffset(adr, len, 2**(32-len)-1);
}

function subnetMid1(adr, len) {
    return subnetOffset(adr, len, 2**(32-len)/3-1);
}

function subnetMid2(adr, len) {
    return subnetOffset(adr, len, 2**(32-len)/3*2-1);
}


function onMapClick(e) {
    let x = Math.floor(e.latlng.lng);
    let y = 255 - Math.floor(e.latlng.lat);
    let ip = xy2ip[x][y];
    let content = '<b><a target="_blank" href="https://netbox.esc.rl.ac.uk/search/?q=^' + ip.replaceAll('.', '\\.') + '%2F&obj_types=ipam.ipaddress&lookup=iregex">' + ip + '</a></b>';

    if (ip2hostname[ip] !== undefined) {
        content += '<br>' + ip2hostname[ip];
    }

    if (ip !== undefined) {
        popup
            .setLatLng(L.latLng(255-y+0.5, x+0.5))
            .setContent(content)
            .openOn(map);
    }
}

function findOrHighlight(ip) {
    if (ip.includes('/')) {
        let sub = ip.split('/');
        let adr = sub[0];
        let len = sub[1];

        if (len < 16 || len > 32) {
            alert("Invalid prefix length, must be between 16 and 32 inclusive.");
            return;
        }

        xy1 = ip2xy[subnetStart(adr, len)];
        xy2 = ip2xy[subnetMid1(adr, len)];
        xy3 = ip2xy[subnetMid2(adr, len)];
        xy4 = ip2xy[subnetEnd(adr, len)];
        let coords = [
            [255 - Math.min(xy1[1], xy2[1], xy3[1], xy4[1]) + 1, Math.min(xy1[0], xy2[0], xy3[0], xy4[0])],
            [255 - Math.max(xy1[1], xy2[1], xy3[1], xy4[1]),     Math.max(xy1[0], xy2[0], xy3[0], xy4[0]) + 1],
        ];
        if (highlight !== undefined) {
            highlight.remove();
        }
        highlight = L.rectangle(coords, {color: "#aa5500", weight: 2}).addTo(map);
        map.fitBounds(coords);
    } else {
        xy = ip2xy[ip];
        xy = [255-xy[1]+0.5, xy[0] + 0.5];
        map.setView(xy, 55555);
        popup
            .setLatLng(xy)
            .setContent(ip+" / "+ip2hostname[ip])
            .openOn(map);
    };
}

$.when(
    $.get("xy2ip.json", function(data) {
        xy2ip = data;
        $.get("ip2hostname.json", function(data2) {
            ip2hostname = data2;
            map.on('click', onMapClick);
        });
    }),
    $.get("ip2xy.json", function(data) {
        ip2xy = data;
        $('#ipfind').click(function() {
            ip = $('#ipsearch').val();
            findOrHighlight(ip);
        });
    }),
).done(() => {
    if (location.search !== "" && location.search.includes('?')) {
        findOrHighlight(location.search.split('?')[1]);
    };
});

$('#show_hilbert').click(function() {
    if (polyline === undefined) {
        if (hilbert.length == 0) {
            $.get("xy.json", function(latlngs) {
                polyline = L.polyline(latlngs, {color: 'red', weight: 1}).addTo(map);
                hilbert = latlngs;
            });
        } else {
            polyline = L.polyline(hilbert, {color: 'red', weight: 1}).addTo(map);
        }
    } else {
        polyline.remove();
        polyline = undefined;
    }
});

-->
</script>
</body>
</html>
