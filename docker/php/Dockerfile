# FROM php:8.4-fpm

# ARG UID=1000
# ARG GID=1000

# # ユーザー作成
# RUN groupadd -g ${GID} appuser \
#  && useradd -u ${UID} -g appuser -m appuser

# # 必要な拡張（例）
# RUN docker-php-ext-install pdo pdo_pgsql

# # 作業ディレクトリ
# WORKDIR /var/www/html

# # ★ root を使わない
# USER appuser

# RUN apt-get update && apt-get install -y \
#     git unzip libpq-dev libzip-dev \
#     && docker-php-ext-install pdo_pgsql zip \
#     && apt-get clean && rm -rf /var/lib/apt/lists/*

# COPY --from=composer:2 /usr/bin/composer /usr/bin/composer





FROM php:8.4-fpm

ARG UID=1000
ARG GID=1000

# ---- 必須パッケージ ----
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libpng-dev \
    fontconfig \
    librsvg2-bin \
    fonts-ipafont \
 && rm -rf /var/lib/apt/lists/*

# ---- PHP Extensions ----
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    gd

# ---- Composer ----
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ---- ユーザー作成（rootを使わない核心）----
RUN groupadd -g ${GID} appuser \
 && useradd -u ${UID} -g appuser -m appuser

WORKDIR /var/www/html

# ---- 権限地獄を終わらせる ----
USER appuser

COPY docker/php/entrypoint.sh /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]

CMD ["php-fpm"]
