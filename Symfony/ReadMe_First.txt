
URL possibles:

http://{{host}}/Symfony/web/paradigm/

http://{{host}}/Symfony/web/app_dev.php/paradigm/

http://{{host}}/Symfony/web/app.php/paradigm/


In virtualhost mode: enable subfolders "AllowOverride All":


<VirtualHost virtualhost:80>
    DocumentRoot "C:/www/"
    ServerName virtualhost
    <Directory C:/www/>
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Order Deny,Allow
        Allow from all
        Require all granted
    </Directory>
</VirtualHost>