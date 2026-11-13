# Telegram Bot Reply

Laravel 13 (PHP 8.4) app where a **superadmin** creates **companies**, each company connects up to *N* Telegram bots,
configures auto-replies (text, image, buttons) and chats back with every Telegram user who messaged the bot.

## Setup (Laragon)

```bash
composer install
cp .env.example .env        # then set DB_DATABASE=telegram_bot_reply, APP_URL
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

Default login (from `DatabaseSeeder`): **superadmin / superadmin99999**

## Roles

| Role | Can do |
|------|--------|
| superadmin | Create / edit / disable / delete companies, set username, password and **bot limit** |
| company | Connect bots (up to its limit), manage auto-replies, view chats, send messages, broadcast |

Login is by **username + password** (no email).

## How the bot flow works

1. Company pastes a BotFather token and clicks **Connect** → token is checked with `getMe`, saved (encrypted) and the webhook is registered.
2. Company adds **replies**: trigger `/start`, a keyword (e.g. `price`), or `*` (fallback). Each reply can have a message, an image and buttons
   (link buttons, or keyword buttons that send the reply with that trigger).
3. A Telegram user presses Start → Telegram POSTs to `/telegram/webhook/{bot}` → the app saves the user (`chat_id`, name, username, language)
   in `telegram_users`, the message in `telegram_messages` (with `bot_id`), and sends the matching replies.
4. In **Chats** the company opens any user and sends text / image / buttons back, or **Broadcast**s to all users of that bot.

## Webhook vs local polling

Telegram only delivers webhooks to a public **HTTPS** URL. In production set `APP_URL=https://your-domain` and click **Set webhook** on the bot page.

On a local Laragon machine (`http://*.test`) use polling instead:

```bash
php artisan telegram:poll
```

(or expose the site with a tunnel such as ngrok, set `APP_URL` to the https tunnel URL, and click **Set webhook**).

## Tests

Tests use the MySQL database `telegram_bot_reply_test` (see `phpunit.xml`).

```bash
php artisan test
```
