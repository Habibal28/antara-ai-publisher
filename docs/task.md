# Task Status

## Task checklist

- [x] ANTARA list/detail scraping and article import implementation
- [x] AI fact extraction (10 stored articles processed individually and evidence validated)
- [x] AI rewrite (10 drafts processed individually; style rules remain user-editable)
- [x] Telegram draft delivery and approval/rejection webhook implementation
- [x] Telegram approval end-to-end (Approve callback changed article #1 to `approved` on 2026-10-03)
- [x] WordPress publishing implementation for approved articles
- [x] WordPress end-to-end publishing verification (article #1 published as WordPress post 19313; readback returned status `publish`)
- [ ] SEO metadata publishing (Yoast focus keyphrase, tags, category, fixed author, and featured image; implementation ready, VPS migration and live verification pending)
- [x] Scheduler entries for fact extraction, rewrite, Telegram draft delivery, and approved-only WordPress publishing
- [ ] Scheduler runtime and host cron setup
- [x] Database queue job dispatch for scheduled workflow commands
- [ ] Queue worker runtime setup
- [x] Deduplication improvement (whitespace-normalized content hashes implemented and existing hashes migrated)
- [x] Validation for imported ANTARA records and Gemini output structure/evidence
- [ ] Production deployment

## Completed: AI rewrite

- Rewrites use validated facts and the source article as context.
- One article is processed per `php artisan ai:rewrite` invocation.
- Draft title/content are stored in `rewritten_title` and `rewritten_content` with status `drafted`.
- Ten stored articles were rewritten individually using Gemini 3.5 Flash-Lite.
- The command reports no articles waiting for rewrite.
- Custom editorial rules can be added to `docs/ai-rewrite-rules.md` and will be included in future rewrites.
- Feature tests pass: `php artisan test --filter='AIServiceTest|RewriteArticleCommandTest|ExtractArticleFactsCommandTest'`.

## Telegram approval

- [x] The send-draft command and callback webhook are implemented.
- End-to-end approval is verified: clicking Approve changed article #1 from `waiting_approval` to `approved` on 2026-10-03.
- The callback was delivered through a temporary Cloudflare HTTPS tunnel. Telegram later reported HTTP 530; the temporary server, proxy, and tunnel have been stopped. Configure and register a stable production HTTPS endpoint before relying on future approvals.
- Telegram API calls from the queue worker failed with a network connection error in this environment, so no new draft was sent.
- The bot token appeared in a local error record and tool output during diagnostics. The database record was scrubbed and local logs contain no bot URL. The token in `.env` was confirmed valid; rotate it before production use because it was exposed.

- Drafts can be sent one at a time with `php artisan telegram:send-draft [article_id]`.
- Telegram messages include Approve and Reject buttons.
- A secret-validated webhook updates only articles in `waiting_approval` to `approved` or `rejected`.
- Telegram credentials and webhook secret are read from environment configuration.
- Webhook registration is available with `php artisan telegram:set-webhook [https://public-host/telegram/webhook]`.

## Completed: WordPress publishing implementation

- `php artisan wordpress:publish [article_id]` publishes one approved article at a time.
- Articles outside `approved` status and articles with an existing `wordpress_post_id` are skipped.
- Successful responses save `wordpress_post_id`, `published_at`, and `published` status.
- API errors are saved to `error_message`; the article remains approved for review/retry. If the required local featured image is missing and no WordPress media ID exists, publication is cancelled with status `cancelled` so the scheduled publisher can continue to the next approved article.
- WordPress URL, username, and application password are configured through environment variables.
- Feature tests pass: `php artisan test --filter=PublishWordPressArticleTest`.
- WordPress REST authentication was verified with a read-only request (HTTP 200).
- Live publishing is verified for article #1: WordPress post 19313 was created, `wordpress_post_id` and `published_at` were persisted, and a readback returned status `publish`.

## Next

Productionize the webhook host and configure persistent scheduler and queue worker runtimes before relying on the full pipeline.

## WordPress SEO metadata

- Rewrite output now includes a focus keyword, tags, and category and persists them on the article record.
- Telegram approval previews include the SEO metadata.
- WordPress publishing resolves or creates taxonomy terms, assigns author `Habib Al Bay Haqqi`, uploads the source image as featured media, and publishes the post.
- Install and activate `wordpress/antara-yoast-rest-bridge.zip` as a regular WordPress plugin so the REST API accepts and returns Yoast's `_yoast_wpseo_focuskw` field.
- Run the new Laravel migration on the deployment and verify one complete article before marking this task done.

## Scheduler progress

- [x] Laravel schedules fact extraction, rewrite, Telegram draft delivery, and WordPress publishing once per minute through unique database queue jobs. Each command still processes at most one article per invocation.
- [ ] Run the scheduler continuously with the host scheduler (for example, `php artisan schedule:work` during development or the Laravel scheduler cron entry in production); runtime and cron setup have not been verified.
- [ ] Run a queue worker continuously with `php artisan queue:work database --queue=scheduled`; runtime has not been verified.
- [x] ANTARA import is scheduled daily at 06:00 WIB and refreshes its session immediately before scraping, using credentials from `.env` in headless Playwright.
- [ ] Verify scheduled ANTARA login/import on a network-enabled host with configured credentials.
