# Connexion BB

```
oc secrets new-sshauth uqamena-moodle --ssh-privatekey=$HOME/.ssh/id_rsa_uqamena_h
oc secrets link builder uqamena-moodle
oc annotate secret/uqamena-moodle 'build.openshift.io/source-secret-match-uri-1=ssh://bitbucket.org:uqam/moodle.git'
oc set build-secret --source bc/moodle-bb uqamena-moodle
# finalement on est prê
oc new-app --name moodle-bb  s2i-centos7-php71~git@bitbucket.org:uqam/moodle.git#UQAM_35_DEV
```

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

## Running on os
Bâtir l'image et l'importer comme imagestream:
```
cd docker
docker build -t nmolleruq/php-71-centos-moodle .
docker push nmolleruq/php-71-centos-moodle:latest
oc import-image nmolleruq/php-71-centos-moodle:latest --confirm
```

il y a une imagestream `php-71-centos-moodle` dans openshift. Le `bc` lancera un nouveau build.


```
oc import-image nmolleruq/s2i-centos7-php71 --confirm 
```
ce qui donne comme sortie:
```
imagestream.image.openshift.io/s2i-centos7-php71 imported

Name:			s2i-centos7-php71
Namespace:		myproject
Created:		2 seconds ago
Labels:			<none>
Annotations:		openshift.io/image.dockerRepositoryCheck=2018-11-17T19:33:28Z
Docker Pull Spec:	172.30.1.1:5000/myproject/s2i-centos7-php71
Image Lookup:		local=false
Unique Images:		1
Tags:			1

latest
  tagged from nmolleruq/s2i-centos7-php71

  * nmolleruq/s2i-centos7-php71@sha256:b93a3125d1ca2cb1f05fc7594f9f8ac11c72cd8539068a66a32be064a616b9ef
      2 seconds ago

Image Name:	s2i-centos7-php71:latest
Docker Image:	nmolleruq/s2i-centos7-php71@sha256:b93a3125d1ca2cb1f05fc7594f9f8ac11c72cd8539068a66a32be064a616b9ef
Name:		sha256:b93a3125d1ca2cb1f05fc7594f9f8ac11c72cd8539068a66a32be064a616b9ef
Created:	2 seconds ago
Annotations:	image.openshift.io/dockerLayersOrder=ascending
Image Size:	231.5MB in 10 layers
Layers:		74.7MB	sha256:aeb7866da422acc7e93dcf7323f38d7646f6269af33bcdb6647f2094fc4b3bf7
		9.391MB	sha256:a968b438296f6dd2ba8edcfbf873bb9841f0c6634533f071047c487db1cd4a45
		4.745kB	sha256:facb1df24784f27b34979b37d5b1e8a886dcccc5b2fd87548b53f9cb32a41db7
		189.5kB	sha256:d58087aba1a848bf43f9c417d7715b90b9d88b071b99bb24cf1357c981fc6088
		84.14MB	sha256:dc2128ad988dfb899eee97923eab83cede81529426346c2c5a33a18c5366739d
		54.12MB	sha256:e7c1d61b9d14fb388cbb0b05aa4268d3be0413d96f5efe8bfad25900e44d57b8
		2.34kB	sha256:88a868b547e9d94ad6f5c31e7814a267dba73cbfdd31cfd61ef36b9adeec3706
		27.11kB	sha256:ccdec39ef1725ec0b6da6eb8347a4e98fc43f5e7216c823c02a2876affe18b35
		263kB	sha256:011b7bb06c5899592b2ff5192869e220807fe92b6b1126ab4f896117b05ad6d1
		8.659MB	sha256:8e207bc086a29a193e940c9ac989344ba60a07769f7c9d9406cd09843479f964
Image Created:	9 days ago
Author:		<none>
Arch:		amd64
Entrypoint:	container-entrypoint
Command:	/bin/sh -c $STI_SCRIPTS_PATH/usage
Working Dir:	/opt/app-root/src
User:		1001
Exposes Ports:	8080/tcp, 8443/tcp
Docker Labels:	com.redhat.component=rh-php71-container
		description=PHP 7.1 available as container is a base platform for building and running various PHP 7.1 applications and frameworks. PHP is an HTML-embedded scripting language. PHP attempts to make it easy for developers to write dynamically generated web pages. PHP also offers built-in database integration for several commercial and non-commercial database management systems, so writing a database-enabled webpage with PHP is fairly simple. The most common use of PHP coding is probably as a replacement for CGI scripts.
		help=For more information visit https://github.com/sclorg/s2i-php-container
		io.k8s.description=PHP 7.1 available as container is a base platform for building and running various PHP 7.1 applications and frameworks. PHP is an HTML-embedded scripting language. PHP attempts to make it easy for developers to write dynamically generated web pages. PHP also offers built-in database integration for several commercial and non-commercial database management systems, so writing a database-enabled webpage with PHP is fairly simple. The most common use of PHP coding is probably as a replacement for CGI scripts.
		io.k8s.display-name=Apache 2.4 with PHP 7.1
		io.openshift.builder-version="60378a6"
		io.openshift.expose-services=8080:http
		io.openshift.s2i.scripts-url=image:///usr/libexec/s2i
		io.openshift.tags=builder,php,php71,rh-php71
		io.s2i.scripts-url=image:///usr/libexec/s2i
		maintainer=SoftwareCollections.org <sclorg@redhat.com>
		name=centos/php-71-centos7
		org.label-schema.build-date=20181006
		org.label-schema.license=GPLv2
		org.label-schema.name=CentOS Base Image
		org.label-schema.schema-version=1.0
		org.label-schema.vendor=CentOS
		release=1
		summary=Platform for building and running PHP 7.1 applications
		usage=s2i build https://github.com/sclorg/s2i-php-container.git --context-dir=7.1/test/test-app centos/php-71-centos7 sample-server
		version=7.1
Environment:	PATH=/opt/app-root/src/bin:/opt/app-root/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/opt/rh/rh-php71/root/usr/bin
		SUMMARY=Platform for building and running PHP 7.1 applications
		DESCRIPTION=PHP 7.1 available as container is a base platform for building and running various PHP 7.1 applications and frameworks. PHP is an HTML-embedded scripting language. PHP attempts to make it easy for developers to write dynamically generated web pages. PHP also offers built-in database integration for several commercial and non-commercial database management systems, so writing a database-enabled webpage with PHP is fairly simple. The most common use of PHP coding is probably as a replacement for CGI scripts.
		STI_SCRIPTS_URL=image:///usr/libexec/s2i
		STI_SCRIPTS_PATH=/usr/libexec/s2i
		APP_ROOT=/opt/app-root
		HOME=/opt/app-root/src
		BASH_ENV=/opt/app-root/etc/scl_enable
		ENV=/opt/app-root/etc/scl_enable
		PROMPT_COMMAND=. /opt/app-root/etc/scl_enable
		NODEJS_SCL=rh-nodejs8
		PHP_VERSION=7.1
		PHP_VER_SHORT=71
		NAME=php
		PHP_CONTAINER_SCRIPTS_PATH=/usr/share/container-scripts/php/
		APP_DATA=/opt/app-root/src
		PHP_DEFAULT_INCLUDE_PATH=/opt/rh/rh-php71/root/usr/share/pear
		PHP_SYSCONF_PATH=/etc/opt/rh/rh-php71
		PHP_HTTPD_CONF_FILE=rh-php71-php.conf
		HTTPD_CONFIGURATION_PATH=/opt/app-root/etc/conf.d
		HTTPD_MAIN_CONF_PATH=/etc/httpd/conf
		HTTPD_MAIN_CONF_D_PATH=/etc/httpd/conf.d
		HTTPD_VAR_RUN=/var/run/httpd
		HTTPD_DATA_PATH=/var/www
		HTTPD_DATA_ORIG_PATH=/opt/rh/httpd24/root/var/www
		HTTPD_VAR_PATH=/opt/rh/httpd24/root/var
		SCL_ENABLED=rh-php71
```

