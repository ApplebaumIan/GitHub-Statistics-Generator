FROM laravelphp/vapor:php82-arm

# Add the `imagick` PHP extension...
RUN apk --update add imagick
RUN docker-php-ext-install imagick

# Place application in Lambda application directory...
COPY . /var/task
