## Setup Magento 2
Clone magento 2 in docker folder
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
docker-compose up -d --build
```

App: http://magento2.docker
PHPMyAdmin: http://127.0.0.1:8080

## Access docker container
```
docker exec -it web bash
```

## Install Magento2

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

## Seeding sample data

Add reference to repo.magento.com in composer.json, this is needed if you clone repo from github.

```
composer config repositories.0 composer https://repo.magento.com
```
nb: you need repo.magento.com cridential

Install sample data:
```
php bin/magento sampledata:deploy
```

Run the following command to update the database
```
bin/magento setup:upgrade
```

## TODO
- [x] Create docker for magento
- [ ] Create our own image
- [x] Integrate with our plugin
- [ ] Support other version
- [ ] Seeding product
- [ ] Add Xendit API Key
- [ ] Integrate with E2E testing