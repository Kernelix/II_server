FROM nginx:alpine



# Копируем конфигурации и скрипты
COPY default.conf /etc/nginx/conf.d/

RUN deluser nginx 2>/dev/null || true

RUN if ! getent group app >/dev/null; then \
        addgroup -g 1000 app; \
    fi

RUN if ! getent passwd nginx >/dev/null; then \
        adduser -D -u 1000 -G app -s /bin/sh nginx; \
    fi

# Создаем необходимые директории и файлы
RUN mkdir -p /var/log/nginx \
    /var/cache/nginx/client  && \
    touch /var/log/nginx/error.log

RUN chown -R nginx:app /var/log/nginx /var/cache/nginx/client && \
    chmod -R 775 /var/log/nginx /var/cache/nginx/client

COPY default.conf /etc/nginx/conf.d/
COPY --chmod=755 entrypoint.sh /docker-entrypoint.d/
COPY nginx.conf /etc/nginx/nginx.conf


USER nginx

CMD ["nginx", "-g", "pid /tmp/nginx.pid; daemon off;"]







