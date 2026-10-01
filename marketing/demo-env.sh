# Point artisan at the marketing demo database instead of the dev database.
# Source it in each terminal before running artisan:
#
#   source marketing/demo-env.sh
#
# One-time setup (as a Postgres superuser):
#   sudo -u postgres createdb -O sentria sentria_marketing
#
# Seed (safe to repeat; wipes only sentria_marketing):
#   php artisan migrate:fresh --seeder=MarketingDemoSeeder --force
#
# Serve on :8001 next to the dev server on :8000, each in its own terminal:
#   php artisan serve --port=8001
#   php artisan queue:listen --tries=1 --timeout=0
#
# Reverb and Vite from `composer run dev` are shared; leave them running.

export DB_DATABASE=sentria_marketing
export APP_URL=http://localhost:8001
export REDIS_PREFIX=sentria-marketing-
export SESSION_COOKIE=sentria-marketing-session
export SENTRIA_ORG_NAME="Sangguniang Bayan"
export SENTRIA_ORG_SHORT_NAME="SB"
export SENTRIA_ORG_LOCALITY="Municipality of Malalag"
