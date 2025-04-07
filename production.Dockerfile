FROM laravelphp/vapor:php82-arm

# Install Imagick dependencies
RUN apk add --no-cache imagemagick imagemagick-dev

# Install and enable the Imagick PHP extension
RUN pecl install imagick && \
    docker-php-ext-enable imagick

# Copy your application files
COPY . /var/task
