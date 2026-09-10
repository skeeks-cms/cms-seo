# Sitemap background job

cms-job and cms-agent 3.2+ are required. The unconditional common config
registers `seo.sitemap.generate` and a native system schedule (every 12 hours)
on `maintenance`. Provision its worker separately.

The handler invokes SitemapGenerator directly. It reports generated URL counts,
part messages and the final file/URL totals, without an invented percentage.
Cancellation/heartbeats run between URLs and before publishing the index.
Cancellation before publication preserves the previous index. There is no
per-item error skipping: generation failures fail the job without publishing
an incomplete sitemap. Slow individual database/URL calls are not interruptible
cooperatively; the isolated queue worker enforces its TTR.

`php yii seo/sitemap/generate --maxUrls=10000` remains a synchronous manual
fallback using the same generator. Its filesystem lock also protects against
concurrent job generation when both use the same console runtime directory.

Before upgrading, stop scheduler publication and drain active sitemap jobs.
Run normal CMS migrations before loading system schedules. The migration
converts the exact system command `seo/sitemap/generate` to
`job:seo.sitemap.generate`, retaining its ID, activity, interval and dates.
Running legacy agents, duplicate native schedules and custom job types fail
closed. Custom non-system command schedules remain unchanged. Existing queued
`seo.sitemap.generate` runs retain the same type and can use the native handler;
the former wrapper already ignored their payload.

No sitemap file is attached to CMS storage: these are public website outputs,
not job diagnostic artifacts. Publication and generation cleanup remain owned
by SitemapGenerator.

Tests: `php tests/native-sitemap.php` with `SKEEKS_APP_ROOT` pointing to an
installed Composer project (default `/app`). Tests use a unique temporary
directory and SQLite memory database, without the site's config or database.
