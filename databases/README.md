user: uslnt
db: dslnt

CREATE USER 'uslnt'@'localhost' IDENTIFIED BY 'uslnt';
create DATABASE dslnt; GRANT ALL PRIVILEGES ON dslnt.* TO uslnt@localhost IDENTIFIED BY '<password>';
FLUSH PRIVILEGES;

$databases['default']['default'] = array (
  'database' => 'dslnt',
  'username' => 'uslnt',
  'password' => '<password>',
  'host' => 'localhost',
  'port' => '3306',
  'driver' => 'mysql',
);

SET PASSWORD FOR 'uslnt'@'localhost' = PASSWORD('<password>');





db: dslnt2

create DATABASE dslnt2; GRANT ALL PRIVILEGES ON dslnt2.* TO uslnt@localhost IDENTIFIED BY '<password>';
FLUSH PRIVILEGES;

$databases['default']['default'] = array (
  'database' => 'dslnt2',
  'username' => 'uslnt',
  'password' => '<password>',
  'host' => 'localhost',
  'port' => '3306',
  'driver' => 'mysql',
);

