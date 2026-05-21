FROM ubuntu:20.04

ENV IN_DOCKER=1 \
    USE_HTTP=0 \
    FAIL2BAN=1 \
    SERVERFQDN=localhost \
    MYSQLSERVER=db \
    WEBADMINPASS=admin \
    EMAILALERT=root@localhost \
    LOG_ROTATE_SIZE=100M \
    LOG_ROTATE_KEEP=7 \
    LEGACY_CLIENT=0 \
    ENABLE_BLUECONNECT=0 \
    LANG=C.UTF-8 \
    LC_ALL=C.UTF-8 \
    DEBIAN_FRONTEND=noninteractive \
    BLUESKY_VERSION=2.6.0-rc.3

RUN apt-get update && \
    apt-get install --no-install-recommends -y apache2 \
    openssh-server \
    openssl \
    curl \
    cron \
    mysql-client \
    php-mysql \
    php \
    libapache2-mod-php \
    php-mysql \
    inoticoming \
    supervisor \
    cpio \
    netcat \
    swaks \
    rsyslog \
    fail2ban \
    iptables \
    logrotate \
    uuid-runtime \
    libnet-ssleay-perl \
    ca-certificates && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN mkdir /usr/local/bin/BlueSkyConnect /var/run/sshd  /var/run/fail2ban

COPY . /usr/local/bin/BlueSkyConnect/

RUN dpkg -i /usr/local/bin/BlueSkyConnect/docker/libssl1.0.0_1.0.2n-1ubuntu5.8_amd64.deb && \
  rm /usr/local/bin/BlueSkyConnect/docker/libssl1.0.0_1.0.2n-1ubuntu5.8_amd64.deb

ARG RCODESIGN_VERSION=0.27.0
RUN curl -fsSL -o /tmp/rcodesign.tar.gz \
      "https://github.com/indygreg/apple-platform-rs/releases/download/apple-codesign%2F${RCODESIGN_VERSION}/apple-codesign-${RCODESIGN_VERSION}-x86_64-unknown-linux-musl.tar.gz" && \
    tar -xzf /tmp/rcodesign.tar.gz -C /tmp && \
    install -m 0755 /tmp/apple-codesign-*/rcodesign /usr/local/bin/rcodesign && \
    rm -rf /tmp/rcodesign.tar.gz /tmp/apple-codesign-*

RUN mv /usr/local/bin/BlueSkyConnect/docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf && \
	mv /usr/local/bin/BlueSkyConnect/docker/* /usr/local/bin/ && \
	touch /var/log/auth.log /etc/default/locale && \
	chown syslog:adm /var/log/auth.log && \
	chmod 640 /var/log/auth.log && \
	chmod +x /usr/local/bin/run /usr/local/bin/fail2ban-supervisor.sh /usr/local/bin/build_pkg.sh /usr/local/bin/build_admin_pkg.sh /usr/local/bin/sign-helpers.sh /usr/local/bin/notarize-pkgs.sh && \
	echo "ServerName CHANGETHIS" >> /etc/apache2/apache2.conf

EXPOSE 22 80 443

VOLUME ["/certs", "/home/admin/.ssh", "/home/bluesky/.ssh", "/tmp/pkg"]

CMD ["/usr/local/bin/run"]
