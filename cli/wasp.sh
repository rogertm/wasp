#!/usr/bin/env bash
# shellcheck shell=bash
#
# WASP CLI script aliases:
#   - wasp <command> [options]
#   - php cli/wasp <command> [options]
#
# Usage:
#    chmod +x cli/wasp.sh
#    sudo cp cli/wasp.sh /usr/local/bin/wasp

script=""

# Try local cli/wasp first
if [ -f "./cli/wasp" ]; then
  script="./cli/wasp"

# Then try sibling ../wasp/cli/wasp
elif [ -f "../wasp/cli/wasp" ]; then
  script="../wasp/cli/wasp"

else
  # Walk up directories to see if either <parent>/wasp/cli/wasp or <parent>/cli/wasp exists
  dir="$PWD"

  while [ "$dir" != "/" ]; do
    if [ -f "$dir/wasp/cli/wasp" ]; then
      script="$dir/wasp/cli/wasp"
      break
    fi

    if [ -f "$dir/cli/wasp" ]; then
      script="$dir/cli/wasp"
      break
    fi

    dir="$(dirname "$dir")"
  done
fi

if [ -z "$script" ]; then
  echo "wasp: script not found (searched ./cli/wasp, ../wasp/cli/wasp and parents)" >&2
  exit 1
fi

# Run the script with php and forward all arguments
php "$script" "$@"