# استخدام صورة PHP رسمية مع Apache
FROM php:8.2-apache

# تمكين mod_rewrite لدعم المسارات النظيفة
RUN a2enmod rewrite

# نسخ جميع الملفات إلى مجلد الويب
COPY . /var/www/html/

# تكوين Apache للاستماع على المنفذ 10000 (المنفذ الذي يستخدمه Render)
RUN sed -i 's/80/10000/g' /etc/apache2/ports.conf && \
    sed -i 's/:80/:10000/g' /etc/apache2/sites-available/000-default.conf

# تعيين مجلد العمل
WORKDIR /var/www/html/

# فتح المنفذ
EXPOSE 10000

# تشغيل Apache
CMD ["apache2-foreground"]