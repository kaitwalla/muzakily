#!/bin/bash
set -e

# Start queue worker in background
php artisan queue:work --sleep=3 --tries=3 --max-time=3600 &
echo "Queue worker started"

# Start Laravel development server in background
php artisan serve --host=0.0.0.0 --port=8000 &
echo "Laravel server started on port 8000"

# Start Vite dev server in foreground (keeps container alive)
echo "Starting Vite dev server on port 5173"
npm run dev -- --host 0.0.0.0 --port 5173
