#!/bin/bash

(cd labels && ./update.sh)

parallel ./build_zoom_level.sh ::: 0 1 2 3 4 5 6
