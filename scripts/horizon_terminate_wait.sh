#!/bin/bash
# Gracefully terminate Horizon and wait for workers to stop (max 60s).
# Source this file from deploy scripts — do not execute directly.

php artisan horizon:terminate
echo "Waiting for Horizon to stop..."
HORIZON_STOPPED=false
for i in $(seq 1 12); do
  sleep 5
  if php artisan horizon:status 2>/dev/null | grep -qi "inactive"; then
    echo "Horizon stopped after $((i * 5))s."
    HORIZON_STOPPED=true
    break
  fi
  echo "Horizon still running... ($((i * 5))s elapsed)"
done

if [ "$HORIZON_STOPPED" = false ]; then
  echo "ERROR: Horizon did not stop within 60s — aborting deploy." >&2
  exit 1
fi
