#!/bin/bash

set -eo pipefail

echo "On copie... ${APP_ROOT}/etc"

cp ${APP_ROOT}/src/php-pre-start/config.php ${APP_DATA}

echo "I've run ...."