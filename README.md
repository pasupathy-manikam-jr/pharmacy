# Pharmacy

Community pharmacy management for Malaysia: counter POS, batch/FEFO stock, Poisons Act registers, purchasing, shifts, customer accounts, reports and LHDN MyInvois e-invoicing.

Laravel 13 · Inertia 3 · Vue 3 · shadcn-vue · Tailwind 4. See [PLAN.md](PLAN.md) for the market scan, design and status.

## Local setup

```bash
composer setup                 # install, .env, key, migrate, npm build
php artisan migrate:fresh --seed
composer dev
```

With `DEMO_LOGINS=true` the login page lists the seeded staff (password `Zx123456`). Never enable it on a live server.

## Checks

```bash
composer ci:check   # lint + vue-tsc + pint + phpstan + tests (what CI runs)
```

## Deploy

GitHub Actions builds assets with `APP_PATH_PREFIX=pharmacy` and publishes a `deploy` branch; on the server run `cd ~/pharmacy && bash scripts/deploy.sh`. The server never runs npm.

The daily stock digest needs the scheduler: `* * * * * cd ~/pharmacy && php artisan schedule:run`.
