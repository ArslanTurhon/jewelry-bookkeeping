# 宝塔部署说明

这个包是 PHP 8.2 兼容版，后台 Vue 已经放进 Laravel 的 `public` 目录里。宝塔只需要创建一个站点。

## 1. 服务器环境

宝塔需要：

- Nginx
- MySQL
- PHP 8.2 或 PHP 8.3

PHP 扩展建议开启：

- fileinfo
- openssl
- pdo_mysql
- mbstring
- tokenizer
- xml
- ctype
- json
- curl

## 2. 上传和解压

把压缩包上传到服务器，例如：

```text
/www/wwwroot/finance.kazanqi.com
```

解压后目录类似：

```text
/www/wwwroot/finance.kazanqi.com/finance-deploy-php82/backend
```

## 3. 宝塔创建网站

在宝塔“网站”里添加站点：

- 域名：你的域名
- 网站目录：`backend/public`
- PHP 版本：PHP 8.2 或 PHP 8.3
- 数据库：创建 MySQL 数据库，并记住数据库名、用户名、密码

例如网站目录：

```text
/www/wwwroot/finance.kazanqi.com/finance-deploy-php82/backend/public
```

## 4. 配置 .env

进入后端目录：

```bash
cd /www/wwwroot/finance.kazanqi.com/finance-deploy-php82/backend
cp .env.example .env
```

编辑 `.env`，重点改这些：

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://你的域名

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=你的数据库名
DB_USERNAME=你的数据库用户名
DB_PASSWORD=你的数据库密码

ADMIN_EMAIL=你的管理员邮箱
ADMIN_PASSWORD=你的管理员初始密码
```

如果暂时没有 HTTPS，`APP_URL` 写：

```env
APP_URL=http://你的域名
```

## 5. 初始化

进入 `backend` 目录执行：

```bash
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan config:cache
php artisan route:cache
```

如果命令行默认不是 PHP 8.2，用宝塔 PHP 路径执行：

```bash
/www/server/php/82/bin/php artisan key:generate
/www/server/php/82/bin/php artisan migrate --seed
/www/server/php/82/bin/php artisan storage:link
/www/server/php/82/bin/php artisan config:cache
/www/server/php/82/bin/php artisan route:cache
```

## 6. 权限

确保这两个目录可写：

```text
backend/storage
backend/bootstrap/cache
```

可以在 `backend` 目录执行：

```bash
chmod -R 775 storage bootstrap/cache
```

## 7. 宝塔伪静态

网站设置里找到“伪静态”，填：

```nginx
location /api/ {
    try_files $uri $uri/ /index.php?$query_string;
}

location / {
    try_files $uri $uri/ /index.html;
}
```

如果登录接口 404，再把第一段换成：

```nginx
location /api {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## 8. 登录

浏览器打开：

```text
https://你的域名
```

用 `.env` 里的管理员账号登录：

```env
ADMIN_EMAIL
ADMIN_PASSWORD
```

## 9. 常见问题

如果出现 500，先执行：

```bash
php artisan config:clear
php artisan cache:clear
```

然后查看：

```text
backend/storage/logs/laravel.log
```

如果提示数据库连接失败，检查 `.env` 数据库配置。

如果刷新页面 404，检查伪静态里的：

```nginx
location / {
    try_files $uri $uri/ /index.html;
}
```
