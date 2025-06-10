"docker does not seem to be working inside the WSL2 distro yet."

https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/#windows

https://stackoverflow.com/questions/61592709/docker-not-running-on-ubuntu-wsl-due-to-error-cannot-connect-to-the-docker-daemo#comment128436901_61592709


Windows PowerShell
Copyright (C) Microsoft Corporation. All rights reserved.
                                                                                                                        Try the new cross-platform PowerShell https://aka.ms/pscore6                                                                                                                                                                                    PS C:\Users\Rob> Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072;
PS C:\Users\Rob> iex ((New-Object System.Net.WebClient).DownloadString('https://raw.githubusercontent.com/ddev/ddev/main/scripts/install_ddev_wsl2_docker_desktop.ps1'))
docker : The term 'docker' is not recognized as the name of a cmdlet, function, script file, or operable program.
Check the spelling of the name, or if a path was included, verify that the path is correct and try again.
At line:26 char:45
+ if (-not(Get-Command docker 2>&1 ) -Or -Not(docker ps ) ) {
+                                             ~~~~~~
    + CategoryInfo          : ObjectNotFound: (docker:String) [], CommandNotFoundException
    + FullyQualifiedErrorId : CommandNotFoundException

Detected OS architecture: AMD64; using DDEV installer: amd64
The latest ddev/ddev version is v1.24.6.
Downloading from https://github.com/ddev/ddev/releases/download/v1.24.6/ddev_windows_amd64_installer.v1.24.6.exe...
Waiting up to 60 seconds for C:\Program Files\DDEV\mkcert.exe binary...
The local CA is already installed in the system trust store! 👍
Note: Firefox support is not available on your platform. ℹ️

Hit:1 https://download.docker.com/linux/ubuntu noble InRelease
Hit:2 http://security.ubuntu.com/ubuntu noble-security InRelease
Hit:3 http://archive.ubuntu.com/ubuntu noble InRelease
Hit:4 http://archive.ubuntu.com/ubuntu noble-updates InRelease
Hit:5 http://archive.ubuntu.com/ubuntu noble-backports InRelease
Get:6 https://pkg.ddev.com/apt * InRelease
Fetched 5706 B in 1s (7727 B/s)
Reading package lists... Done
Reading package lists... Done
Building dependency tree... Done
Reading state information... Done
curl is already the newest version (8.5.0-2ubuntu10.6).
The following packages were automatically installed and are no longer required:
  libdrm-intel1 libpciaccess0 libsensors-config libsensors5
Use 'apt autoremove' to remove them.
0 upgraded, 0 newly installed, 0 to remove and 9 not upgraded.
Hit:1 http://archive.ubuntu.com/ubuntu noble InRelease
Hit:2 http://archive.ubuntu.com/ubuntu noble-updates InRelease
Hit:3 http://security.ubuntu.com/ubuntu noble-security InRelease
Hit:4 http://archive.ubuntu.com/ubuntu noble-backports InRelease
Hit:5 https://download.docker.com/linux/ubuntu noble InRelease
Get:6 https://pkg.ddev.com/apt * InRelease
Fetched 5706 B in 0s (12.7 kB/s)
Reading package lists... Done
Reading package lists... Done
Building dependency tree... Done
Reading state information... Done
ddev is already the newest version (1.24.6).
wslu is already the newest version (3.2.3-0ubuntu3).
The following packages were automatically installed and are no longer required:
  libdrm-intel1 libpciaccess0 libsensors-config libsensors5
Use 'apt autoremove' to remove them.
0 upgraded, 0 newly installed, 0 to remove and 9 not upgraded.
/mnt/c/Users/Rob/AppData/Local/mkcert
The local CA is already installed in the system trust store! 👍

docker-compose 71.75 MiB / 71.75 MiB [======================================================================] 100.00% 3s
Download complete.
 ITEM             VALUE
 DDEV version     v1.24.6
 architecture     amd64
 cgo_enabled      0
 db               ddev/ddev-dbserver-mariadb-10.11:v1.24.6
 ddev-ssh-agent   ddev/ddev-ssh-agent:v1.24.6
 docker           28.2.2
 docker-api       1.50
 docker-compose   v2.36.1
 docker-platform  wsl2-docker-ce
 global-ddev-dir  /home/robdavisprojects/.ddev
 go-version       go1.24.3
 mutagen          0.18.1
 os               linux
 router           ddev/ddev-traefik-router:v1.24.6
 web              ddev/ddev-webserver:v1.24.6
 xhgui-image      ddev/ddev-xhgui:v1.24.6

PS C:\Users\Rob>
