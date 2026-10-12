1. Copie el archivo .env.example a .env

```
    cp .env.example .env
```

2. Rellene los datos del .env

```
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=root
DB_PASSWORD=
```

4. Ejecute Contenedor de Laravel

```
    docker-compose up -d
```

5. Ejecute Comandos Adicionales del Contenedor de Laravel

```
    docker exec estacion-apple composer install

    docker exec estacion-apple php artisan key:generate

    docker exec estacion-apple php artisan storage:link

    docker exec estacion-apple chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

    docker exec estacion-apple chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

    docker exec estacion-apple php artisan optimize

    docker exec estacion-apple npm install

    docker exec estacion-apple npm run build
```

Si cambió el `Dockerfile`, levante los contenedores con `docker-compose up -d --build`.
