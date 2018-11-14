#!/bin/bash

set -eo pipefail

if [ -d /var/lib/redis/data  ]; then
	chown -R default:0 /var/lib/redis/data
fi

echo "I've run ...."