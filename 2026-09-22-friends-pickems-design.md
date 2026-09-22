# Friends Pick'ems — Design & Implementation Plan

## Context

`schier-pickems` is an office NFL pick'ems app: admins run each week from a dashboard, players get emailed signed links to make their picks, the pick grid is revealed at the first kickoff, and a cash pot goes to the weekly winner.

Riley wants a version for about 8 friends. The weekly loop is the same — ESPN schedule, lock at the first kickoff, reveal the grid, tie-breaker — with three changes:

- **No money.** No entry fee, pot, paid flag or payouts. Each week awards **placement points** (1st, 2nd, …), and a **season leaderboard** tracks the running total over time.
- **No email.** No invitations, reminders or signed links. Instead, friends **register their own account and log in**. Riley marks which registered accounts are **active players**; only active accounts are entered into weeks.
- **One admin.** Riley's account carries an `is_admin` flag and runs weeks, the points table and user management. Everyone else manages their own profile (name, nickname, photo, password).

This is a new repo built by copying from `schier-pickems` and adapting. Roughly 70% of the code carries over unchanged. The table below is the source of truth.

## Confirmed decisions

| Topic | Decision |
| --- | --- |
| Accounts | Open registration (email + password). No passcode or invite gate. A new account is **inactive** and non-admin until Riley activates it. |
| Admin | `users.is_admin`. Riley's account is created with `php artisan app:create-admin`, which sets the flag. Admins can grant the flag to another user. |
| Player = user | The `Player` model is gone. One `users` table holds name, nickname, photo, `is_admin` and `is_active`. Entries belong to users. |
| Visibility | Everything is behind `auth`. There are no public pages. Guests see only login and register. |
| Inactive users | They can log in and watch the board and leaderboard, but are not enrolled in weeks and cannot submit picks. The UI tells them they're waiting to be activated. |
| Self-serve profile | Name, nickname, photo and password, on the existing settings pages. The active and admin flags are admin-only. |
| Password reset | Off, since no mail is configured. An admin can set a new password for any user from the admin screen. Fortify's reset can be switched on later if SMTP is added. |
| Weekly pool | Every active user is auto-enrolled when a week opens. The admin can add or remove entries. |
| Lock | The whole week locks at the first kickoff (`locks_at` = earliest kickoff). Unchanged. |
| Edits | Players resubmit until the lock. The admin can enter or edit picks for any player until the lock (for friends who text their picks in). |
| Tie-breaker | Kept. Ranked by correct picks, then by the closest guess to the tie-breaker game's combined score. The tie-breaker game defaults to the last kickoff; the admin can override. |
| Placement | Competition ranking (1, 1, 3) among **submitted** entries only. Same correct count and same tie-breaker difference means a shared placement. |
| Points | Each season has an admin-editable points table, default `[10, 7, 5, 3, 2, 1, 0, 0]` (index 0 = 1st). Placements past the end of the table score 0. **Tied players split** the points for the spots they occupy (two tied for 1st: (10+7)/2 = 8.5 each). Stored as decimal with 2 places. |
| No-shows | An entry that was never submitted gets no placement, 0 points, and shows as "DNP" at the bottom. |
| Tied NFL game | Nobody gets a point for it. Unchanged. |
| Season leaderboard | Sorted by total points, then weeks won (1st-place finishes, ties included), then total correct. Includes a cumulative-points-over-time chart. |
| Pick visibility | Before the lock, only who's in and who has submitted. After the lock, the full grid. Unchanged. |
| Scope | Regular season only, weeks 1–18. ESPN sync is button-only, with no scheduler. Unchanged. |
| Branding | Generic. Stock shadcn-vue neutral theme, app name from `APP_NAME`. No Schier tokens, Proxima Nova or bundled headshots. |
| Avatars | The user uploads a photo; an admin can replace it. Initials on a colored circle as the fallback. |

## Removed from `schier-pickems` (do not port)

- `app/Mail/PickInvitation.php`, `resources/views/mail/*`, `app/Actions/Weeks/SendPickInvitations.php`, and the invitation, reminder and resend routes and buttons.
- Signed pick routes: `Entry::picksUrl()`, the `signed` middleware, and `entries.ulid` (it existed only for the signed URL).
- The whole `Player` model, migration, factory, controller and admin page as separate concepts — they are folded into `User`.
- Money: `seasons.entry_fee_cents`, `entries.is_paid`, `entries.payout_cents`, `entries.is_winner`, `WeekStandings::potCents()` and `payouts()`, the Paid switch, the pot stat cards and the `money()` formatter.
- Email-tracking columns: `entries.emailed_at`, `weeks.emails_sent_at`.
- CSV import (`ImportPlayersCsv`, `PlayerImportController`). Friends create their own accounts now.
- `Headshots.php`, `public/images/player-photos/*`, the Schier CSS tokens and fonts, and the `@laravel/passkeys` and `vue-input-otp` npm packages if they stay unused.
- Queue and mail config beyond the defaults. Nothing is queued any more.

