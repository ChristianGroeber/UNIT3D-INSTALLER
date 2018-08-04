<?php

namespace App\Installer\Database;

use App\Installer\BaseInstaller;

class MySqlSetup extends BaseInstaller
{
    public function handle()
    {
        $root_pass = $this->config->app('dbrootpass');
        $db = $this->config->app('db');
        $dbuser = $this->config->app('dbuser');
        $dbpass = $this->config->app('dbpass');

        switch (true) {
            case (memory() >= 1200000 && memory() < 3900000):
                $mycnf = "my-medium.cnf";
                break;
            case (memory() >= 3900000):
                $mycnf = "my-large.cnf";
                break;
            default:
                $mycnf = "my-small.cnf";
        }

        $this->process(['cp -f ' . resource_path(distname() . "/mysql/$mycnf") . ' /etc/mysql/my.cnf']);

        if (distmainver() !== '16.04') {
            $this->process(['mysql_install_db']);
        }

        if (distmainver() === '18.04') {
            $this->process([
                'mkdir /var/lib/mysql',
                'chown mysql:mysql /var/lib/mysql',
                'mysqld --initialize-insecure',
            ]);
        }

        $this->process([
            'update-rc.d mysql defaults',
            'service mysql start',
            "mysqladmin -u root password $root_pass",
            "echo -e \"[client]\npassword='$root_pass'\n\" > /root/.my.cnf",
            'chmod 600 /root/.my.cnf'
        ]);

        /*
         * Critical
         */
        $this->process([
            "mysql -e \"CREATE database $db\"",
            "mysql -e \"CREATE USER '$dbuser'@'localhost' IDENTIFIED BY '$dbpass';\"",
            "mysql -e \"GRANT ALL PRIVILEGES ON $db . * TO '$dbuser'@'localhost'\"",
            "mysql -e \"UPDATE mysql.user SET authentication_string=PASSWORD('$root_pass') WHERE User='root'\"",
            "mysql -e \"DELETE FROM mysql.user WHERE User=''\"",
            "mysql -e \"DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1')\"",
        ]);

        /*
         * Non-Critical
         */
        $this->process([
            "mysql -e \"DROP DATABASE test\"",
            "mysql -e \"DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%'\"",
        ], true);


        $this->process(["mysql -e \"FLUSH PRIVILEGES\""]);

        $this->io->writeln(' ');
    }
}