#!/bin/bash
# Terminate Horizon gracefully. The Horizon supervisor restarts it automatically
# with the new code, so waiting for "inactive" is not needed.
# Source this file from deploy scripts — do not execute directly.

php artisan horizon:terminate || true
