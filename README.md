# Digital Learning Platform for Rural Government Schools

> **Developed by Ritu | CMRIT (CMR Institute of Technology)**  
> © 2026 Ritu. All rights reserved.

A complete school management and digital learning platform built with **Laravel 10**. It helps schools manage students, teachers, classes, attendance, examinations, fees, assignments, announcements, communication, and online learning activities from a centralized dashboard.

> Designed for rural government schools, with multi-school/SaaS support and role-based access.

> **Copyright Notice:** This project, its source code, design, and documentation are the intellectual property of Ritu. Unauthorized copying, redistribution, modification, commercial use, or publication of any part of this project is prohibited. Academic use is allowed only with the explicit permission of the owner.

## Key Features

- School and multi-tenant management
- Role-based dashboards for Super Admin, School Admin, Teacher, Student, and Guardian
- Student admission and complete student records
- Teacher and staff management
- Class, section, subject, timetable, and syllabus management
- Daily attendance and attendance reports
- Examination, grading, marks, report cards, and timetable management
- Fee collection, invoices, expenses, and financial reports
- Assignment creation and submission
- Lessons, study materials, and online learning content
- Announcements and school notices
- Enquiry, online admission, and parent communication
- Leave management, holidays, and events
- Library, transport, and hostel modules
- Real-time chat and notification support
- Subscription packages and payment gateway support
- Printable reports and PDF document generation

## Technology Stack

- **Backend:** PHP 8.1+, Laravel 10
- **Database:** MySQL / MariaDB
- **Frontend:** Blade, Bootstrap 5, JavaScript, Vue 2, SASS
- **Build Tools:** Laravel Mix, npm
- **Authentication:** Laravel Sanctum and role/permission middleware
- **Additional Libraries:** DomPDF, Spatie Permission, Maatwebsite Excel, Google APIs, Stripe, Razorpay, and Paystack

## Requirements

### Windows (XAMPP)

- XAMPP or Apache with PHP
- PHP 8.1 or higher
- Composer
- Node.js and npm
- MySQL or MariaDB
- Required PHP extensions:
  - `curl`
  - `pdo_mysql`
  - `zip`
  - `mbstring`
  - `fileinfo`
  - `openssl`

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/lunacoderitu3/DIgital-learning-platform-rural-government-schools.git
cd DIgital-learning-platform-rural-government-schools
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install frontend dependencies

```bash
npm install
```

### 4. Create the environment file

```bash
copy .env.example .env
```

Or on Linux/macOS:

```bash
cp .env.example .env
```

### 5. Configure the database

Create a MySQL database, then update these values in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_username
DB_PASSWORD=your_database_password
```

### 6. Generate application key

```bash
php artisan key:generate
```

### 7. Run migrations and seed data

```bash
php artisan migrate --seed
```

If migrations are already available and you want a clean setup:

```bash
php artisan migrate:fresh --seed
```

> Warning: `migrate:fresh` deletes all existing database data.

### 8. Create storage link

```bash
php artisan storage:link
```

### 9. Build frontend assets

For development:

```bash
npm run dev
```

For production:

```bash
npm run production
```

### 10. Start the application

```bash
php artisan serve
```

Open:

```text
http://127.0.0.1:8000
```

If using XAMPP Apache, place the project inside `htdocs` and access it through the configured virtual host or `http://localhost/school/public`.

## Environment Configuration

Never commit a real `.env` file or service credentials to GitHub.

Configure payment, email, storage, and messaging services only in your local `.env` file:

- Email: SMTP/Mail settings
- Payment: Stripe, Razorpay, or Paystack credentials
- Cloud storage: AWS or local storage
- Broadcasting: Pusher or Laravel Reverb
- Google: OAuth/API credentials

See `.env.example` for available configuration variables.

## Default Configuration

Before deploying, review and update:

- `APP_NAME`
- `APP_URL`
- `APP_ENV=production`
- `APP_DEBUG=false`
- Database credentials
- Mail credentials
- Logo and favicon settings
- Payment gateway credentials
- Queue and cache settings

## Running Tests

```bash
php artisan test
```

or:

```bash
vendor/bin/phpunit
```

## Production Deployment

1. Set `APP_ENV=production` and `APP_DEBUG=false`.
2. Configure the database and all required environment variables.
3. Install Composer dependencies:

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

4. Build frontend assets:

   ```bash
   npm install
   npm run production
   ```

5. Run optimizations:

   ```bash
   php artisan migrate --force
   php artisan storage:link
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

6. Ensure these directories are writable by the web server:

   ```text
   storage/
   bootstrap/cache/
   public/storage/
   ```

## Project Structure

```text
app/          Application logic, models, controllers, services, and jobs
bootstrap/    Framework bootstrap files
config/       Laravel and application configuration
database/     Migrations, seeders, and factories
public/       Public files and compiled assets
resources/    Blade views, JavaScript, and styles
routes/       Web and API routes
storage/      Logs, cache, uploads, and generated files
tests/        Unit and feature tests
```

## Security

- Do not upload real database passwords or API keys.
- Use `.env.example` as the only environment template.
- Replace all demo credentials before deployment.
- Keep `APP_DEBUG=false` in production.
- Use HTTPS for all public deployments.
- Regularly update Composer and npm dependencies.

## Contributing

Contributions are not accepted through public forks or pull requests unless written permission is granted by the owner.

For academic, educational, or collaboration requests, contact the project owner before copying or modifying the project.

## License and Usage

This is a **copyright-protected project**. No open-source license is granted.

- Personal viewing on GitHub is permitted.
- Copying, downloading for reuse, modifying, redistributing, sublicensing, or commercial use is prohibited without written permission.
- The project may not be presented or submitted as an original project by anyone other than the authorized developer.

© 2026 Ritu. All rights reserved.

## Developer

- **Developer:** Ritu
- **College:** CMRIT (CMR Institute of Technology)
- **Project:** Digital Learning Platform for Rural Government Schools
- **GitHub:** [lunacoderitu3](https://github.com/lunacoderitu3)

## Support

If you find bugs or have feature requests, please open an issue on GitHub with:

- Clear steps to reproduce
- Expected behavior
- Screenshots, if possible
- PHP, Laravel, and browser versions
