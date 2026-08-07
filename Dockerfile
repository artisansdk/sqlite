FROM php:8.5-cli-bookworm AS extensions

ARG SQLITE_VEC_VERSION=0.1.9

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install --yes --no-install-recommends \
        build-essential \
        ca-certificates \
        curl \
        libsqlite3-dev \
        unzip \
    && rm -rf /var/lib/apt/lists/* \
    && curl -fsSL "https://github.com/asg017/sqlite-vec/releases/download/v${SQLITE_VEC_VERSION}/sqlite-vec-${SQLITE_VEC_VERSION}-amalgamation.tar.gz" \
        | tar -xz -C /usr/src \
    && mkdir -p /usr/src/php/ext/sqlite_vec_loader

COPY docker/sqlite-vec-loader /usr/src/php/ext/sqlite_vec_loader

RUN mv /usr/src/sqlite-vec.c /usr/src/php/ext/sqlite_vec_loader/sqlite-vec.c \
    && mv /usr/src/sqlite-vec.h /usr/src/php/ext/sqlite_vec_loader/sqlite-vec.h \
    && CFLAGS="${CFLAGS} -DSQLITE_CORE" docker-php-ext-install sqlite_vec_loader \
    && php -r '$db = new PDO("sqlite::memory:"); $db->exec("CREATE VIRTUAL TABLE vectors USING vec0(embedding FLOAT[2])");' \
    && php -m | grep -qx sqlite3 \
    && php -m | grep -qx pdo_sqlite \
    && php -m | grep -qx sqlite_vec_loader

WORKDIR /app
