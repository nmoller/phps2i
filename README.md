# Utilisation des images php s2i.

``` 
Usage:
  s2i build <source> <image> [<tag>] [flags]
```
Voir https://github.com/openshift/source-to-image/blob/master/docs/cli.md 

Pour commencer, on veut bâtir une image de moodle35.
```
s2i build https://github.com/moodle/moodle centos/php-70-centos7 moodle-35 --ref=MOODLE_35_STABLE -e COMPOSER_ARGS=--no-dev
```
Ça prend du temps cependant on voit les processus:
```
nmoller  19021  0.0  0.0  14960  9588 pts/0    Sl+  10:14   0:00 s2i build https://github.com/moodle/moodle centos/php-70
nmoller  19106  0.0  0.0  19208  4468 pts/0    S+   10:14   0:00 git clone --quiet https://github.com/moodle/moodle /tmp/
nmoller  19109  3.0  0.1 200424 23388 pts/0    S+   10:14   0:04 /usr/lib/git-core/git-remote-https origin https://github
nmoller  19111  1.1  0.0  27504  4312 pts/0    S+   10:15   0:01 /usr/lib/git-core/git fetch-pack --stateless-rpc --stdin
nmoller  19113 57.4  0.9 368920 148124 pts/0   Sl+  10:15   1:17 /usr/lib/git-core/git index-pack --stdin --fix-thin --ke
```
L'image `moodle-35` est construite. Pour lancer:
```
docker run --rm  -p 8080:8080 moodle-35
=> sourcing 20-copy-config.sh ...
---> 15:23:10     Processing additional arbitrary httpd configuration provided by s2i ...
=> sourcing 00-documentroot.conf ...
=> sourcing 50-mpm-tuning.conf ...
=> sourcing 40-ssl-certs.sh ...
AH00558: httpd: ...
```
On voit le processus au démarrage. 

On peut ajouter des options pour composer:
```
s2i build https://bitbucket.org/uqam/after_before_validator centos/php-70-centos7 moodle-tester  -e COMPOSER_ARGS="--no-dev --no-autoloader" -e COMPOSER_HOME=/opt/app-root/src/composer
```
Si l'on a besoin d'utiliser des resources protégées avec nos clés:
```
s2i build git@bitbucket.org:uqam/after_before_validator.git \
centos/php-70-centos7 moodle-tester \
-v ~/.ssh/id_rsa:/opt/app-root/src/.ssh/id_rsa  \
-e COMPOSER_ARGS="--no-dev --no-autoloader"
```
Même si après le build, on trouve un fichier `id_rsa` dans l'image... votre clé n'est pas là:
```
docker run -it --rm -u 0 moodle-tester bash

bash-4.2# ls -altr
total 8
-rwxr-xr-x  1 root    root    0 Nov  7 17:36 id_rsa
drwxr-xr-x  2 root    root 4096 Nov  7 17:36 .
drwxrwxr-x 11 default root 4096 Nov  7 17:36 ..
bash-4.2# pwd
/opt/app-root/src/.ssh
```

## Utilisation de redis pour les sessions

Le site:

https://github.com/phpredis/phpredis#php-session-handler

Dans le container s2i, on trouve:
```
yum search pecl-redis
# on y voit
sclo-php56-php-pecl-redis.x86_64 
sclo-php70-php-pecl-redis.x86_64
sclo-php70-php-pecl-redis4.x86_64
sclo-php71-php-pecl-redis.x86_64
sclo-php71-php-pecl-redis4.x86_64
```
On a pour la version 3 et 4 de `redis`. Vue le cycle de vie de php, il est raisonnable d'aller avec la `7.1`. Ce sera donc (on commence avec `redis 3`:

```
FROM centos/php-71-centos7

USER 0

RUN yum install -y centos-release-scl && \
    INSTALL_PKGS="rh-php71-php-xmlrpc sclo-php71-php-pecl-redis" && \
    yum install -y --setopt=tsflags=nodocs $INSTALL_PKGS --nogpgcheck && \
    rpm -V $INSTALL_PKGS && \
    yum clean all -y

USER 1001

```

Une fois qu'on a l'image `nmolleruq/s2i-centos7-php71:latest` (voir structure de tagging).

```
s2i build git@bitbucket.org:uqam/after_before_validator.git \
nmolleruq/s2i-centos7-php71 moodle-tester \
-v ~/.ssh/id_rsa:/opt/app-root/src/.ssh/id_rsa  \
-e COMPOSER_ARGS="--no-dev --no-autoloader"
```

## Auth_saml2

On a besoin de mcrypt pour le faire fonctionner.

```
FROM centos/php-71-centos7

USER 0

# dep pour sclo-php71-php-mcrypt
RUN yum install -y epel-release && \
    yum install -y --setopt=tsflags=nodocs libmcrypt-devel --nogpgcheck 

RUN INSTALL_PKGS="rh-php71-php-xmlrpc sclo-php71-php-pecl-redis sclo-php71-php-mcrypt" && \
    yum install -y --setopt=tsflags=nodocs $INSTALL_PKGS --nogpgcheck && \
    rpm -V $INSTALL_PKGS && \
    yum clean all -y && rm -rf /var/cache/yum


# C'est qu'on va traiter les problèmes de permission.....
# Aussi la configuration du muc...
COPY ./pre-start/ ${APP_DATA}/php-pre-start

USER 1001
```
Je fais:
```
docker build -t moodle-tester .

s2i build https://bitbucket.org/uqam/after_before_validator \
moodle-tester moodle-tester  -v ~/.ssh/id_rsa:/opt/app-root/src/.ssh/id_rsa  \
-e COMPOSER_ARGS="--no-dev --no-autoloader" -e COMPOSER_HOME=/opt/app-root/src/composer

docker run --rm -p 8080:8080 moodle-tester
```
On y lit:
```
=> sourcing 20-copy-config.sh ...
---> 19:05:42     Processing additional arbitrary httpd configuration provided by s2i ...
=> sourcing 00-documentroot.conf ...
=> sourcing 50-mpm-tuning.conf ...
=> sourcing 40-ssl-certs.sh ...
=> sourcing test01.sh ...
I've run ....
AH00558: httpd: Could not reliably determine the server's fully qualified domain name, using 172.17.0.2. Set the 'ServerName' di
```

#### Revoir `SCC`

`SCC` security context constraint

Pour que le pod puisse avoir accès au user root:
```
oc adm policy add-scc-to-user anyuid developer
##scc "anyuid" added to: ["developer"]
oc login -u developer
#The server uses a certificate signed by an unknown authority.
#You can bypass the certificate check, but any data you send to the server could be intercepted by others.
#Use insecure connections? (y/n): y

#Logged into "https://192.168.99.100:8443" as "developer" using existing credentials.

#You have one project on this server: "myproject"

#Using project "myproject".
oc create -f extras/redis-permission-pod.yaml
```
et cette fois, on arrive à modifier les permissions et le container redis demarre sans problèmes.

## Moodledata

Où le volume sera monté:
```
/opt/app-root/moodledata
```

Pour aller chercher le fichier de configuration du muc:
```
oc rsync pod/moodle-1-64vhb:/opt/app-root/moodledata/muc/config.php .
```
c'est sûr que le nom du pod changera...
```
oc delete pvc,deployment,pod,svc,routes -l app=wp001
```