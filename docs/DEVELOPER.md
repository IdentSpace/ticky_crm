# Developer documentation

## IDE
Use an IDE you like, but check if it can handle Markdown and Mermaid correctly.

## Commands

### NPM
```
npm run dev
npm run lint
npm run lint:fix
npm run build
npm run build:code
npm run build:translations
```

### Nextclous
```
php occ files:cleanup
```

## Requirements

- **NodeJS:** v24.x
- **NPM:** v10.x to 11.x
- Ev. **Docker:**

## On boarding

- Clone repository from gitlab ```git clone https://github.com/IdentSpace/ticky_crm.git```
- Do an ```npm i``` to install all packages - use ```npm i --force``` if you get some dependency errors
- Try ```npm run build``` and ```npm run build:translations```
- If you have a local Nextcloud instance running (Linux):
    - Add symbolic link ```ln -s <source dir> <target dir>``` where source dir is your cloned repository and target dir is in your www/nextcloud/apps folder e.g. ```ln -s /home/user/ticky_crm /var/www/nextcloud/apps/ticky_crm```
    - Ensure that www-data has write access to this directory e.g. /home/user/ticky_crm
    - Now you should be able to activate the app ticky_crm in the Nextcloud
    - Download and install composer (PHP) from https://getcomposer.org/
    - Execute ```composer i```

