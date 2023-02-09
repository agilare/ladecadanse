#!/bin/bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php install
php composer.phar install
