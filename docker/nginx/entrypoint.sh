#!/bin/sh
set -e

echo "--- FIXING PERMISSIONS ---"



echo "--- STARTING NGINX ---"
exec nginx -g "daemon off;"