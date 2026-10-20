# syntax=docker/dockerfile:1

# --- build the Vue app ---------------------------------------------------------
FROM node:24-alpine3.24 AS build

WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ ./
RUN npm run build

# --- serve static files and pass /api to PHP-FPM, without root -----------------
FROM nginxinc/nginx-unprivileged:1.30-alpine3.24

COPY docker/web/default.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
