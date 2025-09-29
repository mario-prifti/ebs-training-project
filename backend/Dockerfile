FROM yiisoftware/yii2-php:8.3-apache

# Change document root for Apache
RUN sed -i -e 's|/app/web|/app/backend/web|g' /etc/apache2/sites-available/000-default.conf
RUN composer require react/socket
RUN composer require ratchet/pawl
RUN composer require ratchet/rfc6455
RUN composer require cboden/ratchet