## Setup Magento 2
open docker folder
```
git clone https://github.com/magento/magento2.git
git checkout 2.1
```

## Setup local domain
```
sudo -- sh -c "echo '127.0.0.1 magento2.docker' >> /etc/hosts"
```

## Run
```
docker exec -it web bash
```

App: http://magento2.docker
PHPMyAdmin: http://127.0.0.1:8080

## Install Magento2

Open App directory
```
cd /app
```

deploy sample data
```
php bin/magento sampledata:deploy
```

Install
```
php bin/magento setup:install \
--admin-firstname=Admin \
--admin-lastname=istrator \
--admin-email=admin@admin.com \
--admin-user=admin \
--admin-password='abcd1234' \
--base-url=http://magento2.docker \
--base-url-secure=https://magento2.docker \
--backend-frontname=admin \
--db-host=mysql \
--db-name=magento \
--db-user=root \
--db-password=root \
--use-rewrites=1 \
--language=en_US \
--currency=IDR \
--timezone=America/New_York \
--use-secure-admin=1 \
--admin-use-security-key=1 \
--session-save=files \
--use-sample-data
```

## TODO
- [ ] Create our own image
- [ ] Integrate with our plugin
- [ ] Support other version