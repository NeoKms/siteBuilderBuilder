FROM upachko/nginx-sftp:u16p7.4

RUN  echo 'root:3cqlrJZusKI' | chpasswd

RUN apt-get update && apt-get install -y git
RUN cd /var/www \
    && git clone https://github.com/NeoKms/siteBuilderBuilder.git \
	&& rm -rf /var/www/html/ \
	&& mv -v /var/www/siteBuilderBuilder/* /var/www/
RUN cd /var/www/ \
	&& php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
	&& php composer-setup.php \
	&& php -r "unlink('composer-setup.php');" \
	&& php composer.phar install

#rights
RUN chown -R www-data:www-data /var/www/html
RUN chmod 777 -R /var/www

EXPOSE 80 22 443

CMD ["service" "php7.4-fpm", "start"]
CMD ["/usr/bin/supervisord"]
