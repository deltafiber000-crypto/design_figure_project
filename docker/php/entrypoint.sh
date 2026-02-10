#!/bin/sh

mkdir -p storage bootstrap/cache
chmod -R ug+rw storage bootstrap/cache

exec "$@"
