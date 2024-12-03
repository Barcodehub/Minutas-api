
# Laravel App (bomberos)

Cuerpo de bomberos voluntarios de Villa del Rosario

## Install dependencies
```bash
composer install
```

## Set up the environment
Ensure the `.env` file is correctly set up with the necessary configuration:
```
APP_KEY=<Your App Key>
DB_CONNECTION=mysql
DB_HOST=<Your Database Host>
DB_PORT=<Your Database Port>
DB_DATABASE=<Your Database Name>
DB_USERNAME=<Your Database Username>
DB_PASSWORD=<Your Database Password>
```

## Generate the application key
```bash
php artisan key:generate
```

## Run database migrations
```bash
php artisan migrate
```

## Start the development server
```bash
php artisan serve
```

## Access the app
Open your browser and go to:
http://localhost:8000

## Customize the configuration
Refer to the Laravel documentation for more customization options: https://laravel.com/docs