## Stack / scaffold

- Run `laravel new <app-name> --vue --pest --database=sqlite --git`. That gives Laravel 13, Inertia 3, Vue 3.5, Tailwind 4, shadcn-vue/reka-ui, Wayfinder and Fortify. Add `laravel/boost` (dev), Larastan and Pint, matching `schier-pickems/composer.json`.
- Fortify: **registration and login on**, reset, verification and 2FA off (`'features' => [Features::registration()]`). `home` is `/`, since most users are not admins. Keep the login rate limiter at 5/min per email+IP and add the same limit to registration per IP.
- `app/Actions/Fortify/CreateNewUser` validates name, email, password (8+, confirmed) and creates the user with `is_active = false`, `is_admin = false`.
- Copy `app/Console/Commands/CreateAdmin.php` and have it set `is_admin` and `is_active` to true.
- Timezone handling is unchanged: store UTC, display in `config('app.display_timezone')` (default `America/Chicago`). Add `APP_DISPLAY_TIMEZONE` to `.env.example` this time.
- Copy the `composer.json` scripts (`setup`, `ci:check`) and `.github/workflows/tests.yml`.

## Data model

Differences from `schier-pickems` are in **bold**.

- **users** (absorbs `players`):
    - `name`, `email` (unique), `password`, `email_verified_at?`, `remember_token`
    - **`nickname?`, `photo_path?`, `is_admin` (bool, default false), `is_active` (bool, default false, indexed)**
    - `hasMany Entry`
    - Accessors ported from `Player`: `display_name` (nickname, else name), `photo_url` (public disk), `toAvatar()`. Scopes `active()` and `alphabetical()`.
    - `casts`: `password` hashed, the two flags boolean.
- **teams**: unchanged. Copy the model and migration.
- **seasons**:
    - `year` (unique)
    - **`points_table` (json, cast `array`, default `[10,7,5,3,2,1,0,0]`)**
    - **no `entry_fee_cents`**
- **weeks**: `season_id`, `number`, `status` (`WeekStatus`), `locks_at?`, `closed_at?`. **No `emails_sent_at`.** Keep `is_locked`, `phase()`, `refreshLocksAt()` and the `published()` scope.
- **games**: unchanged (`is_tiebreaker`, `manual_override`, `is_tbd_flex`, scores, status).
- **entries**:
    - **`user_id`** + `week_id` (unique pair), `tiebreaker_guess?`, `submitted_at?`, `correct_count?`
    - **`placement?` (unsigned tinyint), `points?` (decimal 6,2)**
    - **no `ulid`, `player_id`, `is_paid`, `emailed_at`, `is_winner` or `payout_cents`**
    - `isEligible()` becomes `isSubmitted()`.
- **picks**: unchanged.
- Enums `WeekStatus` and `GameStatus` are copied as-is.

## Access control

- Every route except `login`, `register` and `up` carries `auth`.
- New middleware `EnsureUserIsAdmin` (alias `admin`), applied to the `admin` group on top of `auth`. It aborts with 403 when `is_admin` is false. Pair it with a `Gate::define('admin', ...)` so Vue can hide admin links via the shared `auth.user.is_admin` prop.
- New middleware `EnsureUserIsActive` on the pick routes, which redirects inactive users home with a "You're not active yet" notice.
- Shared Inertia props (`HandleInertiaRequests`): `auth.user` gains `is_admin`, `is_active`, `display_name` and `photo_url`; `timezone` stays.
- An admin cannot remove their own admin flag or delete their own account while they are the last admin.

## Domain logic

Files live in `app/Actions` and `app/Support`. Each is copied from `schier-pickems` and then changed as described.

