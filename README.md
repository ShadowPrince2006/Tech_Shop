# Brain Shop 🧠

An online tech store built with vanilla PHP, MySQL, HTML/CSS/JS (Bootstrap).

## Features

- Product catalog with categories and search
- Shopping cart (localStorage)
- User registration and login
- Admin panel — CRUD for products
- Order placement with stock validation
- MySQL stored procedures & triggers

## Tech Stack

- **Backend:** PHP (no frameworks), MySQL
- **Frontend:** HTML, CSS (Bootstrap 5), vanilla JS
- **DB:** MySQL 8 with stored procedures

## Setup

1. Clone the repo
2. Import `db/database.sql` into MySQL
3. Edit `config.php` with your database credentials
4. Serve the project via Apache/Nginx with PHP
5. Open `index.html` in the browser

### Default users

| Role  | Email              | Password     |
|-------|--------------------|--------------|
| Admin | admin@techshop.com | admin        |
| Client| client@email.com   | test         |

## Project structure

```
shops/
├── api/            # PHP REST API (index.php — single entry point)
├── client/         # Client dashboard
├── db/             # Database schema + seed data
├── images/         # Product images
├── config.php      # DB configuration
├── index.html      # Main shop frontend
└── admin.html      # Admin panel
```

## API Endpoints

| Method | Endpoint              | Description        |
|--------|-----------------------|--------------------|
| GET    | /api/products         | List products      |
| GET    | /api/products/{id}    | Get product by ID  |
| POST   | /api/products         | Create product     |
| PUT    | /api/products/{id}    | Update product     |
| DELETE | /api/products/{id}    | Delete product     |
| GET    | /api/categories       | List categories    |
| POST   | /api/auth/register    | Register user      |
| POST   | /api/auth/login       | Login user         |
| POST   | /api/orders           | Place an order     |
| GET    | /api/orders           | List orders        |

## License

MIT
