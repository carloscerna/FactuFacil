#!/bin/bash
# -*- ENCODING: UTF-8 -*-

clear
export PGPASSWORD="Orellana"
/opt/PostgreSQL/9.6/bin/pg_dump -w -U postgres -v -F c sistema_facturacion > /var/www/html/backup.dump
chmod -R 7777 /var/www/html/backup.dump

exit

