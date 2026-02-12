# Craft CMS Demo – Blog, Portfolio & E-Commerce

A full-featured demo project built with **Craft CMS 5** and **Craft Commerce 5**, showcasing blog, portfolio, and e-commerce functionality with a complete shopping experience.

![Craft CMS](https://img.shields.io/badge/Craft%20CMS-5.x-E5422B?logo=craftcms&logoColor=white)
![Craft Commerce](https://img.shields.io/badge/Craft%20Commerce-5.x-E5422B)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)
![DDEV](https://img.shields.io/badge/DDEV-v1.24-02A8E2)

---

## Features

### Frontend

- **Blog** – Articles with categories, featured images, and rich text content
- **Portfolio** – Project showcase with image galleries
- **Shop** – Product catalog powered by Craft Commerce
- **Cart** – Full shopping cart with checkout flow

### Backend (Craft Commerce)

- Order management
- Product & variant management
- Payment gateway integration
- Shipping methods & tax configuration (Germany)
- Digital product support

---

## Tech Stack

| Layer       | Technology                  |
|-------------|-----------------------------|
| CMS         | Craft CMS 5                 |
| E-Commerce  | Craft Commerce 5            |
| PHP         | 8.3                         |
| Database    | MySQL 8.0                   |
| Web Server  | Nginx (via DDEV)            |
| Templating  | Twig                        |
| Local Dev   | DDEV with Mutagen           |
| Node.js     | 22                          |

---

## Pages Overview

| Page       | URL                          |
|------------|------------------------------|
| Homepage   | `/`                          |
| Blog       | `/blog`                      |
| Portfolio  | `/portfolio`                 |
| Shop       | `/shop`                      |
| Cart       | `/shop/cart`                 |
| Admin      | `/admin`                     |
| Commerce   | `/admin/commerce/orders`     |

---

## Requirements

- [DDEV](https://ddev.readthedocs.io/en/stable/) v1.24+
- Docker Desktop
- Composer

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/yogig/craft-demo.git
cd craft-demo
```

### 2. Set up environment variables

```bash
cp .env.example .env
```

Edit `.env` and set your own values:

```
CRAFT_APP_ID=CraftCMS--your-unique-id
CRAFT_SECURITY_KEY=your-security-key
PRIMARY_SITE_URL=https://craft-demo.ddev.site
CRAFT_CP_URL=https://craft-demo.ddev.site/admin
```

> Generate a new security key with: `ddev craft setup/security-key`

### 3. Start DDEV

```bash
ddev start
```

### 4. Install dependencies

```bash
ddev composer install
```

### 5. Import the database

If you have a database dump:

```bash
ddev import-db --file=your-database-dump.sql.gz
```

> **Note:** The database dump is not included in this repository due to file size. Contact the repo owner for a copy, or set up a fresh Craft installation with `ddev craft install`.

### 6. Run migrations (if needed)

```bash
ddev craft migrate/all
ddev craft project-config/apply
```

### 7. Access the site

| URL | Description |
|-----|-------------|
| https://craft-demo.ddev.site | Frontend |
| https://craft-demo.ddev.site/admin | Control Panel |

---

## Project Structure

```
craft-demo/
├── config/            # Craft & Commerce configuration
│   ├── general.php
│   ├── db.php
│   └── commerce.php
├── modules/           # Custom Craft modules
├── templates/         # Twig templates
│   ├── blog/
│   ├── portfolio/
│   ├── shop/
│   └── _layouts/
├── web/               # Document root
│   └── assets/
├── .ddev/             # DDEV configuration
├── .env.example       # Environment template
├── composer.json       # PHP dependencies
└── craft               # Craft CLI
```

---

## Useful Commands

```bash
# Start/stop the project
ddev start
ddev stop

# Craft CLI
ddev craft clear-caches/all
ddev craft migrate/all
ddev craft project-config/apply

# Database
ddev export-db --gzip --file=backup.sql.gz
ddev import-db --file=backup.sql.gz

# Composer
ddev composer install
ddev composer update

# Share locally (temporary public URL)
ddev share
```

---

## Author

**Yogendra Ghorecha**
- GitHub: [@yogig](https://github.com/yogig)

---

## License

This project is for demonstration and learning purposes.