On va tester une version moodle-35:
```
oc new-app php-71-centos-moodle~https://github.com/moodle/moodle.git#MOODLE_35_STABLE \
-e COMPOSER_ARGS="--no-dev --no-autoloader"
```

#### Si problèmes de permission
Ajouter volume au deployment généré et rouler le `access-pod.yaml` avant de faire l'installation.

Pour que les sessions s'en aillent vers `redis`:
```
$CFG->session_handler_class = '\core\session\redis';
$CFG->session_redis_host = 'redis02.myproject.svc';
$CFG->session_redis_port = 6379;  // Optional.
$CFG->session_redis_database = 0;  // Optional, default is db 0.
$CFG->session_redis_auth = 'redis'; // Optional, default is don't set one.
$CFG->session_redis_prefix = ''; // Optional, default is don't set one.
$CFG->session_redis_acquire_lock_timeout = 120;
$CFG->session_redis_lock_expire = 7200;
```
pour tester regarder `moodledata/sessions` et pour produire du volume:
```
docker run --rm --network host jordi/ab -n 8 \
http://moodle-myproject.192.168.99.100.nip.io/
```
`ne pas oublier` le slash final sinon `ab` n'aime pas ça.

```
oc create configmap moodle-config --from-file=config.php
```
et dans le deployment (le yaml dans os):
```
    volumeMounts:
	- mountPath: /opt/app-root/moodledata
	  name: volume-qgguo
	- mountPath: /opt/app-root/src/config.php
	  name: moodle-config
	  subPath: config.php

...
volumes:
    - name: volume-qgguo
      persistentVolumeClaim:
        claimName: moodle-dev-001
    - configMap:
        defaultMode: 420
        name: moodle-config
      name: moodle-config
```

### Test de charge.
Pour mieux visualiser (pas pour executer..): installer localement

https://jmeter.apache.org/download_jmeter.cgi?Preferred=ftp%3A%2F%2Fapache.mirrors.tds.net%2Fpub%2Fapache.org%2F

```
sudo apt install default-jre
# ajouter l'executable bin/jmeter dans le PATH 
sudo ln -s
```

https://www.blazemeter.com/blog/9-easy-solutions-jmeter-load-test-%E2%80%9Cout-memory%E2%80%9D-failure

Utiliser outil moodle por créer cours et test plan; par la suite:
```
docker run -it --rm -v `pwd`:/jmeter \
-e HEAP="-Xms2g -Xmx2g -XX:MaxMetaspaceSize=512m" \
rdpanek/jmeter:latest \
--nongui -Jusersfile=users_201811181411_7129.csv \
--testfile testplan_201811181411_7368.jmx --logfile result004.jtl
```

Pour être en contrôle des paramètres:
```
jmeter --nongui -Jusersfile=users.csv --testfile testplan.jmx --logfile result001.jtl \
 -Jusers=30 -Jrampup=10 -Jloops=2
```

Voir:

https://octoperf.com/blog/2017/10/19/how-to-analyze-jmeter-results/

Pour mieux visualiser les résultats:
```
jmeter -g result.jtl -o test001
```