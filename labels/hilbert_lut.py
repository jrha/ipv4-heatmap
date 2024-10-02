#!/usr/bin/env python3

from argparse import ArgumentParser
from json import dump

from ipaddress import IPv4Network, IPv4Address


# Figure 14-5 from Hacker's Delight (by Henry S. Warren, Jr. published by
# Addison Wesley, 2002)
# See also http://www.hackersdelight.org/permissions.htm
def hil_xy_from_s(s, order):
    state = 0                                  # Initialize.
    x = 0
    y = 0

    for i in range(2 * order - 2, -1, -2):  # Do n times.
        row = 4 * state | ((s >> i) & 3)       # Row in table.
        x = (x << 1) | ((0x936C >> row) & 1)
        y = (y << 1) | ((0x39C6 >> row) & 1)
        state = (0x3E6B94C1 >> 2 * row) & 3    # New state.

    return (x, y)


def xy_from_ip(ip, network, hilbert_curve_order):
    sip = (int(ip) - int(network.network_address))
    return hil_xy_from_s(sip, hilbert_curve_order)


def main():
    parser = ArgumentParser()
    parser.add_argument('network', metavar='NETWORK', type=IPv4Network, help='an integer for the accumulator')
    args = parser.parse_args()

    #network =  IPv4Network('130.246.0.0/16')
    network = args.network
    hilbert_curve_order = int(network.prefixlen / 2)

    xy = list()
    ip2xy = dict()
    xy2ip = dict()

    for ip in network:
        x, y = xy_from_ip(ip, network, hilbert_curve_order)
        xy.append((255 - y + 0.5, x + 0.5));

        ip2xy[str(ip)] = (x, y)
        if x not in xy2ip:
            xy2ip[x] = dict()
        xy2ip[x][y] = str(ip)

    dump(xy, open('xy.json', 'w'))
    dump(xy2ip, open('xy2ip.json', 'w'))
    dump(ip2xy, open('ip2xy.json', 'w'))


if (__name__ == '__main__'):
    main()
