FROM php:7.0-cli
COPY . /app
WORKDIR /app
CMD [ "php", "./webmon.php", "-i/app/store/seeds.txt" ]
