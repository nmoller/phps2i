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
COPY ./pre-start/ ${APP_DATA}/php-pre-start

USER 1001