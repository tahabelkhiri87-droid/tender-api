FROM php:8.2-apache

# تمكين mod_rewrite
RUN a2enmod rewrite

# نسخ الملفات
COPY . /var/www/html/

# إنشاء ملف index.php افتراضي إذا لم يكن موجوداً
RUN echo '<?php header("Location: api.php"); ?>' > /var/www/html/index.php

# تكوين Apache
RUN sed -i 's/80/10000/g' /etc/apache2/ports.conf && \
    sed -i 's/:80/:10000/g' /etc/apache2/sites-available/000-default.conf

# تعيين الصلاحيات
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

EXPOSE 10000
CMD ["apache2-foreground"]