- **`Nfl/*`, `EspnClient`, `UpsertTeam`, `SyncTeams`, `SyncWeekGames`, `SyncSeasonSchedule`**: copy unchanged. `SyncSeasonSchedule` creates the season with the default `points_table`.
- **`Weeks/OpenWeek`**: copy. Same guards. It enrolls `User::active()` instead of active players, sets the default tie-breaker and sets `locks_at`.
- **`Picks/SubmitPicks`**: copy. Keeps the lock check, the "every game needs a valid team" rule and the tie-breaker validation. The caller resolves the entry from `$request->user()`, or from the route in the admin case.
- **`Support/WeekStandings`**: copy and change:
    - Entries in scope: `submitted_at !== null` (the paid filter is gone).
    - `rows()`: same sort and competition rank, plus `placement` (= rank) and `points`.
    - New `pointsFor(int $rank, int $tiedCount, array $table): float` returns the average of `$table[$rank-1 … $rank+$tiedCount-2]`, treating missing indexes as 0, rounded to 2 places.
    - Remove `potCents()` and `payouts()`. `winners()` stays as the placement-1 entries.
    - While the week is open, the points are projections: only final games count, and the tie-breaker is pending until that game is final (same behavior as now).
- **`Weeks/CloseWeek`**: same guards. In a transaction it writes `correct_count`, `placement` and `points` for submitted entries; non-submitted entries get null / null / `0`.
- **`Weeks/ReopenWeek`**: clears `correct_count`, `placement` and `points`.
- **New `Seasons/UpdatePointsTable`**:
    - Validates an array of 1–20 non-negative integers, each no larger than the one before it.
    - Saves the table, then **re-scores every closed week** in the season, recomputing points from each entry's stored `placement` and the number of entries sharing it. It never re-ranks.
- **New `Support/SeasonLeaderboard`**: built from closed weeks. Per user it returns `total_points`, `weeks_won` (placement 1), `total_correct`, `weeks_played` (submitted), `dnp_count`, and `cumulative` (points after each closed week, for the chart). Sorted by points, then weeks won, then total correct, then display name, with competition ranking on the first three keys.

## Routes & pages

| Route | Page | Notes |
| --- | --- | --- |
| `GET /login`, `GET /register` | `auth/Login.vue`, `auth/Register.vue` | Fortify. The register page says an admin has to activate you before you can play. |
| `GET /` | `Home.vue` | Current week board (the open week, else the latest closed one). Adds a "Make your picks" call to action for active users when the week is open and unlocked, an "waiting to be activated" notice for inactive ones, and a compact season top-3. |
| `GET /seasons/{year}` | `Seasons/Show.vue` | Leaderboard table (rank, avatar, points, W, correct/played), the cumulative points chart, and a week list (phase, top 3 with points). |
| `GET /seasons/{year}/weeks/{number}` | `Weeks/Show.vue` | Board for one week. |
| `GET /users/{user}` | `Users/Show.vue` | Profile: photo, nickname, season totals, and a per-week list of placement, points and correct count. |
| `GET /picks`, `PUT /picks` | `Picks/Show.vue` | The current open week's entry for the logged-in user (`auth` + `active`, `throttle:20,1` on PUT). The same form as now, minus the paid banner and the fee. |
| `settings/profile`, `settings/security`, `settings/appearance` | starter kit | Profile gains nickname and photo upload/remove (`forceFormData`). |
| `admin/*` | see below | `auth` + `admin`. |

### Admin pages (copy, then trim)

- **`Admin/Users/Index.vue`** (from `Admin/Players/Index.vue`):
    - Columns: avatar and name (nickname in quotes), email, weeks played, an **Active** switch, an **Admin** switch, edit, reset password, delete (only when the user has no entries).
    - The edit dialog covers name, nickname, photo and remove-photo. There is no create dialog — friends register themselves — but keep a "Create user" action for someone who won't register.
    - The header reads "N active of M" and explains that active users are auto-enrolled when a week opens.
- **`Admin/Seasons/Show.vue`**: replace the entry-fee form with the points-table editor (one numeric input per place, add/remove place, save). Saving confirms that closed weeks will be re-scored.
- **`Admin/Weeks/Show.vue`**:
    - Remove the Paid switch, pot card, send/remind/resend buttons, "Emailed X ago" and the copy-link action.
    - Payout becomes placement + points.
    - Add an "Enter picks" action per entry that opens the pick form in a dialog, posting to `PUT admin/entries/{entry}/picks` (before the lock only, through `SubmitPicks`).
    - Keep the game editor, tie-breaker, sync, open, close and reopen.

### Components (copy from `schier-pickems/resources/js/components/`)

