# Pick'ems

NFL pick'ems for friends.

## Overview

### What is Pick'ems?

Pick'ems is a web application for running a weekly NFL pick'ems league with friends. Players register their own accounts, pick a winner for every game before the first kickoff, and earn placement points each week that roll up into a season leaderboard.

## Getting Started

### Prerequisites

Ensure you have the following prerequisites installed on your system. You can verify each installation by running the provided commands in your terminal.

1. **PHP** (8.3+) is required for the application. Check if PHP is installed by running:

   ```bash
   php --version
   ```

2. **Composer** is necessary for managing PHP dependencies. Verify its installation with:

   ```bash
   composer --version
   ```

3. **Node** and **NPM** are needed for managing frontend dependencies. Check their installations with:

   ```bash
   node --version
   npm --version
   ```

### Installation

1. Duplicate the example environment file and configure it with your settings:

   ```bash
   cp .env.example .env
   ```

2. Install PHP and JavaScript dependencies:

   ```bash
   composer install
   npm install
   ```

3. Generate a new PHP application key:

   ```bash
   php artisan key:generate
   ```

4. Create the SQLite database file:

   ```bash
   touch database/database.sqlite
   ```

5. Apply database migrations and seed the current season (teams and schedule are pulled from ESPN; team logos are committed in `public/images/teams`):

   ```bash
   php artisan migrate --seed
   ```

6. Create your admin account:

   ```bash
   php artisan app:create-admin you@example.com "Your Name"
   ```

7. Start the development environment:

   ```bash
   composer dev
   ```

   Alternatively, run the backend and frontend separately:

   ```bash
   php artisan serve
   npm run dev
   ```

## Testing

Run the Pest test suite along with Pint formatting and PHPStan checks:

```bash
composer test
```