- **`board/WeekBoardView.vue`**: the stat cards become players in, submitted, games and lock time; the winners banner becomes a podium (top 3 with points).
- **`board/PickGrid.vue`**: unchanged except for the footer rows "Correct", "Place" and "Pts". Highlight the current user's column.
- **`board/StandingsList.vue`**: the payout pill becomes a points pill ("Projected" until closed); DNP rows sit last and greyed out.
- **`board/GameMatchup.vue`**, `PlayerAvatar.vue` (rename to `UserAvatar.vue`), `TeamLogo.vue`, `WeekPhaseBadge.vue`: copy. `lib/avatar.ts` uses a neutral palette.
- **New `PointsChart.vue`**: a dependency-free inline SVG line chart, one line per user, x = week, y = cumulative points, with an avatar legend, hover highlighting and theme-aware colors.
- **`lib/format.ts`**: drop `money`, add `points()`, which prints `8.5` and trims `.00`.
- **`types/pickems.ts`**: update `StandingRow` (`placement`, `points`, `submitted`) and add `LeaderboardRow`.
- Layouts: there is one `AppLayout` with a sidebar now that every page needs auth. Its nav is This week, Season, Players, plus Admin (This week, Seasons, Users) when `is_admin`. `PublicLayout` is not needed.

## Seeders

- **`DatabaseSeeder`**: an admin (`test@example.com`, active + admin) and 8 active users with factory names, all sharing the default factory password; then `DemoWeekSeeder`.
- **`DemoWeekSeeder`**: copy and drop the paid flags. Stage weeks 1–2 as **closed** (so the leaderboard and chart have data) and week 3 as final and ready to close. Keep the hash-based picks so every run produces the same result.

## Tests (Pest; copy the `tests/Pest.php` helpers `fakeEspn` and `openWeekWithGames`, and `tests/Fixtures/espn/*`)

- **Port unchanged**: `Nfl/EspnSyncTest`; `Weeks/OpenWeekTest` (active users instead of players); the login tests; the game-editing parts of `Admin/WeekAdminTest`.
- **`Weeks/WeekStandingsTest`**: placement ranking; the tie-breaker only counts once final; a tied NFL game scores nobody; a tied placement splits points (2-way and 3-way); a placement past the table gets 0; non-submitted entries are excluded.
- **`Weeks/CloseWeekTest`**: stores placement and points, DNP scores 0, and reopening clears them.
- **`Seasons/UpdatePointsTableTest`**: validation (non-increasing, max length) and re-scoring closed weeks from the stored placement.
- **`Seasons/SeasonLeaderboardTest`**: totals, sort order, the cumulative series and the DNP count.
- **`Auth/RegistrationTest`**: registration works; a new account is inactive and non-admin; reset, verification and 2FA are disabled.
- **`Auth/AdminAccessTest`**: a guest is redirected from `/admin`; a non-admin gets a 403; an admin gets in; the last admin can't demote or delete themselves.
- **`Picks/SubmitPicksTest`**: an active user submits and resubmits; an inactive user is redirected; a user can't touch another user's entry; a locked week rejects picks.
- **`Public/BoardTest`** (now auth'd): guests are redirected; picks are hidden until the lock; the season page and user profile render.
- **`Admin/UserAdminTest`**: the active and admin toggles; profile edit with a photo upload; password reset; deletion blocked when entries exist.
- **`Settings/ProfileTest`**: a user updates their own nickname and photo but can't change `is_active` or `is_admin` through the profile endpoint.
- **`Admin/EnterPicksTest`**: an admin enters picks for a player before the lock, and not after.

## Build order (each phase ends green: `php artisan test --compact`, `vendor/bin/pint --dirty`, `npm run build`)

1. Scaffold. Fortify with registration, the `is_admin` / `is_active` columns, `CreateAdmin`, the admin middleware and the neutral theme.
2. Migrations, models, enums and factories (trimmed as above). Copy the NFL/ESPN sync and its tests.
3. Week lifecycle (open, standings, close, reopen) with points, plus `UpdatePointsTable` and its tests.
4. Picks: the entry resolved from the logged-in user, the pick form and the active-user guard.
5. Board, season leaderboard, chart and user profile.
6. Admin: users, the season points editor, the trimmed week screen and admin enter-picks.
7. Seeders, `.env.example` and the CI workflow.

## Verification

- `composer ci:check` passes (lint, `vue-tsc`, Pint, Larastan, tests).
- Run `php artisan migrate:fresh --seed`, then on the Herd URL:
    - A guest is sent to login; register a new account and see the "waiting to be activated" state on `/`.
    - As the admin, activate that account; open a week and confirm the new user has an entry.
    - As that user, submit picks, then resubmit; confirm the other picks stay hidden until the lock.
    - As the admin, close week 3; the board shows placement and points, and the leaderboard and chart update.
    - Edit the points table; the closed weeks' points change.
    - A non-admin hitting `/admin` gets a 403.
